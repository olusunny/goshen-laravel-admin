<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropheticDecree extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'duration' => 'integer',
        'file_size' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function goUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class, 'go_user_id');
    }
}
