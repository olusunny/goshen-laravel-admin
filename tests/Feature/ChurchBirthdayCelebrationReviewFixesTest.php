<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\InboxMessage;
use App\Models\MobileUser;
use App\Services\Addons\AddonRuntimeLoader;
use App\Services\InboxMessageDeliveryService;
use Carbon\CarbonImmutable;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayCelebration;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayDelivery;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayGreeting;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayReport;
use ChurchTools\ChurchBirthdayCelebrations\Services\AddonAvailability;
use ChurchTools\ChurchBirthdayCelebrations\Services\BirthdayCardService;
use ChurchTools\ChurchBirthdayCelebrations\Services\BirthdayEligibilityService;
use ChurchTools\ChurchBirthdayCelebrations\Services\BirthdayLifecycleService;
use ChurchTools\ChurchBirthdayCelebrations\Services\BirthdayNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class ChurchBirthdayCelebrationReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    private string $installPath;

    protected function setUp(): void
    {
        parent::setUp();

        config(['addons.install_path' => 'addons/testing-birthday-production-blockers']);
        $this->installPath = base_path('addons/testing-birthday-production-blockers/church_birthday_celebrations');
        File::deleteDirectory(dirname($this->installPath));
        File::copyDirectory(base_path('packages/church-birthday-celebrations-addon'), $this->installPath);

        $manifest = json_decode((string) File::get($this->installPath.'/addon.json'), true, flags: JSON_THROW_ON_ERROR);
        Addon::query()->create([
            'package_key' => $manifest['package_key'],
            'name' => $manifest['name'],
            'installed_version' => $manifest['version'],
            'status' => Addon::STATUS_ACTIVE,
            'manifest' => $manifest,
            'install_path' => $this->installPath,
        ]);

        app(AddonRuntimeLoader::class)->registerAddon($manifest, $this->installPath);
        $this->artisan('migrate', [
            '--path' => $this->installPath.'/database/migrations',
            '--realpath' => true,
            '--force' => true,
        ])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->installPath));

        parent::tearDown();
    }

    public function test_multi_celebrant_digest_is_linked_and_deleted_when_any_celebrant_is_purged(): void
    {
        $deliveryService = Mockery::mock(InboxMessageDeliveryService::class);
        $deliveryService->shouldReceive('dispatch')->zeroOrMoreTimes()->andReturn(['push' => [], 'email' => []]);
        $this->app->instance(InboxMessageDeliveryService::class, $deliveryService);

        $first = $this->celebration($this->member('First Celebrant'), BirthdayCelebration::CLOSED);
        $second = $this->celebration($this->member('Second Celebrant'), BirthdayCelebration::CLOSED);
        $recipient = $this->member('Digest Recipient', ['email_verified_at' => null, 'birthday_month' => 8, 'birthday_day' => 15]);

        $this->invokeLifecycle('digest', CarbonImmutable::parse('2026-07-30 12:00:00', 'Africa/Lagos'), [$first, $second]);

        $delivery = BirthdayDelivery::query()->where('mobile_user_id', $recipient->id)->where('kind', 'digest')->firstOrFail();
        $this->assertSame([$first->id, $second->id], $delivery->celebrations()->orderBy('birthday_celebrations.id')->pluck('birthday_celebrations.id')->all());
        $this->assertNotNull($delivery->inbox_message_id);
        $this->assertDatabaseHas('inbox_messages', ['id' => $delivery->inbox_message_id]);

        $second->forceFill(['purge_due_at' => now()->addDay()])->save();
        $this->lifecycle()->run();
        $this->assertDatabaseMissing('birthday_celebration_deliveries', ['id' => $delivery->id]);
        $this->assertDatabaseMissing('inbox_messages', ['id' => $delivery->inbox_message_id]);
        $this->assertSame(BirthdayCelebration::CLOSED, $second->fresh()->status);
    }

    public function test_missed_run_publication_uses_the_midday_transition_time_and_a_twenty_four_hour_window(): void
    {
        $member = $this->member('Midday Celebrant', ['birthday_month' => 7, 'birthday_day' => 30]);
        $celebration = $this->celebration($member, BirthdayCelebration::PREVIEW_READY, ['card_path' => 'already-generated.svg']);
        $notifier = Mockery::mock(BirthdayNotifier::class);
        $notifier->shouldReceive('send')->zeroOrMoreTimes();
        $notifier->shouldReceive('retryFailed')->zeroOrMoreTimes();
        $lifecycle = $this->lifecycle($notifier);
        $transition = CarbonImmutable::parse('2026-07-30 12:34:56', 'Africa/Lagos');

        CarbonImmutable::setTestNow($transition);
        try {
            $lifecycle->run();
        } finally {
            CarbonImmutable::setTestNow();
        }

        $published = $celebration->fresh();
        $this->assertSame('2026-07-30 12:34:56', $published->published_at->setTimezone('Africa/Lagos')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-31 12:34:56', $published->closes_at->setTimezone('Africa/Lagos')->format('Y-m-d H:i:s'));
        $this->assertSame(86400.0, $published->published_at->diffInSeconds($published->closes_at));
    }

    public function test_report_is_rejected_immediately_after_the_interaction_window_closes(): void
    {
        $celebrant = $this->member('Celebrant');
        $reporter = $this->member('Reporter');
        $celebration = $this->celebration($celebrant, BirthdayCelebration::PUBLISHED, ['closes_at' => now()->subSecond()]);
        $greeting = BirthdayGreeting::query()->create([
            'celebration_id' => $celebration->id,
            'mobile_user_id' => $celebrant->id,
            'body' => 'Happy birthday!',
        ]);

        $this->postJson("/api/v1/church-birthday-celebrations/celebrations/{$celebration->public_id}/greetings/{$greeting->id}/report", [
            'data' => ['api_token' => $reporter->issueApiToken(), 'reason' => 'Late report'],
        ])->assertStatus(409);

        $this->assertDatabaseCount('birthday_celebration_reports', 0);
    }

    public function test_digest_keeps_eligible_members_without_email_verification_and_excludes_visitor_audiences(): void
    {
        $celebration = $this->celebration($this->member('Celebrant'), BirthdayCelebration::PUBLISHED);
        $eligible = $this->member('No Email Verification', ['email_verified_at' => null]);
        $missingTriumphantId = $this->member('Missing Triumphant ID');
        $missingTriumphantId->forceFill(['triumphant_id' => null])->saveQuietly();
        $visitor = $this->member('Visitor', ['member_type' => 'visitor']);
        $goshenOnly = $this->member('Goshen Only', ['member_type' => 'visitor', 'triumphant_id' => null]);

        $this->invokeLifecycle('digest', CarbonImmutable::parse('2026-07-30 12:00:00', 'Africa/Lagos'), [$celebration]);

        $this->assertDatabaseHas('birthday_celebration_deliveries', ['mobile_user_id' => $eligible->id, 'kind' => 'digest']);
        $this->assertDatabaseMissing('birthday_celebration_deliveries', ['mobile_user_id' => $missingTriumphantId->id, 'kind' => 'digest']);
        $this->assertDatabaseMissing('birthday_celebration_deliveries', ['mobile_user_id' => $visitor->id, 'kind' => 'digest']);
        $this->assertDatabaseMissing('birthday_celebration_deliveries', ['mobile_user_id' => $goshenOnly->id, 'kind' => 'digest']);
    }

    private function lifecycle(?BirthdayNotifier $notifier = null): BirthdayLifecycleService
    {
        $availability = Mockery::mock(AddonAvailability::class);
        $availability->shouldReceive('isActive')->andReturn(true);

        return new BirthdayLifecycleService(
            $availability,
            new BirthdayEligibilityService(),
            new BirthdayCardService(),
            $notifier ?? new BirthdayNotifier(),
        );
    }

    private function invokeLifecycle(string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionMethod(BirthdayLifecycleService::class, $method);

        return $reflection->invoke($this->lifecycle(), ...$arguments);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function member(string $name, array $overrides = []): MobileUser
    {
        $number = MobileUser::query()->count() + 1;

        return MobileUser::query()->create(array_replace([
            'name' => $name,
            'email' => "birthday-{$number}@example.test",
            'password' => 'secret',
            'member_type' => 'church_member',
            'is_verified' => true,
            'is_blocked' => false,
            'is_deleted' => false,
            'triumphant_id' => "TM{$number}",
            'birthday_month' => 7,
            'birthday_day' => 30,
        ], $overrides));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function celebration(MobileUser $member, string $status, array $overrides = []): BirthdayCelebration
    {
        return BirthdayCelebration::query()->create(array_replace([
            'mobile_user_id' => $member->id,
            'birthday_year' => 2026,
            'birthday_date' => '2026-07-30',
            'status' => $status,
            'display_name' => $member->name,
            'published_at' => $status === BirthdayCelebration::PUBLISHED ? now()->subHour() : null,
            'closes_at' => $status === BirthdayCelebration::PUBLISHED ? now()->addDay() : null,
            'purge_due_at' => $status === BirthdayCelebration::CLOSED ? now()->subSecond() : null,
        ], $overrides));
    }
}
