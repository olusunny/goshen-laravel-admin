<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChurchGroup extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function leader(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class, 'leader_id');
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class, 'assistant_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(MobileUser::class, 'group_id');
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(ChurchGroupJoinRequest::class);
    }
}
