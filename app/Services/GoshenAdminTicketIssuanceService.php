<?php

namespace App\Services;

use App\Models\MobileUser;
use App\Models\User;
use App\Models\WebWalletVerificationChallenge;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\BookingLine;
use Personal\EventInstallments\Models\EventAuditLog;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\Ticket;
use RuntimeException;

class GoshenAdminTicketIssuanceService
{
    public function __construct(
        private readonly GoshenSingleFullPaymentService $fullPayments,
        private readonly GoshenRegistrationAvailabilityService $availability,
        private readonly GoshenVoucherService $vouchers,
        private readonly LinkedMobileAccountService $linkedAccounts,
        private readonly WebWalletVerificationService $walletVerification,
        private readonly GoshenAdminWalletPaymentService $walletPayments,
        private readonly WalletSecurityResetService $walletSecurityResets,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function verificationContext(
        MobileUser $member,
        EventTicketType $ticketType,
        string $reason,
        int $attendeeQuantity = 1,
    ): array {
        $attendeeQuantity = $this->normalizedAttendeeQuantity($ticketType, $attendeeQuantity);
        $total = round((float) $ticketType->price * $attendeeQuantity, 2);

        return [
            'recipient_id' => (int) $member->getKey(),
            'recipient_context_hash' => hash('sha256', json_encode([
                'id' => (int) $member->getKey(),
                'email' => strtolower(trim((string) $member->email)),
                'verified' => (bool) $member->is_verified,
                'blocked' => (bool) $member->is_blocked,
                'deleted' => (bool) $member->is_deleted,
            ], JSON_THROW_ON_ERROR)),
            'event_id' => (int) $ticketType->event_id,
            'event_context_hash' => hash('sha256', json_encode([
                'id' => (int) $ticketType->event_id,
                'status' => (string) $ticketType->event?->status,
            ], JSON_THROW_ON_ERROR)),
            'ticket_type_id' => (int) $ticketType->getKey(),
            'attendee_quantity' => $attendeeQuantity,
            'amount' => number_format($total, 2, '.', ''),
            'currency' => strtoupper((string) $ticketType->currency),
            'payment_method' => 'wallet',
            'issuance_reason_hash' => hash('sha256', trim($reason)),
        ];
    }

    public function issue(
        MobileUser $member,
        EventTicketType $ticketType,
        User $admin,
        string $reason,
        string $paymentMethod,
        ?string $voucherCode = null,
        ?WebWalletVerificationChallenge $challenge = null,
        ?string $walletCode = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?float $paymentAmount = null,
        array $extraMetadata = [],
        int $attendeeQuantity = 1,
    ): Ticket {
        $reason = trim($reason);
        $paymentMethod = strtolower(trim($paymentMethod));
        $attendeeQuantity = $this->validatedAttendeeQuantity($ticketType, $attendeeQuantity);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'issuance_reason' => 'Enter a reason for issuing this ticket.',
            ]);
        }

        if (! in_array($paymentMethod, ['voucher', 'wallet'], true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Select voucher or wallet payment.',
            ]);
        }

        if ($paymentMethod === 'voucher' && blank($voucherCode)) {
            throw ValidationException::withMessages([
                'voucher_code' => 'Enter a Goshen voucher code.',
            ]);
        }

        $admin = User::query()->findOrFail($admin->getKey());
        $payer = null;
        $authorizedWalletContext = null;

        if ($paymentMethod === 'wallet') {
            [$preflightMember, $preflightTicketType] = $this->availability
                ->lockAndAssertAvailable($member, $ticketType, $attendeeQuantity);
            $this->assertPositiveListPrice($preflightTicketType);
            $authorizedWalletContext = $this->verificationContext(
                $preflightMember,
                $preflightTicketType,
                $reason,
                $attendeeQuantity,
            );
            $payer = $this->linkedAccounts->forAdmin($admin);
            $this->assertWalletRequestCanProceed($admin, $payer, $challenge, $walletCode);
            $this->walletVerification->consume(
                $challenge,
                $admin,
                $payer,
                'admin_ticket_issue',
                $authorizedWalletContext,
                trim((string) $walletCode),
                $ip,
                $userAgent,
            );
        } else {
            $payer = $this->linkedAccounts->forAdmin($admin);
        }

        try {
            return DB::transaction(function () use (
                $member,
                $ticketType,
                $admin,
                $reason,
                $paymentMethod,
                $voucherCode,
                $payer,
                $authorizedWalletContext,
                $paymentAmount,
                $extraMetadata,
                $attendeeQuantity,
            ): Ticket {
                if ($paymentMethod === 'wallet') {
                    $admin = User::query()->whereKey($admin->id)->lockForUpdate()->firstOrFail();
                }

                [$member, $ticketType] = $this->availability
                    ->lockAndAssertAvailable($member, $ticketType, $attendeeQuantity);
                $this->assertPositiveListPrice($ticketType);

                if ($paymentMethod === 'wallet') {
                    $currentContext = $this->verificationContext($member, $ticketType, $reason, $attendeeQuantity);
                    if (! hash_equals(
                        $this->walletVerification->fingerprint($authorizedWalletContext ?? []),
                        $this->walletVerification->fingerprint($currentContext),
                    )) {
                        throw ValidationException::withMessages([
                            'wallet_otp' => 'The ticket request changed after wallet verification. Request a new code.',
                        ]);
                    }
                }

                $listPrice = round((float) $ticketType->price, 2);
                $listedTotal = round($listPrice * $attendeeQuantity, 2);
                $paymentTotal = $paymentAmount === null
                    ? $listedTotal
                    : round((float) $paymentAmount, 2);
                if ($paymentTotal <= 0) {
                    throw ValidationException::withMessages([
                        'payment_amount' => 'The payment amount must be greater than zero.',
                    ]);
                }

                $currency = strtoupper((string) $ticketType->currency);
                $metadata = array_filter(array_merge([
                    'source' => 'filament_admin',
                    'issued_by_admin_id' => $admin->id,
                    'beneficiary_mobile_user_id' => $member->id,
                    'payment_method' => $paymentMethod,
                    'issuance_reason' => $reason,
                    'listed_ticket_price' => $listPrice,
                    'attendee_quantity' => $attendeeQuantity,
                    'listed_ticket_total' => $listedTotal,
                    'amount_paid' => $paymentTotal,
                    'historical_paid_amount' => $paymentAmount === null ? null : $paymentTotal,
                    'historical_discount_amount' => $paymentAmount === null ? null : max(0, round($listedTotal - $paymentTotal, 2)),
                ], $extraMetadata), fn ($value): bool => $value !== null && $value !== '');

                $booking = Booking::query()->create([
                    'event_id' => $ticketType->event_id,
                    'payment_plan_id' => null,
                    'customer_id' => $member->id,
                    'customer_name' => $member->name,
                    'customer_email' => $member->email,
                    'customer_phone' => $member->phone,
                    'currency' => $currency,
                    'subtotal' => $paymentTotal,
                    'total' => $paymentTotal,
                    'paid_total' => 0,
                    'status' => BookingStatus::Pending,
                    'metadata' => $metadata,
                ]);

                BookingLine::query()->create([
                    'booking_id' => $booking->id,
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => $attendeeQuantity,
                    'currency' => $currency,
                    'unit_price' => $paymentAmount === null
                        ? $listPrice
                        : round($paymentTotal / $attendeeQuantity, 2),
                    'line_total' => $paymentTotal,
                    'metadata' => [
                        'source' => 'filament_admin_ticket_issue',
                        'listed_ticket_price' => $listPrice,
                        'listed_ticket_total' => $listedTotal,
                        'amount_paid' => $paymentTotal,
                        'attendee_quantity' => $attendeeQuantity,
                    ],
                ]);

                $firstAttendee = null;
                for ($index = 0; $index < $attendeeQuantity; $index++) {
                    $attendee = Attendee::query()->create([
                        'booking_id' => $booking->id,
                        'ticket_type_id' => $ticketType->id,
                        'first_name' => $member->first_name ?: $member->name,
                        'last_name' => $member->last_name,
                        'email' => $member->email,
                        'phone' => $member->phone,
                        'custom_fields' => array_filter([
                            'title' => $member->title,
                            'gender' => $member->gender,
                            'marital_status' => $member->marital_status,
                            'source' => 'filament_admin_ticket_issue',
                            'attendee_index' => $index + 1,
                            'admin_issued_family_quantity' => $attendeeQuantity > 1 ? $attendeeQuantity : null,
                        ], fn ($value): bool => filled($value)),
                    ]);

                    $firstAttendee ??= $attendee;
                }

                $fullPayment = $this->fullPayments->createForBooking($booking);
                $paymentReferences = [];

                if ($paymentMethod === 'voucher') {
                    $usage = $this->vouchers->redeemForBooking(
                        $booking,
                        $fullPayment,
                        (string) $voucherCode,
                        $member,
                        $payer,
                        'filament_admin_ticket_issue',
                        $admin,
                        [
                            'request_ip' => request()->ip(),
                            'request_user_agent' => request()->userAgent(),
                        ],
                    );
                    $transaction = $usage->paymentTransaction()->firstOrFail();
                    $paymentReferences = [
                        'voucher_usage_id' => $usage->id,
                        'voucher_code_suffix' => $usage->code_suffix,
                    ];
                } else {
                    $wallet = $payer?->wallet()->firstOrFail();
                    $transaction = $this->walletPayments->settle(
                        $booking,
                        $fullPayment,
                        $wallet,
                        $payer,
                        $member,
                        $admin,
                        [
                            'request_ip' => request()->ip(),
                            'request_user_agent' => request()->userAgent(),
                        ],
                    );
                    $paymentReferences = [
                        'payer_mobile_user_id' => $payer->id,
                        'wallet_id' => $wallet->id,
                    ];
                }

                $ticket = $booking->tickets()
                    ->where('attendee_id', $firstAttendee?->id)
                    ->first();

                if (! $ticket instanceof Ticket) {
                    throw new RuntimeException('The paid ticket could not be issued.');
                }

                $safePaymentMetadata = array_merge($metadata, $paymentReferences, [
                    'payment_transaction_id' => $transaction->id,
                    'payment_reference' => $transaction->provider_reference,
                ]);

                $booking->forceFill([
                    'metadata' => array_merge($booking->fresh()->metadata ?? [], $safePaymentMetadata),
                ])->save();
                $ticket->forceFill([
                    'metadata' => array_merge($ticket->metadata ?? [], $safePaymentMetadata),
                ])->save();

                EventAuditLog::query()->create([
                    'event_id' => $ticketType->event_id,
                    'actor_id' => $admin->id,
                    'action' => 'admin_ticket_issued',
                    'auditable_type' => $ticket::class,
                    'auditable_id' => $ticket->id,
                    'after' => [
                        'ticket_public_id' => $ticket->public_id,
                        'member_id' => $member->id,
                        'ticket_type_id' => $ticketType->id,
                        'attendee_quantity' => $attendeeQuantity,
                        'booking_id' => $booking->id,
                        'payment_transaction_id' => $transaction->id,
                    ],
                    'metadata' => $safePaymentMetadata,
                ]);

                return $ticket->fresh(['booking', 'attendee', 'ticketType', 'event']);
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            $field = $paymentMethod === 'voucher' ? 'voucher_code' : 'payment_method';

            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }
    }

    private function assertPositiveListPrice(EventTicketType $ticketType): void
    {
        if (round((float) $ticketType->price, 2) <= 0) {
            throw ValidationException::withMessages([
                'ticket_type_id' => 'Admin-issued tickets must have a positive listed price.',
            ]);
        }
    }

    private function validatedAttendeeQuantity(EventTicketType $ticketType, int $quantity): int
    {
        $quantity = max(1, $quantity);
        $min = max(1, (int) ($ticketType->min_per_booking ?: 1));
        $max = (int) ($ticketType->max_per_booking ?: 0);

        if ($quantity < $min) {
            throw ValidationException::withMessages([
                'attendee_quantity' => "Please issue at least {$min} attendee(s) for this ticket type.",
            ]);
        }

        if ($max > 0 && $quantity > $max) {
            throw ValidationException::withMessages([
                'attendee_quantity' => "You can only issue up to {$max} attendee(s) for this ticket type at once.",
            ]);
        }

        return $quantity;
    }

    private function normalizedAttendeeQuantity(EventTicketType $ticketType, int $quantity): int
    {
        $quantity = max(1, $quantity);
        $min = max(1, (int) ($ticketType->min_per_booking ?: 1));
        $max = (int) ($ticketType->max_per_booking ?: 0);
        $quantity = max($quantity, $min);

        return $max > 0 ? min($quantity, $max) : $quantity;
    }

    private function assertWalletRequestCanProceed(
        User $admin,
        ?MobileUser $payer,
        ?WebWalletVerificationChallenge $challenge,
        ?string $walletCode,
    ): void {
        $adminEmail = strtolower(trim((string) $admin->email));
        $payerEmail = strtolower(trim((string) $payer?->email));

        if (! $payer || $adminEmail === '' || $adminEmail !== $payerEmail || ! $payer->canUseCommunity()) {
            throw ValidationException::withMessages([
                'payment_method' => 'Your linked wallet account could not be verified.',
            ]);
        }

        if (! $payer->wallet()->exists()) {
            throw ValidationException::withMessages([
                'payment_method' => 'Your linked wallet is not available.',
            ]);
        }

        try {
            $this->walletSecurityResets->assertWalletActionsAllowed($payer);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['payment_method' => $exception->getMessage()]);
        }

        if (! $challenge || ! preg_match('/^\d{6}$/', trim((string) $walletCode))) {
            throw ValidationException::withMessages([
                'wallet_otp' => 'Enter the six-digit wallet verification code.',
            ]);
        }
    }
}
