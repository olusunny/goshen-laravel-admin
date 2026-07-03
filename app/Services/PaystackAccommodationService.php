<?php

namespace App\Services;

use App\Models\AccommodationBooking;
use App\Models\AccommodationPayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PaystackAccommodationService
{
    public function __construct(private readonly AccommodationNotificationService $notifications)
    {
    }

    public function initialize(AccommodationBooking $booking): array
    {
        $secret = config('services.paystack.secret_key') ?: env('PAYSTACK_SECRET_KEY');
        if (! $secret) {
            throw new RuntimeException('PayStack secret key is not configured.');
        }

        $reference = 'MC-PAY-' . $booking->id . '-' . Str::upper(Str::random(10));
        $response = Http::withToken($secret)->post('https://api.paystack.co/transaction/initialize', [
            'email' => $booking->user->email,
            'amount' => (int) round(((float) $booking->total_amount) * 100),
            'currency' => $booking->currency,
            'reference' => $reference,
            'metadata' => [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
            ],
        ]);

        if (! $response->successful() || ! ($response->json('status') === true)) {
            throw new RuntimeException($response->json('message') ?: 'Unable to initialize PayStack payment.');
        }

        $payment = AccommodationPayment::updateOrCreate(
            ['paystack_reference' => $reference],
            [
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'amount' => $booking->total_amount,
                'currency' => $booking->currency,
                'status' => 'pending',
                'raw_response' => $response->json(),
            ]
        );

        $this->notifications->paymentPending($booking->fresh(['category', 'unit', 'payments', 'user']), $payment);

        return [
            'reference' => $reference,
            'authorization_url' => $response->json('data.authorization_url'),
            'access_code' => $response->json('data.access_code'),
        ];
    }

    public function verify(string $reference): AccommodationBooking
    {
        $secret = config('services.paystack.secret_key') ?: env('PAYSTACK_SECRET_KEY');
        if (! $secret) {
            throw new RuntimeException('PayStack secret key is not configured.');
        }

        $response = Http::withToken($secret)->get("https://api.paystack.co/transaction/verify/{$reference}");
        $payment = AccommodationPayment::where('paystack_reference', $reference)->firstOrFail();
        $data = $response->json('data') ?? [];
        $paid = $response->successful() && $response->json('status') === true && ($data['status'] ?? null) === 'success';

        $payment->forceFill([
            'transaction_id' => $data['id'] ?? null,
            'status' => $paid ? 'paid' : 'failed',
            'channel' => $data['channel'] ?? null,
            'paid_at' => $paid ? now() : null,
            'raw_response' => $response->json(),
        ])->save();

        $booking = $payment->booking;
        if ($paid) {
            $booking->forceFill([
                'payment_status' => 'paid',
                'booking_status' => 'confirmed',
            ])->save();

            $this->notifications->paymentSuccessful($booking->fresh(['category', 'unit', 'payments', 'user']), $payment);
        } else {
            $booking->forceFill(['payment_status' => 'failed'])->save();
            $this->notifications->paymentFailed($booking->fresh(['category', 'unit', 'payments', 'user']), $payment);
        }

        return $booking->fresh(['category', 'unit', 'payments', 'user']);
    }
}
