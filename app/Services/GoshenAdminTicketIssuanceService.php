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
        ?float $paymentAmount = null,
    ): array {
        $attendeeQuantity = $this->normalizedAttendeeQuantity($ticketType, $attendeeQuantity);
        $listedTotal = round((float) $ticketType->price * $attendeeQuantity, 2);
        $total = $paymentAmount === null
            ? $listedTotal
            : round((float) $paymentAmount, 2);

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
            'listed_amount' => number_format($listedTotal, 2, '.', ''),
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
        array $attendeeDetails = [],
        ?array $memberWalletAuthorization = null,
    ): Ticket {
        $reason = trim($reason);
        $paymentMethod = strtolower(trim($paymentMethod));
        $attendeeQuantity = $this->validatedAttendeeQuantity($ticketType, $attendeeQuantity);
        $attendeeDetails = $this->normalizedAttendeeDetails($member, $attendeeQuantity, $attendeeDetails);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'issuance_reason' => 'Enter a reason for issuing this ticket.',
            ]);
        }

        if (! in_array($paymentMethod, ['voucher', 'wallet', 'member_wallet'], true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Select voucher, your wallet, or the selected member wallet.',
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
        $memberWalletAuthorization = $paymentMethod === 'member_wallet'
            ? $this->validatedMemberWalletAuthorization($memberWalletAuthorization)
            : null;

        if ($paymentMethod === 'wallet') {
            [$preflightMember, $preflightTicketType] = $this->availability
                ->lockAndAssertAvailable($member, $ticketType, $attendeeQuantity);
            $this->assertPositiveListPrice($preflightTicketType);
            $preflightPaymentTotal = $this->paymentTotal($preflightTicketType, $attendeeQuantity, $paymentAmount);
            if ($preflightPaymentTotal <= 0) {
                throw ValidationException::withMessages([
                    'payment_amount' => 'The payment amount must be greater than zero.',
                ]);
            }
            $authorizedWalletContext = $this->verificationContext(
                $preflightMember,
                $preflightTicketType,
                $reason,
                $attendeeQuantity,
                $preflightPaymentTotal,
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
        } elseif ($paymentMethod === 'member_wallet') {
            $payer = $member;
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
                $attendeeDetails,
                $memberWalletAuthorization,
            ): Ticket {
                if ($paymentMethod === 'wallet') {
                    $admin = User::query()->whereKey($admin->id)->lockForUpdate()->firstOrFail();
                }

                [$member, $ticketType] = $this->availability
                    ->lockAndAssertAvailable($member, $ticketType, $attendeeQuantity);
                $this->assertPositiveListPrice($ticketType);

                if ($paymentMethod === 'wallet') {
                    $currentContext = $this->verificationContext($member, $ticketType, $reason, $attendeeQuantity, $paymentAmount);
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
                $paymentTotal = $this->paymentTotal($ticketType, $attendeeQuantity, $paymentAmount);
                if ($paymentTotal <= 0) {
                    throw ValidationException::withMessages([
                        'payment_amount' => 'The payment amount must be greater than zero.',
                    ]);
                }

                $currency = strtoupper((string) $ticketType->currency);
                $source = $paymentMethod === 'member_wallet'
                    ? 'filament_member_wallet_charge'
                    : 'filament_admin';
                $ticketIssueSource = $paymentMethod === 'member_wallet'
                    ? 'filament_member_wallet_charge'
                    : 'filament_admin_ticket_issue';
                $metadata = array_filter(array_merge([
                    'source' => $source,
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
                    'member_wallet_authorization' => $memberWalletAuthorization,
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
                        'source' => $ticketIssueSource,
                        'listed_ticket_price' => $listPrice,
                        'listed_ticket_total' => $listedTotal,
                        'amount_paid' => $paymentTotal,
                        'attendee_quantity' => $attendeeQuantity,
                    ],
                ]);

                $firstAttendee = null;
                for ($index = 0; $index < $attendeeQuantity; $index++) {
                    $attendeeDetail = $attendeeDetails[$index] ?? $this->defaultAttendeeDetails($member);
                    $customFields = is_array($attendeeDetail['custom_fields'] ?? null) ? $attendeeDetail['custom_fields'] : [];

                    $attendee = Attendee::query()->create([
                        'booking_id' => $booking->id,
                        'ticket_type_id' => $ticketType->id,
                        'first_name' => $attendeeDetail['first_name'] ?? ($member->first_name ?: $member->name),
                        'last_name' => $attendeeDetail['last_name'] ?? $member->last_name,
                        'email' => $attendeeDetail['email'] ?? $member->email,
                        'phone' => $attendeeDetail['phone'] ?? $member->phone,
                        'company' => $attendeeDetail['company'] ?? ($customFields['company'] ?? null),
                        'designation' => $attendeeDetail['designation'] ?? ($customFields['designation'] ?? null),
                        'custom_fields' => array_filter([
                            'title' => $member->title,
                            'gender' => $customFields['gender'] ?? $member->gender,
                            'marital_status' => $member->marital_status,
                            ...$customFields,
                            'source' => $ticketIssueSource,
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
                } elseif ($paymentMethod === 'member_wallet') {
                    $wallet = $member->wallet()->firstOrFail();
                    $transaction = $this->walletPayments->settleMemberWallet(
                        $booking,
                        $fullPayment,
                        $wallet,
                        $member,
                        $admin,
                        $memberWalletAuthorization ?? [],
                        [
                            'request_ip' => request()->ip(),
                            'request_user_agent' => request()->userAgent(),
                        ],
                    );
                    $paymentReferences = [
                        'payer_mobile_user_id' => $member->id,
                        'wallet_id' => $wallet->id,
                        'charged_member_wallet_id' => $wallet->id,
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
                    'action' => $paymentMethod === 'member_wallet'
                        ? 'member_wallet_charged_for_ticket'
                        : 'admin_ticket_issued',
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

    /**
     * @param  array<string, mixed>|null  $authorization
     * @return array{confirmed: bool, authorization_method: string, authorization_note: string}
     */
    private function validatedMemberWalletAuthorization(?array $authorization): array
    {
        $method = trim((string) ($authorization['authorization_method'] ?? ''));
        $note = trim((string) ($authorization['authorization_note'] ?? ''));

        if (! filter_var($authorization['confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw ValidationException::withMessages([
                'member_authorization_confirmed' => 'Confirm that the member authorized this charge from their own wallet.',
            ]);
        }

        if (! in_array($method, ['registered_contact', 'in_person', 'church_record', 'other_verified_process'], true)) {
            throw ValidationException::withMessages([
                'member_authorization_method' => 'Select how the member authorization was verified.',
            ]);
        }

        if (str($note)->length() < 20) {
            throw ValidationException::withMessages([
                'member_authorization_note' => 'Record a meaningful member authorization note of at least 20 characters.',
            ]);
        }

        return [
            'confirmed' => true,
            'authorization_method' => $method,
            'authorization_note' => $note,
        ];
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

    /**
     * @param  array<int, array<string, mixed>>  $attendeeDetails
     * @return array<int, array<string, mixed>>
     */
    private function normalizedAttendeeDetails(MobileUser $member, int $attendeeQuantity, array $attendeeDetails): array
    {
        if ($attendeeDetails === []) {
            return [];
        }

        $attendeeDetails = collect($attendeeDetails)->values()->all();

        if (count($attendeeDetails) !== $attendeeQuantity) {
            throw ValidationException::withMessages([
                'attendees' => "Enter details for exactly {$attendeeQuantity} attendee(s).",
            ]);
        }

        $defaults = $this->defaultAttendeeDetails($member);
        $normalized = [];

        foreach ($attendeeDetails as $index => $detail) {
            $detail = is_array($detail) ? $detail : [];
            $detail = array_merge($index === 0 ? $defaults : [], $detail);
            $firstName = trim((string) ($detail['first_name'] ?? ''));

            if ($firstName === '') {
                throw ValidationException::withMessages([
                    "attendees.{$index}.first_name" => 'Enter the attendee first name.',
                ]);
            }

            $customInput = is_array($detail['custom_fields'] ?? null) ? $detail['custom_fields'] : [];

            foreach ($detail as $key => $value) {
                if (! is_string($key) || ! str_starts_with($key, 'custom_fields.')) {
                    continue;
                }

                data_set($customInput, substr($key, strlen('custom_fields.')), $value);
            }

            $customFields = collect($customInput)
                ->filter(fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== '')
                ->map(fn (mixed $value): string => trim((string) $value))
                ->all();

            foreach (['company', 'designation'] as $legacyKey) {
                if (filled($customFields[$legacyKey] ?? null)) {
                    $detail[$legacyKey] = $customFields[$legacyKey];
                }
            }

            $normalized[] = [
                'first_name' => $firstName,
                'last_name' => trim((string) ($detail['last_name'] ?? '')),
                'email' => trim((string) ($detail['email'] ?? '')),
                'phone' => trim((string) ($detail['phone'] ?? '')),
                'company' => trim((string) ($detail['company'] ?? '')),
                'designation' => trim((string) ($detail['designation'] ?? '')),
                'custom_fields' => $customFields,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultAttendeeDetails(MobileUser $member): array
    {
        $nameParts = str($member->name ?: '')
            ->squish()
            ->explode(' ')
            ->filter()
            ->values();

        return [
            'first_name' => $member->first_name ?: ($nameParts->first() ?: $member->name),
            'last_name' => $member->last_name ?: $nameParts->slice(1)->implode(' '),
            'email' => $member->email,
            'phone' => $member->phone,
            'company' => $member->company ?? null,
            'designation' => $member->designation,
            'custom_fields' => array_filter([
                'company' => $member->company ?? null,
                'designation' => $member->designation,
                'gender' => $member->gender,
            ], fn ($value): bool => filled($value)),
        ];
    }

    private function paymentTotal(EventTicketType $ticketType, int $attendeeQuantity, ?float $paymentAmount = null): float
    {
        $listPrice = round((float) $ticketType->price, 2);
        $listedTotal = round($listPrice * $attendeeQuantity, 2);

        return $paymentAmount === null
            ? $listedTotal
            : round((float) $paymentAmount, 2);
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
