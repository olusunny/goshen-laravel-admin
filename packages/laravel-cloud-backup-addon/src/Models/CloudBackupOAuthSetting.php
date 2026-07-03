<?php

namespace ChurchTools\CloudBackup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CloudBackupOAuthSetting extends Model
{
    protected $table = 'cloud_backup_oauth_settings';

    protected $fillable = [
        'provider',
        'client_id',
        'client_secret',
        'tenant',
        'redirect_uri',
        'is_active',
    ];

    protected $casts = [
        'client_id' => 'encrypted',
        'client_secret' => 'encrypted',
        'is_active' => 'boolean',
    ];

    public static function configured(string $provider): bool
    {
        return filled(static::valueFor($provider, 'client_id'))
            && filled(static::valueFor($provider, 'client_secret'));
    }

    public static function valueFor(string $provider, string $key, mixed $fallback = null): mixed
    {
        $value = null;

        if (app()->bound('db') && Schema::hasTable('cloud_backup_oauth_settings')) {
            $setting = static::query()->where('provider', $provider)->first();
            $value = $setting?->{$key};
        }

        return filled($value) ? $value : $fallback;
    }

    public static function forProvider(string $provider): ?self
    {
        if (! app()->bound('db') || ! Schema::hasTable('cloud_backup_oauth_settings')) {
            return null;
        }

        return static::query()->where('provider', $provider)->first();
    }

    public static function activeProvider(): string
    {
        if (! app()->bound('db') || ! Schema::hasTable('cloud_backup_oauth_settings')) {
            return 'google';
        }

        return static::query()->where('is_active', true)->value('provider') ?: 'google';
    }

    public static function activate(string $provider): void
    {
        if (! app()->bound('db') || ! Schema::hasTable('cloud_backup_oauth_settings')) {
            return;
        }

        static::query()->update(['is_active' => false]);
        static::query()->updateOrCreate(
            ['provider' => $provider],
            ['is_active' => true],
        );
    }
}
