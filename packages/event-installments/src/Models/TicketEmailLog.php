<?php

namespace Personal\EventInstallments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketEmailLog extends Model
{
    protected $table = 'ei_ticket_email_logs';

    protected $fillable = [
        'ticket_id',
        'booking_id',
        'recipient',
        'subject',
        'status',
        'attachments',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'sent_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
