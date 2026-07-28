<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Personal\EventInstallments\Models\Attendee;

class GoshenFamilyMember extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date_of_birth' => 'date',
        'metadata' => 'array',
    ];

    public function family(): BelongsTo
    {
        return $this->belongsTo(GoshenFamily::class, 'goshen_family_id');
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    public function mobileUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class);
    }
}
