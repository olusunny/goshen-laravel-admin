<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected static function booted(): void
    {
        static::created(fn (self $setting) => $setting->auditChange('created'));
        static::updated(fn (self $setting) => $setting->auditChange('updated'));
        static::deleted(fn (self $setting) => $setting->auditChange('deleted'));
    }

    private function auditChange(string $action): void
    {
        $actor = \Illuminate\Support\Facades\Auth::user();
        if (! $actor instanceof User || ! \Illuminate\Support\Facades\Schema::hasTable('admin_access_audits')) {
            return;
        }
        $before = $action === 'created' ? [] : $this->getRawOriginal();
        $after = $action === 'deleted' ? [] : $this->getAttributes();
        $redact = ($before['is_secret'] ?? false) || ($after['is_secret'] ?? false)
            || preg_match('/secret|password|token|credential|private_key|service_account/i', (string) $this->key);
        $snapshot = static function (array $data) use ($redact): array {
            $data = array_intersect_key($data, array_flip(['group', 'key', 'value', 'is_secret']));
            if ($redact && array_key_exists('value', $data)) {
                $data['value'] = '[redacted]';
            }
            return $data;
        };
        \Illuminate\Support\Facades\DB::table('admin_access_audits')->insert([
            'actor_id' => $actor->id, 'target_type' => 'app_setting', 'target_id' => $this->id,
            'action' => $action, 'before' => json_encode($snapshot($before), JSON_THROW_ON_ERROR),
            'after' => json_encode($snapshot($after), JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }

    protected $guarded = [];

    protected $casts = [
        'is_secret' => 'boolean',
    ];

    public static function value(string $key, mixed $default = null): mixed
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }
}
