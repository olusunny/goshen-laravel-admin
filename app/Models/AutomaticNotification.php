<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomaticNotification extends Model
{
    protected $guarded = [];

    protected $casts = [
        'send_email' => 'boolean',
        'send_inbox' => 'boolean',
        'send_push' => 'boolean',
        'is_active' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(AutomaticNotificationDelivery::class);
    }
}
