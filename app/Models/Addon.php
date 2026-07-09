<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Addon extends Model
{
    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_INSTALLED = 'installed';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_UPDATE_AVAILABLE = 'update_available';
    public const STATUS_UPDATE_FAILED = 'update_failed';
    public const STATUS_UNINSTALL_FAILED = 'uninstall_failed';
    public const STATUS_UNINSTALLED = 'uninstalled';

    protected $guarded = [];

    protected $casts = [
        'autoload_psr4' => 'array',
        'manifest' => 'array',
        'signature_verified' => 'boolean',
        'installed_at' => 'datetime',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'uninstalled_at' => 'datetime',
        'last_health_check_at' => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(AddonInstallLog::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(AddonUpdateBackup::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function supports(string $capability): bool
    {
        return (bool) data_get($this->manifest, 'supports_'.$capability, false);
    }
}
