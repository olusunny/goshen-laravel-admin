<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;

class GoshenFamily extends Model
{
    protected $guarded = [];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class, 'created_by_mobile_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GoshenFamilyMember::class);
    }
}
