<?php

namespace Tests\Feature;

use App\Filament\Resources\DonationResource;
use App\Models\Donation;
use App\Models\DonationCategory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DonationAdminImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_donation_cannot_be_edited_or_deleted_by_super_admin(): void
    {
        $admin = $this->superAdmin();
        $paidDonation = $this->donation('paid', now(), 'locked-paid-reference');
        $pendingDonation = $this->donation('pending', null, 'pending-reference');

        $this->actingAs($admin);

        $this->assertFalse(DonationResource::canEdit($paidDonation));
        $this->assertFalse(DonationResource::canDelete($paidDonation));
        $this->assertFalse(DonationResource::canDeleteAny());
        $this->assertTrue(DonationResource::canEdit($pendingDonation));
        $this->assertTrue(DonationResource::canDelete($pendingDonation));

        $this->get('/admin/donations/'.$paidDonation->id)
            ->assertOk()
            ->assertSee('locked-paid-reference');

        $this->get('/admin/donations/'.$paidDonation->id.'/edit')
            ->assertForbidden();
    }

    public function test_completed_donation_policy_denies_update_and_delete_for_super_admin(): void
    {
        $admin = $this->superAdmin();
        $paidDonation = $this->donation('paid', now(), 'locked-policy-paid-reference');
        $pendingDonation = $this->donation('pending', null, 'locked-policy-pending-reference');

        $this->assertFalse(Gate::forUser($admin)->allows('update', $paidDonation));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $paidDonation));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $pendingDonation));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $pendingDonation));
    }

    public function test_pending_donation_can_be_marked_completed_once(): void
    {
        $donation = $this->donation('pending', null, 'pending-to-paid-reference');

        $donation->forceFill([
            'status' => 'paid',
            'paid_at' => now(),
        ])->save();

        $this->assertTrue($donation->fresh()->isCompleted());
    }

    public function test_completed_donation_cannot_be_updated_directly(): void
    {
        $donation = $this->donation('paid', now(), 'locked-update-reference');

        try {
            $donation->forceFill(['amount' => 999])->save();
            $this->fail('Completed donation update was not blocked.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(Donation::completedLockMessage(), $exception->getMessage());
        }

        $this->assertSame('20.00', (string) $donation->fresh()->amount);
    }

    public function test_completed_donation_cannot_be_updated_with_query_builder(): void
    {
        $donation = $this->donation('paid', now(), 'locked-query-update-reference');

        try {
            DB::table('donations')
                ->where('id', $donation->id)
                ->update(['amount' => 999]);

            $this->fail('Completed donation query-builder update was not blocked.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString(Donation::completedLockMessage(), $exception->getMessage());
        }

        $this->assertSame('20.00', (string) $donation->fresh()->amount);
    }

    public function test_completed_donation_cannot_be_deleted_directly(): void
    {
        $donation = $this->donation('paid', now(), 'locked-delete-reference');

        try {
            $donation->delete();
            $this->fail('Completed donation delete was not blocked.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(Donation::completedLockMessage(), $exception->getMessage());
        }

        $this->assertTrue($donation->fresh()->exists);
    }

    public function test_completed_donation_cannot_be_deleted_with_query_builder(): void
    {
        $donation = $this->donation('paid', now(), 'locked-query-delete-reference');

        try {
            DB::table('donations')
                ->where('id', $donation->id)
                ->delete();

            $this->fail('Completed donation query-builder delete was not blocked.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString(Donation::completedLockMessage(), $exception->getMessage());
        }

        $this->assertTrue($donation->fresh()->exists);
    }

    public function test_stripe_webhook_acknowledges_completed_donation_without_editing_it(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_completed_lock']);

        $donation = $this->donation('paid', now(), 'locked-webhook-reference', [
            'provider' => 'stripe',
            'metadata' => [
                'source' => 'test',
                'stripe_last_event_id' => 'evt_original',
            ],
        ]);

        $event = [
            'id' => 'evt_after_completion',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'client_reference_id' => $donation->reference,
                    'currency' => strtolower($donation->currency),
                    'amount' => 2000,
                    'metadata' => [
                        'donation_reference' => $donation->reference,
                    ],
                ],
            ],
        ];

        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_completed_lock');

        $this->withHeaders(['Stripe-Signature' => $signature])
            ->postJson('/api/giving/stripe/webhook', $event)
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'message' => 'Completed donation is locked.',
            ]);

        $this->assertSame('evt_original', $donation->fresh()->metadata['stripe_last_event_id']);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        return $admin;
    }

    private function donation(string $status, mixed $paidAt, string $reference, array $overrides = []): Donation
    {
        $category = DonationCategory::query()->where('slug', 'offering')->firstOrFail();

        return Donation::query()->create(array_merge([
            'name' => 'Test Giver',
            'email' => $reference.'@example.test',
            'phone' => '+447700900123',
            'donation_category_id' => $category->id,
            'purpose' => 'Offering',
            'amount' => 20,
            'currency' => 'GBP',
            'provider' => 'wallet',
            'reference' => $reference,
            'status' => $status,
            'paid_at' => $paidAt,
        ], $overrides));
    }
}
