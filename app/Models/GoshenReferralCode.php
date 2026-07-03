<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoshenReferralCode extends Model
{
    protected $guarded = [];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class, 'mobile_user_id');
    }

    public function pointEntries(): HasMany
    {
        return $this->hasMany(GoshenReferralPointEntry::class, 'referral_code_id');
    }
}
