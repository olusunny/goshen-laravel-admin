<?php

namespace App\Services;

use App\Models\GoshenReferralCode;
use App\Models\GoshenReferralPointEntry;
use App\Models\GoshenWallet;
use App\Models\MobileUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Models\TicketCheckIn;
use RuntimeException;

class GoshenReferralService
{
    public function __construct(
        private readonly GoshenReferralSettings $settings,
        private readonly GoshenWalletService $wallets,
        private readonly GoshenRetreatNotificationService $notifications,
    ) {}

    public function ensureCodeFor(MobileUser $user): GoshenReferralCode
    {
        $existing = GoshenReferralCode::query()
            ->where('mobile_user_id', $user->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        for ($attempt = 0; $attempt < 8; $attempt++) {
            try {
                return GoshenReferralCode::query()->create([
                    'mobile_user_id' => $user->id,
                    'code' => $this->generateCode($user),
                    'generated_at' => now(),
                ]);
            } catch (QueryException $exception) {
                if (! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('A referral code could not be generated. Please try again.');
    }

    public function ensureCodesForExistingUsers(int $chunkSize = 200): int
    {
        $created = 0;

        MobileUser::query()
            ->whereNotExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('goshen_referral_codes')
                    ->whereColumn('goshen_referral_codes.mobile_user_id', 'mobile_users.id');
            })
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $users) use (&$created): void {
                $users->each(function (MobileUser $user) use (&$created): void {
                    $this->ensureCodeFor($user);
                    $created++;
                });
            });

        return $created;
    }

    public function codeFromInput(?string $code): ?GoshenReferralCode
    {
        $normalized = $this->normalizeCode($code);

        if ($normalized === '') {
            return null;
        }

        return GoshenReferralCode::query()
            ->with('user')
            ->where('code', $normalized)
            ->first();
    }

    public function acceptedCodeForReferee(MobileUser $referee, ?string $code): ?GoshenReferralCode
    {
        if (! $this->settings->enabled()) {
            return null;
        }

        $referralCode = $this->codeFromInput($code);

        if (! $referralCode) {
            if ($this->normalizeCode($code) !== '') {
                throw new RuntimeException('Please enter a valid Goshen Retreat referral code.');
            }

            return null;
        }

        if ((int) $referralCode->mobile_user_id === (int) $referee->id) {
            throw new RuntimeException('You cannot use your own referral code for Goshen Retreat registration.');
        }

        if (! $referralCode->user || $referralCode->user->is_deleted || $referralCode->user->is_blocked) {
            throw new RuntimeException('This referral code is not available for Goshen Retreat registration.');
        }

        return $referralCode;
    }

