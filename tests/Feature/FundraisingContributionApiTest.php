<?php

namespace Tests\Feature;

use App\Models\GoshenWallet;
use App\Models\GoshenWalletLedgerEntry;
use App\Models\MobileUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Sunny\Fundraising\Models\Campaign;
use Sunny\Fundraising\Models\CampaignContribution;
use Sunny\Fundraising\Services\ContributionService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FundraisingContributionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_fundraising_debits_once_and_is_idempotent(): void
    {
        $member = $this->member('supporter@example.test', 'Campaign Supporter');
        $token = $member->issueApiToken();
        $wallet = $this->wallet($member, 100);
        $campaign = $this->campaign();

        $payload = [
            'data' => [
                'amount' => 25,
                'message' => 'Happy to support',
                'anonymous' => false,
                'idempotency_key' => 'fundraising-wallet-key',
            ],
        ];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/fundraising/campaigns/{$campaign->slug}/contribute", $payload)
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('idempotent_replay', false)
            ->assertJsonPath('wallet.balance', 75);

        $contribution = CampaignContribution::query()->firstOrFail();
        $this->assertSame(CampaignContribution::STATUS_SUCCEEDED, $contribution->status);
        $this->assertSame('wallet', $contribution->payment_provider);
        $this->assertNotNull($contribution->wallet_transaction_id);
        $this->assertSame('75.00', $wallet->fresh()->balance);
        $this->assertSame('25.00', $campaign->fresh()->raised_amount);
        $this->assertSame(1, GoshenWalletLedgerEntry::query()->where('type', 'fundraising_payment')->count());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/fundraising/campaigns/{$campaign->slug}/contribute", $payload)
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('idempotent_replay', true);

        $this->assertSame('75.00', $wallet->fresh()->balance);
        $this->assertSame(1, CampaignContribution::query()->count());
        $this->assertSame(1, GoshenWalletLedgerEntry::query()->where('type', 'fundraising_payment')->count());
    }

    public function test_reused_wallet_idempotency_key_with_different_details_is_rejected(): void
    {
        $member = $this->member('supporter@example.test', 'Campaign Supporter');
        $token = $member->issueApiToken();
        $wallet = $this->wallet($member, 100);
        $campaign = $this->campaign();

        $base = [
            'anonymous' => false,
            'idempotency_key' => 'fundraising-wallet-key',
        ];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/fundraising/campaigns/{$campaign->slug}/contribute", [
                'data' => $base + ['amount' => 25],
            ])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/fundraising/campaigns/{$campaign->slug}/contribute", [
                'data' => $base + ['amount' => 30],
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertSame('75.00', $wallet->fresh()->balance);
        $this->assertSame(1, CampaignContribution::query()->count());
        $this->assertSame(1, GoshenWalletLedgerEntry::query()->where('type', 'fundraising_payment')->count());
    }

    public function test_stripe_checkout_requires_configured_gateway(): void
    {
        config([
            'services.stripe.secret' => '',
            'services.stripe.webhook_secret' => '',
            'fundraising.payments.stripe.success_url' => '',
            'fundraising.payments.stripe.cancel_url' => '',
        ]);

        $member = $this->member('supporter@example.test', 'Campaign Supporter');
        $token = $member->issueApiToken();
        $campaign = $this->campaign();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/fundraising/campaigns/{$campaign->slug}/checkout", [
                'data' => [
                    'amount' => 25,
                    'anonymous' => false,
                    'idempotency_key' => 'fundraising-card-key',
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_stripe_webhook_settles_pending_contribution_and_campaign_totals(): void
    {
        $member = $this->member('supporter@example.test', 'Campaign Supporter');
        $campaign = $this->campaign();

        $contribution = CampaignContribution::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $member->id,
            'user_type' => get_class($member),
            'amount' => 25,
            'currency' => 'GBP',
            'status' => CampaignContribution::STATUS_PENDING,
            'payment_provider' => 'stripe',
            'provider_reference' => 'fund_test_reference',
            'is_anonymous' => false,
            'display_name' => $member->name,
            'metadata' => ['payment_provider' => 'stripe'],
        ]);

        app(ContributionService::class)->settleStripeWebhook([
            'id' => 'evt_fundraising_paid',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_fundraising',
                    'client_reference_id' => 'fund_test_reference',
                    'payment_status' => 'paid',
                    'currency' => 'gbp',
                    'amount_total' => 2500,
                    'payment_intent' => 'pi_test_fundraising',
                ],
            ],
        ]);

        $this->assertSame(CampaignContribution::STATUS_SUCCEEDED, $contribution->fresh()->status);
        $this->assertNotNull($contribution->fresh()->succeeded_at);
        $this->assertSame('25.00', $campaign->fresh()->raised_amount);
        $this->assertSame(1, $campaign->fresh()->donor_count);
    }

    public function test_event_manager_can_view_fundraising_management_summary(): void
    {
        $manager = $this->member('fundraising-manager@example.test', 'Fundraising Manager');
        $member = $this->member('regular-supporter@example.test', 'Regular Supporter');
        Role::findOrCreate('event_manager', 'mobile');
        $manager->assignRole('event_manager');

        $campaign = $this->campaign();
        $campaign->forceFill([
            'raised_amount' => 55,
            'donor_count' => 2,
        ])->save();

        CampaignContribution::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $member->id,
            'user_type' => get_class($member),
            'amount' => 25,
            'currency' => 'GBP',
            'status' => CampaignContribution::STATUS_SUCCEEDED,
            'payment_provider' => 'wallet',
            'display_name' => $member->name,
            'succeeded_at' => now(),
        ]);

        CampaignContribution::query()->create([
            'campaign_id' => $campaign->id,
            'amount' => 30,
            'currency' => 'GBP',
            'status' => CampaignContribution::STATUS_SUCCEEDED,
            'payment_provider' => 'stripe',
            'display_name' => 'Card Supporter',
            'succeeded_at' => now(),
        ]);

        CampaignContribution::query()->create([
            'campaign_id' => $campaign->id,
            'amount' => 10,
            'currency' => 'GBP',
            'status' => CampaignContribution::STATUS_PENDING,
            'payment_provider' => 'stripe',
            'display_name' => 'Pending Supporter',
        ]);

        $this->postJson('/api/fundraising/management/summary', [
            'data' => ['api_token' => $member->issueApiToken()],
        ])
            ->assertForbidden()
            ->assertJsonPath('status', 'error');

        $response = $this->postJson('/api/fundraising/management/summary', [
            'data' => ['api_token' => $manager->issueApiToken()],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.totals.campaigns', 1)
            ->assertJsonPath('data.totals.active_campaigns', 1)
            ->assertJsonPath('data.totals.raised_amount', 55)
            ->assertJsonPath('data.totals.all_time_raised_amount', 55)
            ->assertJsonPath('data.totals.wallet_amount', 25)
            ->assertJsonPath('data.totals.stripe_amount', 30)
            ->assertJsonPath('data.totals.pending_amount', 10)
            ->assertJsonPath('data.totals.succeeded_contributions', 2)
            ->assertJsonPath('data.totals.pending_contributions', 1);

        $providers = collect($response->json('data.breakdowns.payment_provider'))->pluck('count', 'key');
        $statuses = collect($response->json('data.breakdowns.contribution_status'))->pluck('count', 'key');

        $this->assertSame(1, $providers->get('wallet'));
        $this->assertSame(1, $providers->get('stripe'));
        $this->assertSame(2, $statuses->get(CampaignContribution::STATUS_SUCCEEDED));
        $this->assertSame(1, $statuses->get(CampaignContribution::STATUS_PENDING));
        $this->assertCount(1, $response->json('data.campaigns'));
        $this->assertCount(3, $response->json('data.recent_contributions'));
    }

    public function test_event_manager_can_update_fundraising_campaign_status(): void
    {
        $manager = $this->member('campaign-manager@example.test', 'Campaign Manager');
        $member = $this->member('campaign-member@example.test', 'Campaign Member');
        Role::findOrCreate('event_manager', 'mobile');
        $manager->assignRole('event_manager');
        $campaign = $this->campaign();

        $this->postJson("/api/fundraising/management/campaigns/{$campaign->slug}/status", [
            'data' => [
                'api_token' => $member->issueApiToken(),
                'status' => Campaign::STATUS_PAUSED,
            ],
        ])
            ->assertForbidden()
            ->assertJsonPath('status', 'error');

        $managerToken = $manager->issueApiToken();

        $this->postJson("/api/fundraising/management/campaigns/{$campaign->slug}/status", [
            'data' => [
                'api_token' => $managerToken,
                'status' => Campaign::STATUS_PAUSED,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('campaign.status', Campaign::STATUS_PAUSED)
            ->assertJsonPath('campaign.available_actions.0', Campaign::STATUS_ACTIVE);

        $this->assertSame(Campaign::STATUS_PAUSED, $campaign->fresh()->status);

        $this->postJson("/api/fundraising/management/campaigns/{$campaign->id}/status", [
            'data' => [
                'api_token' => $managerToken,
                'status' => Campaign::STATUS_ACTIVE,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('campaign.status', Campaign::STATUS_ACTIVE);

        $this->postJson("/api/fundraising/management/campaigns/{$campaign->slug}/status", [
            'data' => [
                'api_token' => $managerToken,
                'status' => Campaign::STATUS_CLOSED,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('campaign.status', Campaign::STATUS_CLOSED);

        $this->assertSame(Campaign::STATUS_CLOSED, $campaign->fresh()->status);
    }

    private function member(string $email, string $name): MobileUser
    {
        return MobileUser::query()->create([
            'name' => $name,
            'email' => $email,
            'phone' => '+447700900'.random_int(100, 999),
            'password' => 'secret',
            'login_type' => 'email',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function wallet(MobileUser $member, float $balance): GoshenWallet
    {
        return GoshenWallet::query()->create([
            'mobile_user_id' => $member->id,
            'currency' => 'GBP',
            'balance' => $balance,
        ]);
    }

    private function campaign(): Campaign
    {
        return Campaign::query()->create([
            'slug' => 'church-building-project',
            'title' => 'Church Building Project',
            'cause' => 'Building',
            'short_description' => 'Support the building project.',
            'description' => 'Support the building project.',
            'goal_amount' => 10000,
            'currency' => 'GBP',
            'status' => Campaign::STATUS_ACTIVE,
            'start_at' => now()->subDay(),
            'end_at' => now()->addMonth(),
        ]);
    }
}
