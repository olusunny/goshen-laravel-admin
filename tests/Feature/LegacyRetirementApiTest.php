<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\AppSetting;
use App\Models\Donation;
use Tests\TestCase;

class LegacyRetirementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_self_service_accommodation_routes_are_retired(): void
    {
        AppSetting::create([
            'group' => 'legacy',
            'key' => 'legacy_accommodation_booking_enabled',
            'value' => '1',
            'description' => 'Regression guard: this retired API must not be re-enabled by settings.',
        ]);

        $retiredPaths = [
            ['getJson', '/accommodations'],
            ['getJson', '/accommodations/1'],
            ['postJson', '/accommodations/1/check-availability'],
            ['postJson', '/accommodations/1/bookings'],
            ['postJson', '/accommodation-bookings/1/initialize-payment'],
            ['postJson', '/accommodation-bookings/1/cancel'],
            ['postJson', '/paystack/accommodation/verify'],
            ['getJson', '/my-accommodation-bookings'],
        ];

        foreach ($retiredPaths as [$method, $path]) {
            $this->{$method}($path, ['data' => []])
                ->assertGone()
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertJsonPath('status', 'retired')
                ->assertJsonPath(
                    'message',
                    'Legacy accommodation booking has been retired. Goshen Retreat accommodation is now assigned by authorized staff after registration and payment.'
                );
        }
    }

    public function test_legacy_manual_donation_creation_and_bank_account_disclosure_are_retired(): void
    {
        $this->seed();

        $payload = [
            'data' => [
                'name' => 'Retired Manual Gift',
                'amount' => 5000,
                'purpose' => 'Offering',
            ],
        ];

        foreach (['/saveDonation', '/api/saveDonation', '/donation_accounts', '/api/donation_accounts'] as $path) {
            $this->postJson($path, $payload)
                ->assertGone()
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertJsonPath('status', 'retired')
                ->assertJsonPath(
                    'message',
                    'Manual donation submission has been retired. Please use the Stripe-powered Giving flow for new gifts.'
                );
        }

        $this->assertSame(0, Donation::count());
    }
}
