<?php

namespace ChurchTools\CloudBackup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class CloudBackupConnection extends Model
{
    protected $table = 'cloud_backup_connections';

    protected $fillable = [
        'name',
        'provider',
        'owner_name',
        'owner_email',
        'folder_path',
        'token_payload',
        'scopes',
        'connected_at',
        'last_error',
    ];

    protected $casts = [
        'scopes' => 'array',
        'connected_at' => 'datetime',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(CloudBackupSchedule::class, 'connection_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(CloudBackupRun::class, 'connection_id');
    }

    public function setTokenPayloadAttribute(?array $payload): void
    {
        $this->attributes['token_payload'] = $payload === null ? null : Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function getTokenPayloadAttribute(?string $payload): ?array
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        return json_decode(Crypt::decryptString($payload), true, 512, JSON_THROW_ON_ERROR);
    }
}
