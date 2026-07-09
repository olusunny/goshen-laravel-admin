<?php

namespace Personal\EventInstallments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Concerns\HasPublicId;

class Ticket extends Model
{
    use HasPublicId;

    protected $table = 'ei_tickets';

    protected $fillable = [
        'event_id',
        'booking_id',
        'attendee_id',
        'ticket_type_id',
        'ticket_number',
        'formatted_number',
        'qr_hash',
        'status',
        'multiday_status',
        'issued_at',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'status' => TicketStatus::class,
        'multiday_status' => 'array',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(EventTicketType::class, 'ticket_type_id');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(TicketCheckIn::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TicketDocument::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(TicketEmailLog::class);
    }
}
