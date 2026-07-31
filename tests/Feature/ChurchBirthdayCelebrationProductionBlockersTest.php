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
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayPreference;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdaySetting;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayTemplate;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayVerse;
use ChurchTools\ChurchBirthdayCelebrations\Services\AddonAvailability;
use ChurchTools\ChurchBirthdayCelebrations\Services\BirthdayCardService;
use ChurchTools\ChurchBirthdayCelebrations\Services\BirthdayEligibilityService;
use ChurchTools\ChurchBirthdayCelebrations\Services\BirthdayInteractionService;
use ChurchTools\ChurchBirthdayCelebrations\Services\BirthdayLifecycleService;
use ChurchTools\ChurchBirthdayCelebrations\Services\BirthdayNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ChurchBirthdayCelebrationProductionBlockersTest extends TestCase
{
    use RefreshDatabase;

    private string $installPath;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'addons.install_path' => 'addons/testing-birthday-production-blockers',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'queue.default' => 'sync',
        ]);
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
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_api_contract_exposes_choices_identity_and_correction_without_private_birth_data(): void
    {
        $template = BirthdayTemplate::query()->create(['name' => 'Gold', 'version' => 2, 'is_active' => true, 'is_default' => true]);
        $verse = BirthdayVerse::query()->create(['reference' => 'Numbers 6:24', 'body' => 'The Lord bless you.', 'is_active' => true]);
        $member = $this->member('Contract Member');
        $celebration = $this->celebration($member, BirthdayCelebration::PUBLISHED);
        $greeting = BirthdayGreeting::query()->create([
            'celebration_id' => $celebration->id,
            'mobile_user_id' => $member->id,
            'body' => 'Thank you church family.',
        ]);

        $context = $this->withToken($member->issueApiToken())->getJson('/api/v1/church-birthday-celebrations/context')
            ->assertOk()
            ->assertJsonPath('data.templates.0.id', $template->id)
            ->assertJsonPath('data.verses.0.id', $verse->id);

        $this->withToken($member->issueApiToken())->putJson('/api/v1/church-birthday-celebrations/preferences', [
            'template_id' => $template->id,
            'verse_id' => $verse->id,
        ])->assertOk()
            ->assertJsonPath('data.preferences.template_id', $template->id)
            ->assertJsonPath('data.preferences.verse_id', $verse->id);

        $detail = $this->withToken($member->issueApiToken())->getJson("/api/v1/church-birthday-celebrations/celebrations/{$celebration->public_id}")
            ->assertOk()
            ->assertJsonPath('data.is_interactive', true)
            ->assertJsonPath('data.my_greeting_id', $greeting->id)
            ->assertJsonPath('data.greetings.0.is_mine', true);
        $this->assertStringNotContainsString('birthday_year', $context->getContent());
        $this->assertStringNotContainsString('"age"', $detail->getContent());

        $this->withToken($member->issueApiToken())->postJson('/api/v1/church-birthday-celebrations/birthday-correction-requests', [
            'birthday_month' => 8,
            'birthday_day' => 14,
            'reason' => 'The profile day is incorrect.',
        ])->assertOk()->assertJsonPath('data.correction_request.status', 'pending');
        $this->assertDatabaseHas('birthday_celebration_correction_requests', [
            'mobile_user_id' => $member->id,
            'birthday_month' => 8,
            'birthday_day' => 14,
        ]);
    }

    public function test_hub_exposes_profile_images_only_when_a_celebrant_allows_them(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-30 12:00:00', 'Africa/Lagos'));
        Storage::fake('public');
        $celebrant = $this->member('Profile Photo Celebrant');
        $viewer = $this->member('Birthday Viewer');
        Storage::disk('public')->put('avatars/birthday-celebrant.jpg', 'avatar');
        $celebrant->forceFill(['avatar' => 'avatars/birthday-celebrant.jpg'])->saveQuietly();
        $celebration = $this->celebration($celebrant, BirthdayCelebration::PUBLISHED);
        BirthdayPreference::query()->create([
            'mobile_user_id' => $celebrant->id,
            'use_profile_photo' => true,
        ]);

        $visible = $this->withToken($viewer->issueApiToken())
            ->getJson('/api/v1/church-birthday-celebrations/hub')
            ->assertOk();
        $this->assertStringContainsString(
            'avatars/birthday-celebrant.jpg',
            (string) data_get($visible->json(), 'data.today.0.avatar_url'),
        );

        $celebration->preference()->update(['use_profile_photo' => false]);
        $this->withToken($viewer->issueApiToken())
            ->getJson('/api/v1/church-birthday-celebrations/hub')
            ->assertOk()
            ->assertJsonPath('data.today.0.avatar_url', null);
    }

    public function test_initial_migration_can_resume_after_tables_exist_without_a_history_row(): void
    {
        $migration = '2026_07_30_000001_create_church_birthday_celebration_tables';
        DB::table('migrations')->where('migration', $migration)->delete();

        $this->artisan('migrate', [
            '--path' => $this->installPath.'/database/migrations',
            '--realpath' => true,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('migrations', ['migration' => $migration]);
    }

    public function test_opt_out_and_membership_loss_immediately_purge_media_notifications_and_access(): void
    {
        Storage::fake('local');
        $member = $this->member('Privacy Member');
        $celebration = $this->celebration($member, BirthdayCelebration::PUBLISHED, [
            'card_disk' => 'local',
            'card_path' => 'cards/private.png',
            'card_mime' => 'image/png',
            'metadata' => ['card_variants' => ['square' => 'cards/private-square.png', 'portrait' => 'cards/private.png']],
        ]);
        Storage::disk('local')->put('cards/private.png', 'private');
        Storage::disk('local')->put('cards/private-square.png', 'private');
        $message = InboxMessage::query()->create(['title' => 'Birthday', 'content' => 'Private name']);
        BirthdayDelivery::query()->create([
            'celebration_id' => $celebration->id,
            'mobile_user_id' => $member->id,
            'kind' => 'preview',
            'dedupe_key' => 'privacy-preview',
            'inbox_message_id' => $message->id,
        ]);

        $this->withToken($member->issueApiToken())->putJson('/api/v1/church-birthday-celebrations/preferences', [
            'visibility_enabled' => false,
        ])->assertOk()->assertJsonPath('data.eligibility_code', 'BIRTHDAY_OPTED_OUT');

        $this->assertSame(BirthdayCelebration::PURGED, $celebration->fresh()->status);
        Storage::disk('local')->assertMissing('cards/private.png');
        Storage::disk('local')->assertMissing('cards/private-square.png');
        $this->assertDatabaseMissing('inbox_messages', ['id' => $message->id]);
        $this->withToken($member->issueApiToken())->getJson("/api/v1/church-birthday-celebrations/celebrations/{$celebration->public_id}")
            ->assertStatus(403)->assertJsonPath('code', 'BIRTHDAY_OPTED_OUT');

        $second = $this->member('Suspended Member');
        $secondCelebration = $this->celebration($second, BirthdayCelebration::PREVIEW_READY);
        $second->update(['is_blocked' => true]);
        $this->assertSame(BirthdayCelebration::PURGED, $secondCelebration->fresh()->status);
    }

    public function test_opt_out_removes_every_digest_that_contains_the_member_without_purging_other_celebrants(): void
    {
        Storage::fake('local');
        $removed = $this->member('Removed From Digest');
        $remaining = $this->member('Remaining In Digest');
        $removedCelebration = $this->celebration($removed, BirthdayCelebration::PUBLISHED, ['card_disk' => 'local', 'card_path' => 'cards/removed.png']);
        $remainingCelebration = $this->celebration($remaining, BirthdayCelebration::PUBLISHED, ['birthday_year' => 2027, 'card_disk' => 'local', 'card_path' => 'cards/remaining.png']);
        Storage::disk('local')->put('cards/removed.png', 'removed');
        Storage::disk('local')->put('cards/remaining.png', 'remaining');
        $message = InboxMessage::query()->create(['title' => 'Today\'s birthdays', 'content' => 'Celebrate Removed From Digest, Remaining In Digest.']);
        $delivery = BirthdayDelivery::query()->create([
            'mobile_user_id' => $remaining->id,
            'kind' => 'digest',
            'dedupe_key' => 'digest-privacy',
            'inbox_message_id' => $message->id,
        ]);
        $delivery->celebrations()->attach([$removedCelebration->id, $remainingCelebration->id]);

        app(BirthdayLifecycleService::class)->purge($removedCelebration);

        $this->assertDatabaseMissing('birthday_celebration_deliveries', ['id' => $delivery->id]);
        $this->assertDatabaseMissing('inbox_messages', ['id' => $message->id]);
        $this->assertSame(BirthdayCelebration::PUBLISHED, $remainingCelebration->fresh()->status);
        Storage::disk('local')->assertExists('cards/remaining.png');
    }

    public function test_reenabling_after_opt_out_never_regenerates_a_purged_preview_or_birthday_record(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-30 09:00:00', 'Africa/Lagos'));
        $previewMember = $this->member('Purged Preview', ['birthday_month' => 8, 'birthday_day' => 4]);
        $birthdayMember = $this->member('Purged Birthday', ['birthday_month' => 7, 'birthday_day' => 30]);
        $preview = $this->celebration($previewMember, BirthdayCelebration::PREVIEW_READY, ['birthday_date' => '2026-08-04']);
        $birthday = $this->celebration($birthdayMember, BirthdayCelebration::PUBLISHED);
        $lifecycle = app(BirthdayLifecycleService::class);
        BirthdayPreference::query()->updateOrCreate(['mobile_user_id' => $previewMember->id], ['visibility_enabled' => false]);
        BirthdayPreference::query()->updateOrCreate(['mobile_user_id' => $birthdayMember->id], ['visibility_enabled' => false]);
        $lifecycle->reconcileMember($previewMember);
        $lifecycle->reconcileMember($birthdayMember);
        BirthdayPreference::query()->updateOrCreate(['mobile_user_id' => $previewMember->id], ['visibility_enabled' => true]);
        BirthdayPreference::query()->updateOrCreate(['mobile_user_id' => $birthdayMember->id], ['visibility_enabled' => true]);

        $cards = Mockery::mock(BirthdayCardService::class);
        $cards->shouldNotReceive('generate');
        $notifier = Mockery::mock(BirthdayNotifier::class);
        $notifier->shouldNotReceive('send');
        $notifier->shouldReceive('retryFailed')->once();
        $this->lifecycle($cards, $notifier)->run();

        foreach ([$preview, $birthday] as $celebration) {
            $fresh = $celebration->fresh();
            $this->assertSame(BirthdayCelebration::PURGED, $fresh->status);
            $this->assertNull($fresh->card_path);
            $this->assertNull($fresh->card_disk);
        }
    }

    public function test_hard_member_delete_purges_owned_birthday_media_and_messages_without_harming_remaining_celebrant(): void
    {
        Storage::fake('local');
        $deleted = $this->member('Deleted Member');
        $remaining = $this->member('Remaining Member');
        $deletedCelebration = $this->celebration($deleted, BirthdayCelebration::PURGED, ['card_disk' => 'local', 'card_path' => 'cards/deleted.png']);
        $remainingCelebration = $this->celebration($remaining, BirthdayCelebration::PUBLISHED, ['birthday_year' => 2027, 'card_disk' => 'local', 'card_path' => 'cards/remaining-delete.png']);
        Storage::disk('local')->put('cards/deleted.png', 'deleted');
        Storage::disk('local')->put('cards/remaining-delete.png', 'remaining');
        $individualMessage = InboxMessage::query()->create(['title' => 'Birthday', 'content' => 'Deleted Member']);
        BirthdayDelivery::query()->create([
            'celebration_id' => $deletedCelebration->id,
            'mobile_user_id' => $deleted->id,
            'kind' => 'celebrant',
            'dedupe_key' => 'deleted-individual',
            'inbox_message_id' => $individualMessage->id,
        ]);
        $digestMessage = InboxMessage::query()->create(['title' => 'Today\'s birthdays', 'content' => 'Deleted Member, Remaining Member']);
        $digest = BirthdayDelivery::query()->create([
            'mobile_user_id' => $remaining->id,
            'kind' => 'digest',
            'dedupe_key' => 'deleted-digest',
            'inbox_message_id' => $digestMessage->id,
        ]);
        $digest->celebrations()->attach([$deletedCelebration->id, $remainingCelebration->id]);

        $deleted->delete();

        Storage::disk('local')->assertMissing('cards/deleted.png');
        $this->assertDatabaseMissing('inbox_messages', ['id' => $individualMessage->id]);
        $this->assertDatabaseMissing('inbox_messages', ['id' => $digestMessage->id]);
        $this->assertDatabaseMissing('birthday_celebrations', ['id' => $deletedCelebration->id]);
        $this->assertSame(BirthdayCelebration::PUBLISHED, $remainingCelebration->fresh()->status);
        Storage::disk('local')->assertExists('cards/remaining-delete.png');
    }

    public function test_hard_member_delete_removes_birthday_messages_sent_to_that_recipient(): void
    {
        $recipient = $this->member('Deleted Digest Recipient');
        $celebrant = $this->member('Remaining Digest Celebrant');
        $celebration = $this->celebration($celebrant, BirthdayCelebration::PUBLISHED);
        $message = InboxMessage::query()->create([
            'title' => 'Today\'s birthdays',
            'content' => 'Celebrate Remaining Digest Celebrant.',
            'recipient_mode' => 'selected',
            'selected_mobile_user_ids' => [$recipient->id],
        ]);
        $delivery = BirthdayDelivery::query()->create([
            'mobile_user_id' => $recipient->id,
            'kind' => 'digest',
            'dedupe_key' => 'deleted-digest-recipient',
            'inbox_message_id' => $message->id,
        ]);
        $delivery->celebrations()->attach($celebration->id);

        $recipient->delete();

        $this->assertDatabaseMissing('birthday_celebration_deliveries', ['id' => $delivery->id]);
        $this->assertDatabaseMissing('inbox_messages', ['id' => $message->id]);
        $this->assertSame(BirthdayCelebration::PUBLISHED, $celebration->fresh()->status);
    }

    public function test_oversized_profile_image_is_rejected_before_decoding_and_card_falls_back_to_initial(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is required for image handling.');
        }

        Storage::fake('local');
        Storage::fake('public');
        config(['church-birthday-celebrations.media.max_pixels' => 1]);
        $member = $this->member('Fallback Card');
        $avatar = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($avatar);
        Storage::disk('public')->put('avatars/oversized-compressed.png', (string) ob_get_clean());
        imagedestroy($avatar);
        $member->forceFill(['avatar' => 'avatars/oversized-compressed.png'])->saveQuietly();
        $celebration = $this->celebration($member, BirthdayCelebration::PREVIEW_READY);

        $photo = new \ReflectionMethod(BirthdayCardService::class, 'photo');
        $this->assertNull($photo->invoke(app(BirthdayCardService::class), $member));
        app(BirthdayCardService::class)->generate($celebration, $member, null, null);

        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", Storage::disk('local')->get($celebration->fresh()->card_path));
    }

    public function test_card_generator_outputs_real_png_variants_and_records_template_version(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('Local PHP does not provide GD; production must run this test under PHP 8.4 with ext-gd.');
        }

        Storage::fake('local');
        Storage::fake('public');
        $member = $this->member('Card Member');
        $avatar = imagecreatetruecolor(120, 120);
        imagefill($avatar, 0, 0, imagecolorallocate($avatar, 120, 40, 80));
        ob_start();
        imagejpeg($avatar, null, 90);
        Storage::disk('public')->put('avatars/card-member.jpg', (string) ob_get_clean().'EXIF_PRIVATE_MARKER');
        imagedestroy($avatar);
        $member->forceFill(['avatar' => 'avatars/card-member.jpg'])->saveQuietly();
        $preference = BirthdayPreference::query()->create(['mobile_user_id' => $member->id, 'use_profile_photo' => true]);
        $template = BirthdayTemplate::query()->create(['name' => 'Portrait', 'version' => 4, 'is_active' => true]);
        $celebration = $this->celebration($member, BirthdayCelebration::PREVIEW_READY);

        app(BirthdayCardService::class)->generate($celebration, $member, $preference, $template);
        $fresh = $celebration->fresh();
        foreach (['square' => [1080, 1080], 'portrait' => [1080, 1350]] as $variant => $dimensions) {
            $bytes = Storage::disk('local')->get(data_get($fresh->metadata, "card_variants.{$variant}"));
            $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $bytes);
            $this->assertSame($dimensions, array_slice(getimagesizefromstring($bytes), 0, 2));
            $this->assertStringNotContainsString('EXIF_PRIVATE_MARKER', $bytes);
        }
        $this->assertSame($template->id, $fresh->template_id);
        $this->assertSame(4, data_get($fresh->metadata, 'template_version'));
    }

    public function test_failed_delivery_retries_without_creating_a_second_inbox_message_and_uses_private_birthday_routing(): void
    {
        $deliveryService = Mockery::mock(InboxMessageDeliveryService::class);
        $deliveryService->shouldReceive('dispatch')->twice()->andReturn(
            ['push' => ['sent' => 0, 'failed' => 1, 'error' => 'temporary'], 'email' => []],
            ['push' => ['sent' => 1, 'failed' => 0, 'error' => null], 'email' => []],
        );
        $this->app->instance(InboxMessageDeliveryService::class, $deliveryService);
        $member = $this->member('Retry Member');
        $celebration = $this->celebration($member, BirthdayCelebration::PUBLISHED);
        $notifier = app(BirthdayNotifier::class);

        $notifier->send($member, $celebration, 'celebrant', 'retry-once', 'Happy Birthday', 'Your celebration is ready.');
        $this->assertDatabaseHas('birthday_celebration_deliveries', ['dedupe_key' => 'retry-once', 'status' => 'failed', 'attempts' => 1]);
        $notifier->send($member, $celebration, 'celebrant', 'retry-once', 'Happy Birthday', 'Your celebration is ready.');

        $delivery = BirthdayDelivery::query()->where('dedupe_key', 'retry-once')->firstOrFail();
        $message = InboxMessage::query()->findOrFail($delivery->inbox_message_id);
        $this->assertSame('sent', $delivery->status);
        $this->assertSame(2, $delivery->attempts);
        $this->assertDatabaseCount('inbox_messages', 1);
        $this->assertSame('church_birthday_celebrations', $message->push_action);
        $this->assertSame('PRIVATE', $message->push_visibility);
        $this->assertSame($celebration->public_id, $message->push_data['celebration_id']);
    }

    public function test_scheduler_recovers_missed_previews_and_applies_validated_february_29_policy(): void
    {
        $now = CarbonImmutable::parse('2026-08-01 09:00:00', 'Africa/Lagos');
        CarbonImmutable::setTestNow($now);
        $member = $this->member('Recovered Preview', ['birthday_month' => 8, 'birthday_day' => 5]);
        $cards = Mockery::mock(BirthdayCardService::class);
        $cards->shouldReceive('generate')->once()->andReturnUsing(function (BirthdayCelebration $celebration): BirthdayCelebration {
            return tap($celebration)->forceFill(['card_path' => 'generated.png', 'card_disk' => 'local', 'card_mime' => 'image/png'])->save();
        });
        $notifier = Mockery::mock(BirthdayNotifier::class);
        $notifier->shouldReceive('send')->atLeast()->once();
        $notifier->shouldReceive('retryFailed')->once();

        $this->lifecycle($cards, $notifier)->run();
        $preview = BirthdayCelebration::query()->where('mobile_user_id', $member->id)->firstOrFail();
        $this->assertSame('2026-08-05', $preview->birthday_date->toDateString());
        $this->assertSame(BirthdayCelebration::PREVIEW_READY, $preview->status);

        $leapMember = $this->member('Leap Member', ['birthday_month' => 2, 'birthday_day' => 29]);
        $eligibility = new BirthdayEligibilityService();
        BirthdaySetting::put('feb_29_policy', 'march_1');
        $this->assertTrue($eligibility->occursOn($leapMember, CarbonImmutable::parse('2027-03-01')));
        $this->assertFalse($eligibility->occursOn($leapMember, CarbonImmutable::parse('2027-02-28')));
        BirthdaySetting::put('feb_29_policy', 'invalid');
        $this->assertTrue($eligibility->occursOn($leapMember, CarbonImmutable::parse('2027-02-28')));
    }

    public function test_closed_recovery_and_stable_state_codes_are_permission_bound(): void
    {
        $owner = $this->member('Closed Owner');
        $ordinary = $this->member('Ordinary Viewer');
        $staff = $this->member('Recovery Staff');
        Permission::findOrCreate('church_birthday_celebrations.recover', 'mobile');
        $staff->givePermissionTo('church_birthday_celebrations.recover');
        $closed = $this->celebration($owner, BirthdayCelebration::CLOSED, ['purge_due_at' => now()->addDay()]);

        $this->withToken($owner->issueApiToken())->getJson("/api/v1/church-birthday-celebrations/celebrations/{$closed->public_id}")->assertOk();
        $this->withToken($ordinary->issueApiToken())->getJson("/api/v1/church-birthday-celebrations/celebrations/{$closed->public_id}")
            ->assertStatus(409)->assertJsonPath('code', 'CELEBRATION_CLOSED');
        $this->withToken($staff->issueApiToken())->getJson("/api/v1/church-birthday-celebrations/celebrations/{$closed->public_id}")->assertOk();

        $published = $this->celebration($owner, BirthdayCelebration::PUBLISHED, [
            'birthday_year' => 2027,
            'birthday_date' => '2027-07-30',
            'card_path' => null,
        ]);
        $this->withToken($owner->issueApiToken())->getJson("/api/v1/church-birthday-celebrations/celebrations/{$published->public_id}/card")
            ->assertStatus(404)->assertJsonPath('code', 'MEDIA_UNAVAILABLE');

        BirthdayPreference::query()->updateOrCreate(['mobile_user_id' => $owner->id], ['greetings_enabled' => false]);
        $this->withToken($ordinary->issueApiToken())->putJson("/api/v1/church-birthday-celebrations/celebrations/{$published->public_id}/greeting", [
            'body' => 'Happy birthday.',
        ])->assertStatus(409)->assertJsonPath('code', 'GREETINGS_DISABLED');
    }

    public function test_greeting_safeguards_hold_spam_and_report_threshold_hides_without_deleting_audit(): void
    {
        config(['church-birthday-celebrations.report_threshold' => 3]);
        $celebrant = $this->member('Guarded Celebrant');
        $celebration = $this->celebration($celebrant, BirthdayCelebration::PUBLISHED);
        $interactions = app(BirthdayInteractionService::class);
        $linkAuthor = $this->member('Link Author');
        $held = $interactions->greet($celebration, $linkAuthor, 'Visit https://spam.example now');
        $this->assertSame('held', $held->status);

        $author = $this->member('Safe Author');
        $greeting = $interactions->greet($celebration, $author, 'May God bless your new year.');
        $reporters = [$this->member('Reporter One'), $this->member('Reporter Two'), $this->member('Reporter Three')];
        foreach ($reporters as $reporter) {
            $interactions->report($greeting, $reporter, 'Please review this greeting.');
        }
        $this->assertSame('hidden', $greeting->fresh()->status);
        $this->assertDatabaseCount('birthday_celebration_reports', 3);

        try {
            $interactions->report($greeting, $reporters[0], 'Duplicate report.');
            $this->fail('Expected a duplicate-report response.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(409, $exception->getResponse()->getStatusCode());
            $this->assertSame('REPORT_ALREADY_SUBMITTED', $exception->getResponse()->getData(true)['code']);
        }
    }

    private function lifecycle(BirthdayCardService $cards, BirthdayNotifier $notifier): BirthdayLifecycleService
    {
        $availability = Mockery::mock(AddonAvailability::class);
        $availability->shouldReceive('isActive')->andReturn(true);

        return new BirthdayLifecycleService($availability, new BirthdayEligibilityService(), $cards, $notifier);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function member(string $name, array $overrides = []): MobileUser
    {
        $number = MobileUser::query()->count() + 1;

        return MobileUser::query()->create(array_replace([
            'name' => $name,
            'email' => "birthday-production-{$number}@example.test",
            'password' => 'secret',
            'member_type' => 'church_member',
            'is_verified' => true,
            'is_blocked' => false,
            'is_deleted' => false,
            'triumphant_id' => "TB{$number}",
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
            'closes_at' => $status === BirthdayCelebration::PUBLISHED ? now()->addHour() : null,
            'purge_due_at' => $status === BirthdayCelebration::CLOSED ? now()->addDays(30) : null,
        ], $overrides));
    }
}
