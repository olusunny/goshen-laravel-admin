<?php

namespace ChurchTools\CloudBackup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CloudBackupRun extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';

    protected $table = 'cloud_backup_runs';

    protected $fillable = [
            'connection_id',
            'schedule_id',
            'initiated_by_user_id',
            'backup_name',
        'status',
        'progress_percent',
        'current_step',
        'started_at',
        'finished_at',
        'bytes_uploaded',
        'manifest',
        'log',
        'error_summary',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'manifest' => 'array',
        'progress_percent' => 'integer',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(CloudBackupConnection::class, 'connection_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(CloudBackupSchedule::class, 'schedule_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'initiated_by_user_id');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(CloudBackupArtifact::class, 'run_id');
    }

    public function appendLog(string $message): void
    {
        $line = '['.now()->toIso8601String().'] '.$message;
        $this->forceFill(['log' => trim(($this->log ? $this->log.PHP_EOL : '').$line)])->save();
    }
}
