<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class WebWalletVerificationChallenge extends Model
{
    protected $guarded = [];

    protected $hidden = ['code_hash'];

    protected $casts = [
        'context' => 'array',
        'attempts' => 'integer',
        'send_count' => 'integer',
        'last_failed_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];
}
