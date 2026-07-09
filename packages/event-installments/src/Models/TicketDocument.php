<?php

namespace Personal\EventInstallments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketDocument extends Model
{
    protected $table = 'ei_ticket_documents';

    protected $fillable = [
        'ticket_id',
        'type',
        'disk',
        'path',
        'mime_type',
        'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
