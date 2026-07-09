<?php

namespace Personal\EventInstallments\Models;

use Illuminate\Database\Eloquent\Model;

class EventAuditLog extends Model
{
    protected $table = 'ei_event_audit_logs';

    protected $fillable = [
        'event_id',
        'actor_id',
        'action',
        'auditable_type',
        'auditable_id',
        'before',
        'after',
        'metadata',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'metadata' => 'array',
    ];
}
