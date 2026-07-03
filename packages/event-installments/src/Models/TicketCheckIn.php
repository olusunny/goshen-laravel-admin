<?php

namespace Personal\EventInstallments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Personal\EventInstallments\Enums\TicketStatus;

class TicketCheckIn extends Model
{
    protected $table = 'ei_ticket_check_ins';

    protected $fillable = [
        'ticket_id',
        'event_id',
        'actor_id',
        'day_number',
        'status',
        'checked_in_at',
        'source',
        'device_id',
        'metadata',
    ];

    protected $casts = [
        'status' => TicketStatus::class,
        'checked_in_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
