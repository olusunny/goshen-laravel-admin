<?php

use App\Models\ContentPage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->pages() as $type => $page) {
            ContentPage::query()->updateOrCreate(
                ['type' => $type],
                [
                    'title' => $page['title'],
                    'slug' => $type,
                    'body' => $page['body'],
                    'is_published' => true,
                ],
            );
        }

        $this->putWebReturnUrl('stripe_event_success_url', url('/app/payments?checkout=success&session_id={CHECKOUT_SESSION_ID}'));
        $this->putWebReturnUrl('stripe_event_cancel_url', url('/app/payments?checkout=cancelled'));
        $this->putWebReturnUrl('stripe_wallet_success_url', url('/app/wallet?wallet=success&session_id={CHECKOUT_SESSION_ID}'));
        $this->putWebReturnUrl('stripe_wallet_cancel_url', url('/app/wallet?wallet=cancelled'));
        $this->putWebReturnUrl('stripe_giving_success_url', url('/app?giving=success&session_id={CHECKOUT_SESSION_ID}'));
        $this->putWebReturnUrl('stripe_giving_cancel_url', url('/app?giving=cancelled'));
    }

    public function down(): void
    {
        // Content pages are editable records and should not be deleted on rollback.
    }

    private function putWebReturnUrl(string $key, string $url): void
    {
        $setting = DB::table('app_settings')->where('key', $key)->first();
        $current = trim((string) ($setting->value ?? ''));

        if ($current !== '' && ! str_starts_with($current, 'covenantofmercy://')) {
            return;
        }

        $values = [
            'group' => 'payments',
            'value' => $url,
            'is_secret' => false,
            'updated_at' => now(),
        ];

        if ($setting) {
            DB::table('app_settings')->where('id', $setting->id)->update($values);

            return;
        }

        DB::table('app_settings')->insert([
            'key' => $key,
            'created_at' => now(),
            ...$values,
        ]);
    }

    private function pages(): array
    {
        return [
            'privacy' => [
                'title' => 'Privacy Policy',
                'body' => <<<'HTML'
<p><strong>Last updated: July 7, 2026.</strong></p>
<p>MFM Triumphant Church uses the Goshen Retreat web and mobile apps to manage church communication, retreat registration, ticketing, payments, prayer resources, and member services.</p>
<h2>Information we collect</h2>
<p>We may collect your name, title, contact details, country of residence, church group, registration details, attendee details, payment references, wallet activity, device notification token, survey responses, prayer wall submissions, and support messages.</p>
<h2>How we use information</h2>
<ul>
<li>To create and manage your app account.</li>
<li>To process Goshen Retreat registrations, tickets, QR codes, payments, receipts, and support requests.</li>
<li>To send important church, retreat, devotional, prayer point, and account notifications.</li>
<li>To protect accounts, prevent fraud, and keep payment and wallet records accurate.</li>
<li>To improve church services and respond to your enquiries.</li>
</ul>
<h2>Payments and wallet records</h2>
<p>Card payments are handled by payment gateway providers such as Stripe. We do not store your full card number. We store payment references, status, amount, currency, receipt, and wallet ledger information needed for support, accounting, and reconciliation.</p>
<h2>Sharing information</h2>
<p>We share information only with trusted service providers where needed to run the app, deliver notifications, process payments, host content, support registrations, comply with law, or protect the church and app users.</p>
<h2>UK data protection</h2>
<p>Where UK data protection law applies, we process personal information for legitimate church administration, consent-based communications, contract/payment administration, legal obligations, and safeguarding or security needs.</p>
<h2>Your choices</h2>
<p>You can update your profile in the app, manage notification preferences where available, and contact support to request help with your account, data correction, or account access.</p>
<h2>Contact</h2>
<p>For privacy questions, use the app support page or contact the church administration team.</p>
HTML,
            ],
            'terms' => [
                'title' => 'Terms and Conditions',
                'body' => <<<'HTML'
<p><strong>Last updated: July 7, 2026.</strong></p>
<p>These Terms and Conditions apply when you use the Goshen Retreat web portal, Flutter mobile app, and related church services provided by MFM Triumphant Church.</p>
<h2>Account responsibility</h2>
<p>You are responsible for keeping your sign-in details secure and for providing accurate profile, registration, attendee, and payment information. Contact support immediately if you believe your account has been accessed without permission.</p>
<h2>Registrations, tickets, and QR codes</h2>
<p>Tickets and QR codes are issued for eligible registrations only. You must not copy, sell, misuse, or share tickets in a way that prevents proper check-in or attendance management.</p>
<h2>Payments and refunds</h2>
<p>Payments may be processed by Stripe or another approved payment provider. A payment is treated as complete only when the app or payment provider confirms it. Refunds, cancellations, vouchers, and wallet movements are handled according to the published event or church administration policy.</p>
<h2>Wallet services</h2>
<p>Wallet top-ups, transfers, withdrawals, and auto top-ups must be used lawfully and only by the authorised account holder. The church may review, pause, reject, or reverse wallet activity where fraud, error, duplicate payment, failed settlement, or misuse is suspected.</p>
<h2>Community features</h2>
<p>Prayer wall, surveys, testimonials, quiz, comments, and other submissions must be respectful, lawful, and appropriate for a church community. We may moderate, remove, or restrict content where needed.</p>
<h2>Availability</h2>
<p>We work to keep the app available, but services can be interrupted by maintenance, network issues, third-party providers, or security events. We may update or change app features over time.</p>
<h2>Acceptance</h2>
<p>By using the app or web portal, you agree to these terms and to any additional event-specific instructions shown during registration or payment.</p>
HTML,
            ],
            'about' => [
                'title' => 'Support',
                'body' => <<<'HTML'
<p>Need help with Goshen Retreat, payments, wallet, tickets, registration, or your app account? The church support team can help you review your account and resolve app-related issues.</p>
<h2>Before contacting support</h2>
<ul>
<li>Check that your email address and phone number are correct on your profile.</li>
<li>Keep your payment reference, ticket number, or Triumphant ID available.</li>
<li>For wallet security or forgotten PIN support, be ready for account verification.</li>
</ul>
<h2>How to get help</h2>
<p>Use the Contact Us or Support section in the app, or speak with the church administration team during church or retreat support hours.</p>
<h2>Payment support</h2>
<p>If a card payment has completed but the app has not updated yet, allow a short time for payment confirmation. If it remains unresolved, contact support with your payment email and reference.</p>
HTML,
            ],
        ];
    }
};
