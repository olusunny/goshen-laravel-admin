<?php

namespace Personal\EventInstallments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAttendeeField extends Model
{
    protected $table = 'ei_event_attendee_fields';

    protected $fillable = [
        'event_id',
        'key',
        'label',
        'type',
        'is_required',
        'is_unique',
        'options',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_unique' => 'boolean',
        'options' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
