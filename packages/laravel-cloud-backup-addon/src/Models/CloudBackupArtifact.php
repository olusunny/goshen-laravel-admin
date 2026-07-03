<?php

namespace ChurchTools\CloudBackup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CloudBackupArtifact extends Model
{
    protected $table = 'cloud_backup_artifacts';

    protected $fillable = [
        'run_id',
        'type',
        'filename',
        'local_path',
        'size',
        'checksum',
        'remote_path',
        'remote_id',
        'status',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(CloudBackupRun::class, 'run_id');
    }
}
