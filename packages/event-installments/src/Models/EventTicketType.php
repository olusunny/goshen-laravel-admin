<?php

namespace Personal\EventInstallments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Personal\EventInstallments\Models\Concerns\HasPublicId;

class EventTicketType extends Model
{
    use HasPublicId;

    protected $table = 'ei_event_ticket_types';

    protected $fillable = [
        'event_id',
        'name',
        'sku',
        'currency',
        'price',
        'capacity',
        'min_per_booking',
        'max_per_booking',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'ticket_type_id');
    }
}
