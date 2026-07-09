<?php

namespace Personal\EventInstallments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Personal\EventInstallments\Models\Concerns\HasPublicId;

class Attendee extends Model
{
    use HasPublicId;

    protected $table = 'ei_attendees';

    protected $fillable = [
        'booking_id',
        'ticket_type_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'company',
        'designation',
        'custom_fields',
    ];

    protected $casts = [
        'custom_fields' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(EventTicketType::class, 'ticket_type_id');
    }

    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class);
    }
}
