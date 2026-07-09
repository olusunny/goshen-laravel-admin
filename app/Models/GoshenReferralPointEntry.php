<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;

class GoshenReferralPointEntry extends Model
{
    public const STATUS_PENDING_VALIDATION = 'Pending Validation';

    public const STATUS_VALIDATED = 'Validated';

    public const STATUS_CONVERTED = 'Converted';

    public const STATUS_CANCELLED = 'Cancelled';

    protected $guarded = [];

    protected $casts = [
        'points' => 'integer',
        'converted_points' => 'integer',
        'validated_at' => 'datetime',
        'converted_at' => 'datetime',
        'notified_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class, 'referrer_mobile_user_id');
    }

    public function referee(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class, 'referee_mobile_user_id');
    }

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(GoshenReferralCode::class, 'referral_code_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class, 'attendee_id');
    }

    public function walletLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(GoshenWalletLedgerEntry::class, 'wallet_ledger_entry_id');
    }

    public function availablePoints(): int
    {
        return max(0, (int) $this->points - (int) $this->converted_points);
    }
}
