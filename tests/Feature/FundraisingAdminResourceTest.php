<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Services\Addons\AddonRuntimeLoader;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Sunny\Fundraising\Filament\Resources\CampaignResource;
use Sunny\Fundraising\FundraisingServiceProvider;
use Sunny\Fundraising\Models\Campaign;
use Sunny\Fundraising\Models\CampaignContribution;
use Sunny\Fundraising\Services\CampaignService;
use Tests\TestCase;

class FundraisingAdminResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_fundraising_admin_resources(): void
    {
        $admin = $this->superAdmin();
        $campaign = $this->campaign();

        $this->actingAs($admin);

        $this->get('/admin/fundraising/campaigns')->assertOk();
        $this->get('/admin/fundraising/campaigns/create')->assertOk();
        $this->get('/admin/fundraising/campaigns/'.$campaign->id.'/edit')->assertOk();
        $this->get('/admin/fundraising/media')->assertOk();
        $this->get('/admin/fundraising/media/create')->assertOk();
        $this->get('/admin/fundraising/contributions')->assertOk();
    }

    public function test_plain_authenticated_user_cannot_open_legacy_admin_summary_route(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/fundraising')
            ->assertForbidden();
    }

    public function test_inactive_addon_record_hides_package_admin_resources(): void
    {
        $admin = $this->superAdmin();

        Addon::query()->updateOrCreate(['package_key' => 'sunny.fundraising'], [
            'composer_name' => 'sunny/fundraising',
            'name' => 'Fundraising Campaigns',
            'installed_version' => '1.0.0',
            'status' => Addon::STATUS_INACTIVE,
            'provider_class' => 'Sunny\\Fundraising\\FundraisingServiceProvider',
            'namespace' => 'Sunny\\Fundraising\\',
            'autoload_psr4' => ['Sunny\\Fundraising\\' => 'src/'],
            'manifest' => ['package_key' => 'sunny.fundraising'],
        ]);

        $this->actingAs($admin);

        $this->assertFalse(CampaignResource::canViewAny());
        $this->assertFalse(CampaignResource::shouldRegisterNavigation());
    }

    public function test_inactive_addon_status_disables_package_route_loading_decision(): void
    {
        Addon::query()->updateOrCreate(['package_key' => 'sunny.fundraising'], [
            'composer_name' => 'sunny/fundraising',
            'name' => 'Fundraising Campaigns',
            'installed_version' => '1.0.0',
            'status' => Addon::STATUS_INACTIVE,
            'provider_class' => 'Sunny\\Fundraising\\FundraisingServiceProvider',
            'namespace' => 'Sunny\\Fundraising\\',
            'autoload_psr4' => ['Sunny\\Fundraising\\' => 'src/'],
            'manifest' => ['package_key' => 'sunny.fundraising'],
        ]);

        $provider = new FundraisingServiceProvider($this->app);
        $reflection = new \ReflectionMethod($provider, 'fundraisingEnabled');
        $reflection->setAccessible(true);

        $this->assertFalse($reflection->invoke($provider));
    }

    public function test_runtime_loader_discovers_filament_resources_from_active_uploaded_addon_cache(): void
    {
        $cachePath = storage_path('framework/testing-active-addons.json');
        $installRoot = base_path('addons/testing');
        $installPath = $installRoot.DIRECTORY_SEPARATOR.'sunny.fundraising';
        $resourcePath = $installPath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Filament'.DIRECTORY_SEPARATOR.'Resources';

        config([
            'addons.install_path' => 'addons/testing',
            'addons.runtime_cache_path' => $cachePath,
        ]);
        File::delete($cachePath);
        File::deleteDirectory($installRoot);
        File::ensureDirectoryExists($resourcePath);

        Addon::query()->updateOrCreate(['package_key' => 'sunny.fundraising'], [
            'composer_name' => 'sunny/fundraising',
            'name' => 'Fundraising Campaigns',
            'installed_version' => '1.0.0',
            'status' => Addon::STATUS_ACTIVE,
            'provider_class' => 'Sunny\\Fundraising\\FundraisingServiceProvider',
            'namespace' => 'Sunny\\Fundraising\\',
            'autoload_psr4' => ['Sunny\\Fundraising\\' => 'src/'],
            'manifest' => [
                'package_key' => 'sunny.fundraising',
                'namespace' => 'Sunny\\Fundraising\\',
                'autoload_psr4' => ['Sunny\\Fundraising\\' => 'src/'],
            ],
            'install_path' => $installPath,
            'signature_verified' => true,
        ]);

        File::put($cachePath, json_encode([
            'addons' => [[
                'package_key' => 'sunny.fundraising',
                'install_path' => base_path('not-trusted/sunny.fundraising'),
                'manifest' => [
                    'package_key' => 'sunny.fundraising',
                    'namespace' => 'Evil\\Namespace\\',
                ],
            ]],
        ], JSON_THROW_ON_ERROR));

        $discoveries = app(AddonRuntimeLoader::class)->filamentResourceDiscoveries();

        $this->assertCount(1, $discoveries, json_encode($discoveries, JSON_THROW_ON_ERROR));
        $this->assertSame('sunny.fundraising', $discoveries[0]['package_key']);
        $this->assertSame(realpath($resourcePath), $discoveries[0]['path']);
        $this->assertSame('Sunny\\Fundraising\\Filament\\Resources', $discoveries[0]['namespace']);

        File::deleteDirectory($installRoot);
    }

    public function test_campaign_with_contributions_cannot_be_deleted_from_admin(): void
    {
        $admin = $this->superAdmin();
        $campaign = $this->campaign();

        $this->actingAs($admin);

        $this->assertTrue(CampaignResource::canDelete($campaign));
        $this->assertFalse(CampaignResource::canDeleteAny());

        CampaignContribution::query()->create([
            'campaign_id' => $campaign->id,
            'amount' => 25,
            'currency' => 'GBP',
            'status' => CampaignContribution::STATUS_SUCCEEDED,
            'display_name' => 'Supporter',
            'wallet_transaction_id' => 'wallet_txn_1',
            'succeeded_at' => now(),
        ]);

        $this->assertFalse(CampaignResource::canDelete($campaign->fresh()));
    }

    public function test_succeeded_campaign_contribution_cannot_be_changed_or_deleted(): void
    {
        $campaign = $this->campaign();
        $contribution = CampaignContribution::query()->create([
            'campaign_id' => $campaign->id,
            'amount' => 25,
            'currency' => 'GBP',
            'status' => CampaignContribution::STATUS_SUCCEEDED,
            'display_name' => 'Supporter',
            'wallet_transaction_id' => 'wallet_txn_1',
            'succeeded_at' => now(),
        ]);

        try {
            $contribution->forceFill(['amount' => 30])->save();
            $this->fail('Succeeded contribution update was not blocked.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
            $this->assertSame(CampaignContribution::succeededLockMessage(), $exception->getMessage());
        }

        try {
            $contribution->delete();
            $this->fail('Succeeded contribution delete was not blocked.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
            $this->assertSame(CampaignContribution::succeededLockMessage(), $exception->getMessage());
        }

        $this->assertSame('25.00', (string) $contribution->fresh()->amount);
        $this->assertTrue($contribution->fresh()->exists);
    }

    public function test_succeeded_campaign_contribution_cannot_be_changed_with_query_builder(): void
    {
        $campaign = $this->campaign();
        $contribution = CampaignContribution::query()->create([
            'campaign_id' => $campaign->id,
            'amount' => 25,
            'currency' => 'GBP',
            'status' => CampaignContribution::STATUS_SUCCEEDED,
            'display_name' => 'Supporter',
            'wallet_transaction_id' => 'wallet_txn_1',
            'succeeded_at' => now(),
        ]);

        try {
            DB::table('fundraising_campaign_contributions')
                ->where('id', $contribution->id)
                ->update(['amount' => 30]);

            $this->fail('Succeeded contribution query-builder update was not blocked.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString(
                CampaignContribution::succeededLockMessage(),
                $exception->getMessage(),
            );
        }

        $this->assertSame('25.00', (string) $contribution->fresh()->amount);
    }

    public function test_active_campaign_requires_closing_date(): void
    {
        $this->expectException(ValidationException::class);

        Campaign::query()->create([
            'title' => 'Mission Support',
            'slug' => 'mission-support',
            'cause' => 'Mission support',
            'goal_amount' => 5000,
            'currency' => 'GBP',
            'status' => Campaign::STATUS_ACTIVE,
            'start_at' => now()->subDay(),
            'end_at' => null,
        ]);
    }

    public function test_closing_date_must_be_after_start_date(): void
    {
        $this->expectException(ValidationException::class);

        Campaign::query()->create([
            'title' => 'Building Support',
            'slug' => 'building-support',
            'cause' => 'Building support',
            'goal_amount' => 5000,
            'currency' => 'GBP',
            'status' => Campaign::STATUS_DRAFT,
            'start_at' => now(),
            'end_at' => now()->subHour(),
        ]);
    }

    public function test_publish_now_makes_future_started_campaign_visible_immediately(): void
    {
        $campaign = Campaign::query()->create([
            'title' => 'Building Support',
            'slug' => 'building-support',
            'cause' => 'Building support',
            'goal_amount' => 5000,
            'currency' => 'GBP',
            'status' => Campaign::STATUS_DRAFT,
            'start_at' => now()->addHour(),
            'end_at' => now()->addMonth(),
        ]);

        $this->assertTrue($campaign->publishNow());

        $campaign->refresh();

        $this->assertSame(Campaign::STATUS_ACTIVE, $campaign->status);
        $this->assertTrue($campaign->start_at->lte(now()));
        $this->assertTrue($campaign->canContribute());
    }

    public function test_campaign_currency_symbol_is_normalized_to_iso_code(): void
    {
        $campaign = Campaign::query()->create([
            'title' => 'Chair Support',
            'slug' => 'chair-support',
            'cause' => 'Chair support',
            'goal_amount' => 5000,
            'currency' => '£',
            'status' => Campaign::STATUS_DRAFT,
            'start_at' => now()->subDay(),
            'end_at' => now()->addMonth(),
        ]);

        $this->assertSame('GBP', $campaign->fresh()->currency);
    }

    public function test_campaign_cta_label_is_exposed_in_mobile_payload(): void
    {
        $campaign = Campaign::query()->create([
            'title' => 'Chair Support',
            'slug' => 'chair-support',
            'cause' => 'Chair support',
            'goal_amount' => 5000,
            'currency' => 'GBP',
            'status' => Campaign::STATUS_ACTIVE,
            'start_at' => now()->subDay(),
            'end_at' => now()->addMonth(),
            'metadata' => ['cta_label' => 'Contribute from wallet'],
        ]);

        $payload = app(CampaignService::class)->payload($campaign);

        $this->assertSame('Contribute from wallet', $campaign->ctaLabel());
        $this->assertSame('Contribute from wallet', $payload['campaign']['cta_label']);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        return $admin;
    }

    private function campaign(): Campaign
    {
        return Campaign::query()->create([
            'title' => 'Mission Support',
            'slug' => 'mission-support',
            'cause' => 'Mission support',
            'short_description' => 'A focused campaign for missions.',
            'goal_amount' => 5000,
            'currency' => 'GBP',
            'status' => Campaign::STATUS_DRAFT,
            'start_at' => now()->subDay(),
            'end_at' => now()->addMonth(),
        ]);
    }
}
