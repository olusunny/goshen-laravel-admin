<?php

namespace Personal\EventInstallments\Http\Controllers\Api;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Personal\EventInstallments\Contracts\PaymentGateway;
use Personal\EventInstallments\Data\GatewayCheckout;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Http\Requests\StoreBookingRequest;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\BookingLine;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentPlan;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Services\PaymentPlanService;
use Personal\EventInstallments\Services\TicketIssuer;

class BookingController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreBookingRequest $request, PaymentPlanService $paymentPlans, TicketIssuer $ticketIssuer)
    {
        return DB::transaction(function () use ($request, $paymentPlans) {
            $event = Event::query()->where('public_id', $request->input('event_id'))->firstOrFail();
            $plan = $request->filled('payment_plan_id')
                ? PaymentPlan::query()->where('event_id', $event->id)->where('public_id', $request->input('payment_plan_id'))->firstOrFail()
                : $event->paymentPlans()->where('is_active', true)->first();

            $ticketTypes = EventTicketType::query()
                ->where('event_id', $event->id)
                ->whereIn('public_id', collect($request->input('lines'))->pluck('ticket_type_id'))
                ->get()
                ->keyBy('public_id');

            $subtotal = 0.0;
            foreach ($request->input('lines') as $line) {
                $ticketType = $ticketTypes->get($line['ticket_type_id']);
                abort_unless($ticketType, 422, 'Invalid ticket type.');
                $subtotal += (float) $ticketType->price * (int) $line['quantity'];
            }

            $isFreeRegistration = $subtotal <= 0;

            $booking = Booking::query()->create([
                'event_id' => $event->id,
                'payment_plan_id' => $plan?->id,
                'customer_id' => $request->user()?->getAuthIdentifier(),
                'customer_name' => $request->input('customer_name'),
                'customer_email' => $request->input('customer_email'),
                'customer_phone' => $request->input('customer_phone'),
                'currency' => $plan?->currency ?: config('event-installments.payments.currency'),
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'status' => $isFreeRegistration ? BookingStatus::Paid : BookingStatus::Pending,
                'metadata' => $request->input('metadata', []),
            ]);

            foreach ($request->input('lines') as $line) {
                $ticketType = $ticketTypes->get($line['ticket_type_id']);
                BookingLine::query()->create([
                    'booking_id' => $booking->id,
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => $line['quantity'],
                    'currency' => $booking->currency,
                    'unit_price' => $ticketType->price,
                    'line_total' => (float) $ticketType->price * (int) $line['quantity'],
                ]);
            }

            foreach ($request->input('attendees') as $attendee) {
                $ticketType = $ticketTypes->get($attendee['ticket_type_id']);
                abort_unless($ticketType, 422, 'Invalid attendee ticket type.');
                Attendee::query()->create([
                    'booking_id' => $booking->id,
                    'ticket_type_id' => $ticketType->id,
                    'first_name' => $attendee['first_name'] ?? null,
                    'last_name' => $attendee['last_name'] ?? null,
                    'email' => $attendee['email'] ?? null,
                    'phone' => $attendee['phone'] ?? null,
                    'company' => $attendee['company'] ?? null,
                    'designation' => $attendee['designation'] ?? null,
                    'custom_fields' => $attendee['custom_fields'] ?? [],
                ]);
            }

            if ($plan && ! $isFreeRegistration) {
                $paymentPlans->createInstallments($booking, $plan);
            }

            if ($isFreeRegistration) {
                $ticketIssuer->issueForBooking($booking->refresh());
            }

            return $booking->load(['event', 'lines.ticketType', 'attendees', 'installments', 'tickets']);
        });
    }

    public function show(Booking $booking)
    {
        $this->authorize('view', $booking);

        return $booking->load(['event', 'lines.ticketType', 'attendees', 'installments', 'tickets']);
    }

    public function checkout(Booking $booking, PaymentInstallment $installment, PaymentGateway $gateway)
    {
        $this->authorize('checkout', $booking);
        abort_unless($installment->booking_id === $booking->id, 404);

        $bookingStatus = $booking->status instanceof BookingStatus ? $booking->status->value : (string) $booking->status;
        abort_if(in_array($bookingStatus, [BookingStatus::Paid->value, BookingStatus::Cancelled->value, BookingStatus::Refunded->value], true), 422, 'This booking is not open for payment.');

        $installmentStatus = $installment->status instanceof InstallmentStatus ? $installment->status->value : (string) $installment->status;
        abort_if(in_array($installmentStatus, [InstallmentStatus::Paid->value, InstallmentStatus::Cancelled->value, InstallmentStatus::Refunded->value], true), 422, 'This installment is not open for payment.');

        if ($existingCheckout = $this->activeCheckoutFor($installment)) {
            return $existingCheckout;
        }

        $checkout = $gateway->createCheckout($installment);

        PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'installment_id' => $installment->id,
            'gateway' => $checkout->gateway,
            'provider_reference' => $checkout->reference,
            'currency' => $installment->currency,
            'amount' => $installment->amount,
            'status' => 'pending',
            'payload' => $checkout->payload,
        ]);

        return $checkout;
    }

    private function activeCheckoutFor(PaymentInstallment $installment): ?GatewayCheckout
    {
        $transactions = $installment->transactions()
            ->where('status', 'pending')
            ->latest('id')
            ->get();

        foreach ($transactions as $transaction) {
            $expiresAt = data_get($transaction->payload, 'expires_at');
            $checkoutUrl = data_get($transaction->payload, 'url');

            if ($expiresAt && now()->timestamp >= (int) $expiresAt) {
                $transaction->forceFill([
                    'status' => 'expired',
                    'payload' => array_filter(array_merge(
                        $transaction->payload ?: [],
                        ['expired_locally_at' => now()->toIso8601String()],
                    )),
                ])->save();

                continue;
            }

            if (is_string($checkoutUrl) && $checkoutUrl !== '') {
                return new GatewayCheckout(
                    gateway: (string) $transaction->gateway,
                    reference: (string) $transaction->provider_reference,
                    checkoutUrl: $checkoutUrl,
                    payload: $transaction->payload ?: [],
                );
            }
        }

        return null;
    }
}
