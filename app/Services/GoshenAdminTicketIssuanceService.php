<?php

namespace App\Services;

use App\Models\MobileUser;
use App\Models\User;
use App\Models\WebWalletVerificationChallenge;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\TicketStatus;
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
        private readonly GoshenVoucherService $vouchers,
        private readonly LinkedMobileAccountService $linkedAccounts,
        private readonly WebWalletVerificationService $walletVerification,
        private readonly GoshenAdminWalletPaymentService $walletPayments,
        private readonly WalletSecurityResetService $walletSecurityResets,
    ) {
    }

    /**
     * @return array<string, int|string>
     */
    public function verificationContext(
        MobileUser $member,
        EventTicketType $ticketType,
        string $reason,
    ): array {
        return [
            'recipient_id' => (int) $member->getKey(),
            'event_id' => (int) $ticketType->event_id,
            'ticket_type_id' => (int) $ticketType->getKey(),
            'amount' => number_format(round((float) $ticketType->price, 2), 2, '.', ''),
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
    ): Ticket {
        $reason = trim($reason);
        $paymentMethod = strtolower(trim($paymentMethod));

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

        $payer = $this->linkedAccounts->forAdmin($admin);

        if ($paymentMethod === 'wallet') {
            $preflightMember = MobileUser::query()->findOrFail($member->getKey());
            $preflightTicketType = EventTicketType::query()
                ->with('event')
                ->findOrFail($ticketType->getKey());
            $this->assertIssuanceIsValid($preflightMember, $preflightTicketType);
            $this->assertWalletRequestCanProceed($admin, $payer, $challenge, $walletCode);
            $this->walletVerification->consume(
                $challenge,
                $admin,
                $payer,
                'admin_ticket_issue',
                $this->verificationContext($member, $ticketType, $reason),
                trim((string) $walletCode),
                $ip,
                $userAgent,
            );
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
            ): Ticket {
                $member = MobileUser::query()->lockForUpdate()->findOrFail($member->getKey());
                $ticketType = EventTicketType::query()
                    ->with('event')
                    ->lockForUpdate()
                    ->findOrFail($ticketType->getKey());

                $this->assertIssuanceIsValid($member, $ticketType);

                $listPrice = round((float) $ticketType->price, 2);
                $currency = strtoupper((string) $ticketType->currency);
                $metadata = [
                    'source' => 'filament_admin',
                    'issued_by_admin_id' => $admin->id,
                    'beneficiary_mobile_user_id' => $member->id,
                    'payment_method' => $paymentMethod,
                    'issuance_reason' => $reason,
                ];

                $booking = Booking::query()->create([
                    'event_id' => $ticketType->event_id,
                    'payment_plan_id' => null,
                    'customer_id' => $member->id,
                    'customer_name' => $member->name,
                    'customer_email' => $member->email,
                    'customer_phone' => $member->phone,
                    'currency' => $currency,
                    'subtotal' => $listPrice,
                    'total' => $listPrice,
                    'paid_total' => 0,
                    'status' => BookingStatus::Pending,
                    'metadata' => $metadata,
                ]);

                BookingLine::query()->create([
                    'booking_id' => $booking->id,
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => 1,
                    'currency' => $currency,
                    'unit_price' => $listPrice,
                    'line_total' => $listPrice,
                    'metadata' => ['source' => 'filament_admin_ticket_issue'],
                ]);

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
                    ], fn ($value): bool => filled($value)),
                ]);

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
                    );
                    $paymentReferences = [
                        'payer_mobile_user_id' => $payer->id,
                        'wallet_id' => $wallet->id,
                    ];
                }

                $ticket = $booking->tickets()
                    ->where('attendee_id', $attendee->id)
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

    private function assertIssuanceIsValid(MobileUser $member, EventTicketType $ticketType): void
    {
        if (! $member->canUseCommunity()) {
            throw ValidationException::withMessages([
                'customer_id' => 'Tickets can only be issued to active verified app members.',
            ]);
        }

        if (! $ticketType->is_active || ! $ticketType->event || $ticketType->event->status !== 'published') {
            throw ValidationException::withMessages([
                'ticket_type_id' => 'Select an active ticket type for an available retreat edition.',
            ]);
        }

        if (round((float) $ticketType->price, 2) <= 0) {
            throw ValidationException::withMessages([
                'ticket_type_id' => 'Admin-issued tickets must have a positive listed price.',
            ]);
        }

        $duplicateExists = Ticket::query()
            ->where('event_id', $ticketType->event_id)
            ->where('ticket_type_id', $ticketType->id)
            ->where('status', '!=', TicketStatus::Cancelled->value)
            ->whereHas('booking', fn ($query) => $query->where('customer_id', $member->id))
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'customer_id' => 'This member already has this ticket type for the selected retreat edition.',
            ]);
        }
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