    public function createPendingAwardForPaidBooking(Booking $booking): ?GoshenReferralPointEntry
    {
        if (! $this->settings->enabled()) {
            return null;
        }

        $booking->loadMissing(['event', 'attendees']);
        if (! $booking->event || ! $this->isGoshenEvent($booking->event)) {
            return null;
        }

        if (! $this->bookingIsPaidForAward($booking)) {
            return null;
        }

        $metadata = is_array($booking->metadata) ? $booking->metadata : [];
        $codeId = $metadata['referral_code_id'] ?? null;
        $referrerId = $metadata['referrer_mobile_user_id'] ?? null;

        if (! $codeId || ! $referrerId || ! $booking->customer_id) {
            return null;
        }

        if ((int) $referrerId === (int) $booking->customer_id) {
            return null;
        }

        $referralCode = GoshenReferralCode::query()
            ->whereKey((int) $codeId)
            ->where('mobile_user_id', (int) $referrerId)
            ->first();

        if (! $referralCode) {
            return null;
        }

        return DB::transaction(function () use ($booking, $metadata, $referralCode, $referrerId): ?GoshenReferralPointEntry {
            $lockedBooking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->bookingIsPaidForAward($lockedBooking)) {
                return null;
            }

            $existing = GoshenReferralPointEntry::query()
                ->where(function ($query) use ($lockedBooking, $referrerId): void {
                    $query
                        ->where('booking_id', $lockedBooking->id)
                        ->orWhere(function ($query) use ($lockedBooking, $referrerId): void {
                            $query
                                ->where('referrer_mobile_user_id', (int) $referrerId)
                                ->where('referee_mobile_user_id', (int) $lockedBooking->customer_id)
                                ->where('event_id', (int) $lockedBooking->event_id);
                        });
                })
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            return GoshenReferralPointEntry::query()->create([
                'referrer_mobile_user_id' => (int) $referrerId,
                'referee_mobile_user_id' => (int) $lockedBooking->customer_id,
                'referral_code_id' => $referralCode->id,
                'event_id' => (int) $lockedBooking->event_id,
                'booking_id' => (int) $lockedBooking->id,
                'attendee_id' => $booking->attendees->first()?->id,
                'status' => GoshenReferralPointEntry::STATUS_PENDING_VALIDATION,
                'points' => $this->settings->pointsPerPaidRegistration(),
                'metadata' => [
                    'source' => 'goshen_retreat_paid_registration',
                    'booking_public_id' => $lockedBooking->public_id,
                    'referral_code' => $metadata['referral_code'] ?? $referralCode->code,
                    'payment_mode' => $metadata['payment_mode'] ?? null,
                    'event_name' => $booking->event?->name,
                ],
            ]);
        });
    }

    public function validateForTicketCheckIn(TicketCheckIn $checkIn): ?GoshenReferralPointEntry
    {
        $status = $checkIn->status?->value ?? (string) $checkIn->status;
        if ($status !== TicketStatus::CheckedIn->value) {
            return null;
        }

        $ticket = $checkIn->ticket()->with(['event', 'booking', 'attendee'])->first();

        return $ticket ? $this->validateForTicket($ticket) : null;
    }

    public function validateForTicket(Ticket $ticket): ?GoshenReferralPointEntry
    {
        if (! $this->settings->enabled()) {
            return null;
        }

        $ticket->loadMissing(['event', 'booking', 'attendee']);
        if (! $ticket->event || ! $ticket->booking || ! $this->isGoshenEvent($ticket->event)) {
            return null;
        }

        $entry = DB::transaction(function () use ($ticket): ?GoshenReferralPointEntry {
            $entry = GoshenReferralPointEntry::query()
                ->where('booking_id', $ticket->booking_id)
                ->where('status', GoshenReferralPointEntry::STATUS_PENDING_VALIDATION)
                ->lockForUpdate()
                ->first();

            if (! $entry) {
                return null;
            }

            $entry->forceFill([
                'status' => GoshenReferralPointEntry::STATUS_VALIDATED,
                'attendee_id' => $entry->attendee_id ?: $ticket->attendee_id,
                'validated_at' => now(),
                'metadata' => array_merge($entry->metadata ?? [], [
                    'validated_by_ticket_id' => $ticket->id,
                    'validated_by_ticket_number' => $ticket->formatted_number ?: $ticket->ticket_number,
                ]),
            ])->save();

            return $entry->fresh(['referrer', 'referee', 'event', 'booking']);
        });

        if ($entry && ! $entry->notified_at) {
            $this->notifyValidatedEntry($entry);
        }

        return $entry;
    }

    public function summaryFor(MobileUser $user): array
    {
        $code = $this->ensureCodeFor($user);
        $wallet = $this->wallets->walletFor($user);
        $entries = GoshenReferralPointEntry::query()
            ->with(['referee', 'event', 'booking', 'referralCode'])
            ->where('referrer_mobile_user_id', $user->id)
            ->latest()
            ->get();

        $validatedEntries = $entries->where('status', GoshenReferralPointEntry::STATUS_VALIDATED);
        $availablePoints = (int) $validatedEntries->sum(fn (GoshenReferralPointEntry $entry): int => $entry->availablePoints());
        $pendingPoints = (int) $entries
            ->where('status', GoshenReferralPointEntry::STATUS_PENDING_VALIDATION)
            ->sum('points');
        $convertedPoints = (int) $entries->sum('converted_points');
        $rate = $this->settings->walletAmountPerPoint();

        return [
            'code' => $code->code,
            'referral_code' => $code->code,
            'currency' => $wallet->currency,
            'pending_points' => $pendingPoints,
            'pending_validation' => $pendingPoints,
            'validated_points' => $availablePoints,
            'available_points' => $availablePoints,
            'converted_points' => $convertedPoints,
            'total_points' => (int) $entries->sum('points'),
            'total_earned' => (int) $entries->sum('points'),
            'wallet_amount' => round($availablePoints * $rate, 2),
            'wallet_amount_available' => round($availablePoints * $rate, 2),
            'can_convert' => $this->settings->enabled()
                && $rate > 0
                && $availablePoints >= $this->settings->minConvertiblePoints(),
            'settings' => $this->settings->payload(),
            'points' => [
                'pending_validation' => $pendingPoints,
                'validated' => $availablePoints,
                'converted' => $convertedPoints,
                'total_earned' => (int) $entries->sum('points'),
                'wallet_amount_available' => round($availablePoints * $rate, 2),
                'can_convert' => $this->settings->enabled()
                    && $rate > 0
                    && $availablePoints >= $this->settings->minConvertiblePoints(),
            ],
            'entries' => $entries
                ->take(50)
                ->map(fn (GoshenReferralPointEntry $entry): array => $this->entryPayload($entry))
                ->values(),
        ];
    }

    public function convertValidatedPointsToWallet(MobileUser $user): array
    {
        if (! $this->settings->enabled()) {
            throw new RuntimeException('Goshen Retreat referrals are not available right now.');
        }

        $rate = $this->settings->walletAmountPerPoint();
        if ($rate <= 0) {
            throw new RuntimeException('Referral wallet conversion is not configured yet.');
        }

        return DB::transaction(function () use ($user, $rate): array {
            $entries = GoshenReferralPointEntry::query()
                ->where('referrer_mobile_user_id', $user->id)
                ->where('status', GoshenReferralPointEntry::STATUS_VALIDATED)
                ->whereColumn('converted_points', '<', 'points')
                ->lockForUpdate()
                ->orderBy('validated_at')
                ->orderBy('id')
                ->get();

            $points = (int) $entries->sum(fn (GoshenReferralPointEntry $entry): int => $entry->availablePoints());
            if ($points < $this->settings->minConvertiblePoints()) {
                throw new RuntimeException('You do not have enough validated referral points to convert yet.');
            }

            $wallet = $this->wallets->walletFor($user);
            $wallet = GoshenWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $amount = round($points * $rate, 2);
            $reference = 'gr_ref_' . Str::ulid();

            $wallet->forceFill([
                'balance' => round(((float) $wallet->balance) + $amount, 2),
            ])->save();

            $ledger = $wallet->ledgerEntries()->create([
                'type' => 'referral_conversion',
                'status' => 'paid',
                'currency' => $wallet->currency,
                'amount' => $amount,
                'gateway' => 'goshen_referrals',
                'provider_reference' => $reference,
                'metadata' => [
                    'points_converted' => $points,
                    'amount_per_point' => $rate,
                    'referral_entry_ids' => $entries->pluck('id')->values()->all(),
                ],
                'settled_at' => now(),
            ]);

            $entries->each(function (GoshenReferralPointEntry $entry) use ($ledger): void {
                $entry->forceFill([
                    'status' => GoshenReferralPointEntry::STATUS_CONVERTED,
                    'converted_points' => $entry->points,
                    'wallet_ledger_entry_id' => $ledger->id,
                    'converted_at' => now(),
                ])->save();
            });

            return [
                'wallet' => $wallet->fresh(),
                'ledger_entry' => $ledger->fresh(),
                'points_converted' => $points,
                'wallet_amount' => $amount,
                'reference' => $reference,
            ];
        });
    }

    public function entryPayload(GoshenReferralPointEntry $entry): array
    {
        $rate = $this->settings->walletAmountPerPoint();

        return [
            'id' => $entry->id,
            'status' => $entry->status,
            'points' => (int) $entry->points,
            'available_points' => $entry->availablePoints(),
            'converted_points' => (int) $entry->converted_points,
            'referral_code' => $entry->referralCode?->code,
            'referee_name' => $entry->referee?->name,
            'referee_email' => $entry->referee?->email,
            'referred_name' => $entry->referee?->name,
            'referred_email' => $entry->referee?->email,
            'event_name' => $entry->event?->name,
            'booking_public_id' => $entry->booking?->public_id,
            'wallet_amount' => round($entry->availablePoints() * $rate, 2),
            'validated_at' => $entry->validated_at?->toIso8601String(),
            'converted_at' => $entry->converted_at?->toIso8601String(),
            'created_at' => $entry->created_at?->toIso8601String(),
        ];
    }

    private function notifyValidatedEntry(GoshenReferralPointEntry $entry): void
    {
        $referrer = $entry->referrer;
        if (! $referrer) {
            return;
        }

        $refereeName = $entry->referee?->name ?: 'your referee';
        $eventName = $entry->event?->name ?: 'Goshen Retreat';
        $points = (int) $entry->points;

        $this->notifications->notifyUser(
            $referrer,
            'Goshen referral points validated',
            "{$refereeName} has checked in for {$eventName}. Your {$points} referral point(s) are now validated and can be converted when eligible.",
            'events',
        );

        $entry->forceFill(['notified_at' => now()])->save();
    }

    private function bookingIsPaidForAward(Booking $booking): bool
    {
        $status = $booking->status?->value ?? (string) $booking->status;
        $total = (float) $booking->total;
        $paidTotal = (float) $booking->paid_total;

        return $total > 0
            && $status === BookingStatus::Paid->value
            && $paidTotal + 0.01 >= $total;
    }

    private function generateCode(MobileUser $user): string
    {
        $seed = strtoupper(Str::of((string) $user->name)
            ->replaceMatches('/[^A-Za-z0-9]/', '')
            ->substr(0, 4)
            ->padRight(4, 'G')
            ->toString());

        return 'GR' . $seed . strtoupper(Str::random(6));
    }

    private function normalizeCode(?string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim((string) $code)) ?: '');
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, ['1062', '19'], true);
    }

    private function isGoshenEvent(Event $event): bool
    {
        $settings = is_array($event->settings) ? $event->settings : [];
        $module = strtolower(trim((string) ($settings['module'] ?? $settings['app_module'] ?? '')));

        if (in_array($module, ['goshen_retreat', 'goshen-retreat'], true)) {
            return true;
        }

        $slug = strtolower((string) $event->slug);
        if (str_starts_with($slug, 'goshen-retreat') || str_starts_with($slug, 'goshen-')) {
            return true;
        }

        return str_contains(strtolower((string) $event->name), 'goshen retreat');
    }
}
