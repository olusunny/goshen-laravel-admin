<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CronJobStatus extends Model
{
    protected $fillable = [
        'key',
        'label',
        'expression',
        'frequency_label',
        'command',
        'description',
        'status',
        'last_started_at',
        'last_finished_at',
        'last_success_at',
        'last_failed_at',
        'last_runtime_ms',
        'last_exit_code',
        'run_count',
        'failure_count',
        'last_message',
        'metadata',
    ];

    protected $casts = [
        'last_started_at' => 'datetime',
        'last_finished_at' => 'datetime',
        'last_success_at' => 'datetime',
        'last_failed_at' => 'datetime',
        'metadata' => 'array',
    ];
}
