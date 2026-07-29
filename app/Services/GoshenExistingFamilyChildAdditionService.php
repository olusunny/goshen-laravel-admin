<?php

namespace App\Services;

use App\Models\GoshenFamily;
use App\Models\GoshenFamilyMember;
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
use Personal\EventInstallments\Services\TicketIssuer;

class GoshenExistingFamilyChildAdditionService
{
    public function __construct(
        private readonly GoshenAdminTicketIssuanceService $ticketIssuance,
        private readonly GoshenFamilyRegistrationService $familyRegistration,
        private readonly GoshenRegistrationAvailabilityService $availability,
        private readonly TicketIssuer $ticketIssuer,
    ) {}

    /**
     * @param  array<string, mixed>  $child
     * @param  array{method?: string, voucher_code?: string, wallet_challenge?: WebWalletVerificationChallenge|null, wallet_code?: string|null, approved_amount?: float|int|string|null, approval_note?: string|null, ip?: string|null, user_agent?: string|null}  $payment
     */
    public function add(
        User $admin,
        GoshenFamily $family,
        MobileUser $billingParent,
        EventTicketType $ticketType,
        string $reason,
        array $child,
        array $payment = [],
    ): Ticket {
        $reason = trim($reason);
        if (mb_strlen($reason) < 12) {
            throw ValidationException::withMessages(['reason' => 'Enter an audit reason of at least 12 characters.']);
        }

        return DB::transaction(function () use ($admin, $family, $billingParent, $ticketType, $reason, $child, $payment): Ticket {
            $family = GoshenFamily::query()
                ->with('members')
                ->whereKey($family->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $ticketType->event_id !== (int) $family->event_id) {
                throw ValidationException::withMessages(['ticket_type_id' => 'Select a ticket type from this family\'s retreat edition.']);
            }
            if ($this->familyRegistration->isFamilyTicket((string) $ticketType->name)) {
                throw ValidationException::withMessages(['ticket_type_id' => 'Add a child with an individual Goshen ticket type.']);
            }
            if (! $family->members->contains(fn (GoshenFamilyMember $member): bool => $member->mobile_user_id === $billingParent->id && in_array($member->role, ['father', 'mother'], true))) {
                throw ValidationException::withMessages(['billing_parent_id' => 'Choose a parent already linked to this family.']);
            }

            $child = $this->familyRegistration->prepareChild($child);

            if (! is_array($child) || $this->isDuplicateChild($family, $child)) {
                throw ValidationException::withMessages(['child' => 'This child is already attached to the family.']);
            }

            $parentNames = $family->members
                ->whereIn('role', ['father', 'mother'])
                ->mapWithKeys(fn (GoshenFamilyMember $member): array => [$member->role => trim("{$member->first_name} {$member->last_name}")])
                ->all();
            $attendeeDetails = [[
                'first_name' => $child['first_name'],
                'last_name' => $child['last_name'],
                'email' => $child['email'],
                'phone' => $child['phone'],
                'custom_fields' => [
                    'family_name' => $family->name,
                    'family_role' => 'child',
                    'family_age' => $child['age'],
                    'gender' => $child['gender'],
                    'family_parent_names' => $parentNames,
                    'ticket_amount' => $child['is_payable'] ? (float) $ticketType->price : 0,
                    'payment_exempt' => ! $child['is_payable'],
                ],
            ]];

            $ticket = $child['is_payable']
                ? $this->paidChildTicket($admin, $family, $billingParent, $ticketType, $reason, $child, $attendeeDetails, $payment)
                : $this->complimentaryChildTicket($family, $billingParent, $ticketType, $child, $attendeeDetails);

            $member = GoshenFamilyMember::query()->create([
                'goshen_family_id' => $family->id,
                'attendee_id' => $ticket->attendee_id,
                'mobile_user_id' => $child['mobile_user_id'],
                'role' => 'child',
                'date_of_birth' => $child['date_of_birth'],
                'first_name' => $child['first_name'],
                'last_name' => $child['last_name'] ?: null,
                'email' => $child['email'],
                'phone' => $child['phone'],
                'metadata' => [
                    'age' => $child['age'],
                    'gender' => $child['gender'],
                    'is_payable' => $child['is_payable'],
                    'profile_status' => $child['profile_status'],
                    'source' => 'admin_existing_family_child_addition',
                ],
            ]);

            EventAuditLog::query()->create([
                'event_id' => $family->event_id,
                'actor_id' => $admin->id,
                'action' => 'goshen_existing_family_child_added',
                'auditable_type' => GoshenFamily::class,
                'auditable_id' => $family->id,
                'after' => [
                    'family_member_id' => $member->id,
                    'attendee_id' => $ticket->attendee_id,
                    'ticket_id' => $ticket->id,
                    'booking_id' => $ticket->booking_id,
                    'age' => $child['age'],
                    'is_payable' => $child['is_payable'],
                ],
                'metadata' => [
                    'reason' => $reason,
                    'source' => 'admin_existing_family_child_addition',
                    'payment_method' => $child['is_payable'] ? strtolower(trim((string) ($payment['method'] ?? ''))) : 'complimentary',
                ],
            ]);

            return $ticket->fresh(['booking', 'attendee', 'ticketType', 'event']);
        });
    }

