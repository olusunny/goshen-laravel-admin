<?php

namespace App\Services;

use App\Models\AccommodationBooking;
use App\Models\AccommodationPayment;
use App\Models\AppSetting;
use App\Models\InboxMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class AccommodationNotificationService
{
    public function bookingCreated(AccommodationBooking $booking): void
    {
        $booking = $this->withRelations($booking);
        if ($booking->booking_created_notified_at || ! $booking->user) {
            return;
        }

        $subject = 'Accommodation booking received: ' . $booking->booking_reference;
        $body = $this->bookingMessage($booking, [
            'intro' => "Hello {$this->guestName($booking)}, your accommodation booking has been received.",
            'next' => 'Please complete your payment from the app to confirm the booking. If you need help, contact the booking support team below.',
        ]);

        $this->notifyGuest($booking, $subject, $body, 'booking_created');
        $booking->forceFill(['booking_created_notified_at' => now()])->save();
    }

    public function paymentPending(AccommodationBooking $booking, ?AccommodationPayment $payment = null): void
    {
        $booking = $this->withRelations($booking);
        if ($booking->payment_pending_notified_at || ! $booking->user) {
            return;
        }

        $subject = 'Accommodation payment pending: ' . $booking->booking_reference;
        $body = $this->bookingMessage($booking, [
            'intro' => "Hello {$this->guestName($booking)}, your payment is waiting to be completed.",
            'payment_reference' => $payment?->paystack_reference,
            'next' => 'Kindly continue the payment in the app. Your booking remains pending until payment is confirmed.',
        ]);

        $this->notifyGuest($booking, $subject, $body, 'payment_pending');
        $booking->forceFill(['payment_pending_notified_at' => now()])->save();
    }

    public function paymentSuccessful(AccommodationBooking $booking, ?AccommodationPayment $payment = null): void
    {
        $booking = $this->withRelations($booking);
        if ($booking->payment_success_notified_at || ! $booking->user) {
            return;
        }

        $payment ??= $booking->payments()->latest()->first();
        $subject = 'Accommodation payment receipt: ' . $booking->booking_reference;
        $body = $this->bookingMessage($booking, [
            'intro' => "Hello {$this->guestName($booking)}, thank you. Your accommodation payment was successful.",
            'payment_reference' => $payment?->paystack_reference,
            'amount_paid' => $this->money($payment?->amount ?? $booking->total_amount, $payment?->currency ?? $booking->currency),
            'payment_method' => $payment?->channel ?: 'Online payment',
            'payment_date' => $payment?->paid_at?->format('D, M j, Y g:i A') ?: now()->format('D, M j, Y g:i A'),
            'next' => 'Please keep this receipt for your records. You can also view your booking from the accommodation section in the app.',
        ], receipt: true);

        $this->notifyGuest($booking, $subject, $body, 'payment_success', inboxTitle: 'Accommodation payment successful');
        $booking->forceFill(['payment_success_notified_at' => now()])->save();
    }

    public function paymentFailed(AccommodationBooking $booking, ?AccommodationPayment $payment = null): void
    {
        $booking = $this->withRelations($booking);
        if ($booking->payment_failed_notified_at || ! $booking->user) {
            return;
        }

        $subject = 'Accommodation payment update: ' . $booking->booking_reference;
        $body = $this->bookingMessage($booking, [
            'intro' => "Hello {$this->guestName($booking)}, we could not confirm your accommodation payment yet.",
            'payment_reference' => $payment?->paystack_reference,
            'next' => 'Please try again from the app or contact support if your account was debited. We will be glad to help.',
        ]);

        $this->notifyGuest($booking, $subject, $body, 'payment_failed', inboxTitle: 'Accommodation payment needs attention');
        $booking->forceFill(['payment_failed_notified_at' => now()])->save();
    }

    public function bookingConfirmed(AccommodationBooking $booking): void
    {
        $booking = $this->withRelations($booking);
        if ($booking->booking_confirmed_notified_at || ! $booking->user) {
            return;
        }

        $subject = 'Accommodation booking confirmed: ' . $booking->booking_reference;
        $body = $this->bookingMessage($booking, [
            'intro' => "Hello {$this->guestName($booking)}, your accommodation booking is confirmed.",
            'next' => 'Please arrive from the check-in time shown below. Kindly review the rules and contact support if you need assistance before arrival.',
        ], includeRules: true);

        $this->notifyGuest($booking, $subject, $body, 'booking_confirmed', inboxTitle: 'Accommodation booking confirmed');
        $booking->forceFill(['booking_confirmed_notified_at' => now()])->save();
    }

    public function bookingCancelled(AccommodationBooking $booking, ?string $reason = null): void
    {
        $booking = $this->withRelations($booking);
        if ($booking->booking_cancelled_notified_at || ! $booking->user) {
            return;
        }

        $reason = trim((string) ($reason ?: $booking->admin_note ?: 'No reason was added.'));
        $subject = 'Accommodation booking cancelled: ' . $booking->booking_reference;
        $body = $this->bookingMessage($booking, [
            'intro' => "Hello {$this->guestName($booking)}, this is a gentle update that your accommodation booking has been cancelled.",
            'cancellation_reason' => $reason,
            'next' => 'If you need help with another booking or have any payment question, please contact support below.',
        ]);

        $this->notifyGuest($booking, $subject, $body, 'booking_cancelled', inboxTitle: 'Accommodation booking cancelled');
        $booking->forceFill(['booking_cancelled_notified_at' => now()])->save();
    }

    public function checkoutReminder(AccommodationBooking $booking): void
    {
        $booking = $this->withRelations($booking);
        if ($booking->checkout_reminder_sent_at || ! $booking->user) {
            return;
        }

        $subject = 'Friendly checkout reminder: ' . $booking->booking_reference;
        $body = $this->bookingMessage($booking, [
            'intro' => "Hello {$this->guestName($booking)}, we hope you have enjoyed your stay.",
            'next' => "This is a friendly reminder that your checkout time is {$this->checkoutDateTime($booking)}. Kindly prepare your belongings and let us know if you need any assistance. Thank you.",
        ], includeRules: true);

        $this->notifyGuest($booking, $subject, $body, 'checkout_reminder', inboxTitle: 'Friendly checkout reminder');
        $booking->forceFill(['checkout_reminder_sent_at' => now()])->save();
    }

    public function sendDueCheckoutReminders(): int
    {
        $sent = 0;
        $now = CarbonImmutable::now('Africa/Lagos');

        AccommodationBooking::with(['user', 'category', 'unit'])
            ->whereIn('booking_status', ['confirmed', 'checked_in'])
            ->where('payment_status', 'paid')
            ->whereNull('checkout_reminder_sent_at')
            ->whereDate('checkout_date', '<=', $now->copy()->addDay()->toDateString())
            ->get()
            ->each(function (AccommodationBooking $booking) use (&$sent, $now) {
                $checkoutAt = $this->checkoutAt($booking);
                if ($checkoutAt && $checkoutAt->betweenIncluded($now->subMinutes(5), $now->addMinutes(65))) {
                    $this->checkoutReminder($booking);
                    $sent++;
                }
            });

        return $sent;
    }

    private function notifyGuest(AccommodationBooking $booking, string $subject, string $body, string $event, ?string $inboxTitle = null): void
    {
        $user = $booking->user;
        if (! $user) {
            return;
        }

        $html = $this->emailShell($subject, $body);

        if ($user->email) {
            try {
                app(DynamicSmtpMailer::class)->sendHtml($user->email, $subject, $html, $body);
            } catch (\Throwable $exception) {
                Log::warning('Accommodation email notification failed.', [
                    'event' => $event,
                    'booking_id' => $booking->id,
                    'email' => $user->email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $message = InboxMessage::create([
            'title' => $inboxTitle ?: $subject,
            'notification_category' => 'accommodation',
            'content' => $this->inboxHtml($body),
            'send_push' => true,
            'recipient_mode' => 'selected',
            'selected_mobile_user_ids' => [$user->id],
            'is_published' => true,
            'published_at' => now(),
        ]);

        try {
            $result = app(FirebasePushSender::class)->sendInboxMessage($message);
            $message->forceFill([
                'push_sent_count' => $result['sent'] ?? 0,
                'push_failed_count' => $result['failed'] ?? 0,
                'push_last_error' => $result['error'] ?? null,
                'push_sent_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            Log::warning('Accommodation push notification failed.', [
                'event' => $event,
                'booking_id' => $booking->id,
                'inbox_message_id' => $message->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function bookingMessage(AccommodationBooking $booking, array $extra, bool $receipt = false, bool $includeRules = false): string
    {
        $lines = [
            $extra['intro'],
            '',
            'Booking details',
            'Booking reference: ' . $booking->booking_reference,
            'Accommodation: ' . ($booking->category?->name ?? 'Accommodation'),
            'Room/unit: ' . ($booking->unit?->unit_name ?: 'To be assigned'),
            'Check-in: ' . $this->checkInDateTime($booking),
            'Checkout: ' . $this->checkoutDateTime($booking),
            'Occupants: ' . (int) $booking->total_occupants,
            'Adults: ' . (int) $booking->adults,
            'Children: ' . (int) $booking->children,
            'Booking status: ' . $this->label($booking->booking_status),
            'Payment status: ' . $this->label($booking->payment_status),
            'Total amount: ' . $this->money($booking->total_amount, $booking->currency),
        ];

        foreach (['payment_reference', 'amount_paid', 'payment_method', 'payment_date', 'cancellation_reason'] as $key) {
            if (! empty($extra[$key])) {
                $lines[] = str($key)->replace('_', ' ')->title()->toString() . ': ' . $extra[$key];
            }
        }

        if ($receipt) {
            $lines[] = '';
            $lines[] = 'Receipt note: This confirms that we received your accommodation payment for the booking above.';
        }

        if ($includeRules && filled($booking->category?->rules)) {
            $lines[] = '';
            $lines[] = 'Important rules and instructions';
            $lines[] = trim(strip_tags((string) $booking->category->rules));
        }

        $lines[] = '';
        $lines[] = 'Next steps';
        $lines[] = $extra['next'];
        $lines[] = '';
        $lines[] = $this->supportText();

        return implode("\n", $lines);
    }

    private function emailShell(string $title, string $plainText): string
    {
        $paragraphs = collect(preg_split("/\n{2,}/", trim($plainText)))
            ->map(function (string $block) {
                $lines = collect(explode("\n", $block))->map(fn ($line) => e($line))->implode('<br>');
                return "<p>{$lines}</p>";
            })
            ->implode('');

        return '<!doctype html><html><body style="margin:0;background:#f3f7f8;font-family:Arial,sans-serif;color:#0c2230;">'
            . '<div style="max-width:560px;margin:0 auto;padding:24px 14px;">'
            . '<div style="background:#ffffff;border-radius:20px;padding:24px;box-shadow:0 10px 30px rgba(12,34,48,.08);">'
            . '<h1 style="font-size:22px;line-height:1.25;margin:0 0 16px;color:#0c2230;">' . e($title) . '</h1>'
            . '<div style="font-size:15px;line-height:1.65;color:#243947;">' . $paragraphs . '</div>'
            . '</div></div></body></html>';
    }

    private function inboxHtml(string $plainText): string
    {
        $blocks = collect(preg_split("/\n{2,}/", trim($plainText)))
            ->map(function (string $block): string {
                $lines = collect(explode("\n", trim($block)))
                    ->map(fn ($line) => trim($line))
                    ->filter()
                    ->values();

                if ($lines->isEmpty()) {
                    return '';
                }

                $first = $lines->first();
                if (in_array($first, ['Booking details', 'Important rules and instructions', 'Next steps'], true)) {
                    $items = $lines->slice(1)
                        ->map(function (string $line): string {
                            if (str_contains($line, ':')) {
                                [$label, $value] = explode(':', $line, 2);
                                return '<li><strong>' . e(trim($label)) . ':</strong> ' . e(trim($value)) . '</li>';
                            }

                            return '<li>' . e($line) . '</li>';
                        })
                        ->implode('');

                    return '<section><h3>' . e($first) . '</h3><ul>' . $items . '</ul></section>';
                }

                if (str_starts_with($first, 'Support contact:')) {
                    $items = $lines
                        ->map(function (string $line): string {
                            if (str_contains($line, ':')) {
                                [$label, $value] = explode(':', $line, 2);
                                return '<li><strong>' . e(trim($label)) . ':</strong> ' . e(trim($value)) . '</li>';
                            }

                            return '<li>' . e($line) . '</li>';
                        })
                        ->implode('');

                    return '<section><h3>Booking support</h3><ul>' . $items . '</ul></section>';
                }

                return '<p>' . $lines->map(fn ($line) => e($line))->implode('<br>') . '</p>';
            })
            ->filter()
            ->implode('');

        return '<article class="accommodation-notification">' . $blocks . '</article>';
    }

    private function withRelations(AccommodationBooking $booking): AccommodationBooking
    {
        return $booking->loadMissing(['user', 'category', 'unit', 'payments']);
    }

    private function checkoutAt(AccommodationBooking $booking): ?CarbonImmutable
    {
        try {
            $time = trim((string) ($booking->category?->checkout_time ?: '10:00'));
            return CarbonImmutable::parse($booking->checkout_date->toDateString() . ' ' . $time, 'Africa/Lagos');
        } catch (\Throwable) {
            return null;
        }
    }

    private function checkInDateTime(AccommodationBooking $booking): string
    {
        return $this->formatDateTime($booking->check_in_date, $booking->category?->check_in_time ?: 'From 2:00 PM');
    }

    private function checkoutDateTime(AccommodationBooking $booking): string
    {
        return $this->formatDateTime($booking->checkout_date, $booking->category?->checkout_time ?: '10:00 AM');
    }

    private function formatDateTime($date, string $time): string
    {
        return trim(($date?->format('D, M j, Y') ?: 'Date pending') . ' ' . $time);
    }

    private function guestName(AccommodationBooking $booking): string
    {
        return $booking->user?->name ?: 'Beloved';
    }

    private function label(?string $value): string
    {
        return str((string) $value)->replace('_', ' ')->title()->toString();
    }

    private function money($amount, ?string $currency): string
    {
        return trim(($currency ?: 'NGN') . ' ' . number_format((float) $amount, 2));
    }

    private function supportText(): string
    {
        $name = AppSetting::value('accommodation_booking_support_name', 'Accommodation Support');
        $phone = AppSetting::value('accommodation_booking_support_phone', '');
        $email = AppSetting::value('accommodation_booking_support_email', '');
        $whatsapp = AppSetting::value('accommodation_booking_support_whatsapp', '');
        $instructions = AppSetting::value('accommodation_booking_support_instructions', '');

        $parts = ["Support contact: {$name}"];
        if ($phone) {
            $parts[] = "Phone: {$phone}";
        }
        if ($email) {
            $parts[] = "Email: {$email}";
        }
        if ($whatsapp) {
            $parts[] = "WhatsApp: {$whatsapp}";
        }
        if ($instructions) {
            $parts[] = $instructions;
        }

        return implode("\n", $parts);
    }
}
