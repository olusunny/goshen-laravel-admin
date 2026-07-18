<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\DonationStripeController;
use App\Models\Donation;
use App\Models\DonationCategory;
use App\Models\MobileUser;
use App\Services\StripePaymentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonationStripeCheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_create_stripe_giving_checkout(): void
    {
        $this->fakeStripeGateway();
        $category = $this->category();

        $this->postJson('/api/giving/stripe/checkout', [
            'data' => [
                'amount' => 12.34,
                'currency' => 'GBP',
                'donation_category_id' => $category->id,
                'name' => 'Guest Visitor',
                'email' => 'guest@example.test',
                'phone' => '+447700900123',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checkout.checkout_url', 'https://stripe.test/checkout/cs_test_1');

        $donation = Donation::query()->firstOrFail();

        $this->assertSame('stripe', $donation->provider);
        $this->assertSame('pending', $donation->status);
        $this->assertSame('Guest Visitor', $donation->name);
        $this->assertSame('guest@example.test', $donation->email);
        $this->assertSame('+447700900123', $donation->phone);
        $this->assertSame($category->id, $donation->donation_category_id);
        $this->assertFalse($donation->metadata['anonymous']);
        $this->assertSame('visitor', $donation->metadata['giver_type']);
        $this->assertArrayNotHasKey('mobile_user_id', $donation->metadata);
        $this->assertSame('cs_test_1', $donation->metadata['stripe_checkout_session_id']);

        $checkout = FakeDonationStripeController::$checkoutSessions[0];
        $this->assertSame('guest@example.test', $checkout['payload']['customer_email']);
        $this->assertSame(1234, $checkout['payload']['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame($donation->reference, $checkout['payload']['metadata']['donation_reference']);
        $this->assertSame($donation->reference, $checkout['options']['idempotency_key']);
    }

    public function test_guest_stripe_checkout_requires_contact_details(): void
    {
        $this->fakeStripeGateway();
        $category = $this->category();

        $this->postJson('/api/giving/stripe/checkout', [
            'data' => [
                'amount' => 25,
                'currency' => 'GBP',
                'category_slug' => $category->slug,
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'phone']);

        $this->assertDatabaseCount('donations', 0);
        $this->assertCount(0, FakeDonationStripeController::$checkoutSessions);
    }

    public function test_anonymous_flag_is_ignored_for_stripe_checkout(): void
    {
        $this->fakeStripeGateway();
        $category = $this->category();

        $this->postJson('/api/giving/stripe/checkout', [
            'data' => [
                'amount' => 25,
                'currency' => 'GBP',
                'category_slug' => $category->slug,
                'name' => 'Captured Visitor',
                'email' => 'captured@example.test',
                'phone' => '+447700900999',
                'anonymous' => true,
            ],
        ])->assertOk();

        $donation = Donation::query()->firstOrFail();

        $this->assertSame('Captured Visitor', $donation->name);
        $this->assertSame('captured@example.test', $donation->email);
        $this->assertSame('+447700900999', $donation->phone);
        $this->assertFalse($donation->metadata['anonymous']);
        $this->assertSame('visitor', $donation->metadata['giver_type']);
        $this->assertArrayNotHasKey('mobile_user_id', $donation->metadata);
    }

    public function test_authenticated_stripe_checkout_keeps_member_metadata_and_contact_fallbacks(): void
    {
        $this->fakeStripeGateway();
        $member = $this->member();
        $token = $member->issueApiToken();
        $category = $this->category();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/giving/stripe/checkout', [
                'data' => [
                    'amount' => 30,
                    'currency' => 'GBP',
                    'donation_category_id' => $category->id,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $donation = Donation::query()->firstOrFail();

        $this->assertSame('Stripe Member', $donation->name);
        $this->assertSame('stripe-member@example.test', $donation->email);
        $this->assertSame('+447700900456', $donation->phone);
        $this->assertSame($member->id, $donation->metadata['mobile_user_id']);
        $this->assertSame('member', $donation->metadata['giver_type']);
        $this->assertFalse($donation->metadata['anonymous']);

        $checkout = FakeDonationStripeController::$checkoutSessions[0];
        $this->assertSame('stripe-member@example.test', $checkout['payload']['customer_email']);
    }

    public function test_mobile_giving_checkout_can_request_app_return_urls(): void
    {
        $this->fakeStripeGateway();
        $member = $this->member();
        $token = $member->issueApiToken();
        $category = $this->category();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/giving/stripe/checkout', [
                'data' => [
                    'amount' => 30,
                    'currency' => 'GBP',
                    'donation_category_id' => $category->id,
                    'return_to_app' => true,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $checkout = FakeDonationStripeController::$checkoutSessions[0];

        $this->assertSame(
            'triumphant://goshen-payment/success?flow=giving&session_id={CHECKOUT_SESSION_ID}',
            $checkout['payload']['success_url'],
        );
        $this->assertSame(
            'triumphant://goshen-payment/cancelled?flow=giving',
            $checkout['payload']['cancel_url'],
        );
    }

    private function fakeStripeGateway(): void
    {
        config([
            'services.stripe.secret' => 'sk_test_giving',
            'services.stripe.webhook_secret' => 'whsec_giving',
            'services.stripe.success_url' => 'https://example.test/giving/success',
            'services.stripe.cancel_url' => 'https://example.test/giving/cancel',
        ]);

        FakeDonationStripeController::$checkoutSessions = [];

        $this->app->bind(DonationStripeController::class, FakeDonationStripeController::class);
    }

    private function category(): DonationCategory
    {
        return DonationCategory::query()->where('slug', 'offering')->firstOrFail();
    }

    private function member(): MobileUser
    {
        return MobileUser::query()->create([
            'name' => 'Stripe Member',
            'email' => 'stripe-member@example.test',
            'phone' => '+447700900456',
            'password' => 'secret',
            'login_type' => 'email',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }
}

class FakeDonationStripeController extends DonationStripeController
{
    public static array $checkoutSessions = [];

    protected function createCheckoutSession(array $payload, array $options): array
    {
        self::$checkoutSessions[] = [
            'payload' => $payload,
            'options' => $options,
        ];

        $id = 'cs_test_' . count(self::$checkoutSessions);

        return [
            'id' => $id,
            'url' => 'https://stripe.test/checkout/' . $id,
        ];
    }
}