    /** @param array<string, mixed> $child */
    private function isDuplicateChild(GoshenFamily $family, array $child): bool
    {
        return GoshenFamilyMember::query()
            ->where('goshen_family_id', $family->id)
            ->where('role', 'child')
            ->lockForUpdate()
            ->where(function ($query) use ($child): void {
                if ($child['mobile_user_id'] !== null) {
                    $query->where('mobile_user_id', $child['mobile_user_id']);
                }

                $query->{($child['mobile_user_id'] !== null) ? 'orWhere' : 'where'}(function ($query) use ($child): void {
                    $query
                        ->whereRaw('LOWER(first_name) = ?', [strtolower((string) $child['first_name'])])
                        ->whereRaw("LOWER(COALESCE(last_name, '')) = ?", [strtolower((string) $child['last_name'])])
                        ->where('metadata->age', $child['age'])
                        ->where('metadata->gender', $child['gender']);
                });
            })
            ->exists();
    }

    /** @param array<string, mixed> $child @param array<int, array<string, mixed>> $attendeeDetails @param array<string, mixed> $payment */
    private function paidChildTicket(User $admin, GoshenFamily $family, MobileUser $billingParent, EventTicketType $ticketType, string $reason, array $child, array $attendeeDetails, array $payment): Ticket
    {
        $method = strtolower(trim((string) ($payment['method'] ?? '')));
        if (! in_array($method, ['voucher', 'wallet'], true)) {
            throw ValidationException::withMessages(['payment_method' => 'Choose a voucher or your verified Goshen wallet for a paid child ticket.']);
        }
        $paymentAmount = $this->approvedPaymentAmount($ticketType, $payment);

        return $this->ticketIssuance->issueForExistingFamilyChild(
            $family,
            $billingParent,
            $ticketType,
            $admin,
            $reason,
            $method,
            $child,
            $payment['voucher_code'] ?? null,
            $payment['wallet_challenge'] ?? null,
            $payment['wallet_code'] ?? null,
            $payment['ip'] ?? null,
            $payment['user_agent'] ?? null,
            $paymentAmount,
            [
                'source' => 'admin_existing_family_child_addition',
                'goshen_family_id' => $family->id,
                'family_name' => $family->name,
                'family_role' => 'child',
                'family_age' => $child['age'],
                'billing_parent_mobile_user_id' => $billingParent->id,
                'special_approved_amount' => $paymentAmount !== null,
                'special_approval_note' => $paymentAmount === null ? null : trim((string) ($payment['approval_note'] ?? '')),
                'special_approved_by_admin_id' => $paymentAmount === null ? null : $admin->id,
                'special_approved_listed_total' => $paymentAmount === null ? null : round((float) $ticketType->price, 2),
                'special_approved_discount_amount' => $paymentAmount === null ? null : max(0, round((float) $ticketType->price - $paymentAmount, 2)),
            ],
            $attendeeDetails,
        );
    }

    /** @param array<string, mixed> $child @param array<int, array<string, mixed>> $attendeeDetails */
    private function complimentaryChildTicket(GoshenFamily $family, MobileUser $billingParent, EventTicketType $ticketType, array $child, array $attendeeDetails): Ticket
    {
        [$billingParent, $ticketType] = $this->availability->lockAndAssertAvailableForFamilyChild($family, $billingParent, $ticketType);
        $booking = Booking::query()->create([
            'event_id' => $family->event_id,
            'customer_id' => $billingParent->id,
            'customer_name' => $billingParent->name,
            'customer_email' => $billingParent->email,
            'customer_phone' => $billingParent->phone,
            'currency' => strtoupper((string) $ticketType->currency),
            'subtotal' => 0,
            'total' => 0,
            'paid_total' => 0,
            'status' => BookingStatus::Paid,
            'metadata' => [
                'source' => 'admin_existing_family_child_addition',
                'goshen_family_id' => $family->id,
                'family_name' => $family->name,
                'family_role' => 'child',
                'family_age' => $child['age'],
                'payment_method' => 'complimentary',
                'payment_exempt' => true,
            ],
        ]);
        BookingLine::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'currency' => strtoupper((string) $ticketType->currency),
            'unit_price' => 0,
            'line_total' => 0,
            'metadata' => ['family_child_payment_exemption' => true],
        ]);
        $details = $attendeeDetails[0];
        $attendee = Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => $details['first_name'],
            'last_name' => $details['last_name'],
            'email' => $details['email'],
            'phone' => $details['phone'],
            'custom_fields' => $details['custom_fields'],
        ]);
        $ticket = $this->ticketIssuer->issueForBooking($booking)[0] ?? null;

        if (! $ticket instanceof Ticket || $ticket->attendee_id !== $attendee->id) {
            throw ValidationException::withMessages(['child' => 'The complimentary child ticket could not be issued.']);
        }

        return $ticket;
    }

    /** @param array<string, mixed> $payment */
    private function approvedPaymentAmount(EventTicketType $ticketType, array $payment): ?float
    {
        if (! array_key_exists('approved_amount', $payment) || $payment['approved_amount'] === null || $payment['approved_amount'] === '') {
            return null;
        }

        $amount = round((float) $payment['approved_amount'], 2);
        $listedAmount = round((float) $ticketType->price, 2);
        if ($amount <= 0 || $amount + 0.01 >= $listedAmount) {
            throw ValidationException::withMessages(['special_approved_amount' => 'The special approved amount must be greater than zero and less than the listed ticket amount.']);
        }
        if (trim((string) ($payment['approval_note'] ?? '')) === '') {
            throw ValidationException::withMessages(['special_approval_note' => 'Enter the approval note for this special amount.']);
        }

        return $amount;
    }
}
