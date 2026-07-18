<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SmtpSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    public function getPasswordAttribute(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    public function setPasswordAttribute(?string $value): void
    {
        if (filled($value)) {
            $this->attributes['password'] = Crypt::encryptString($value);
        }
    }

    public function getProviderPresetAttribute(): string
    {
        return match (strtolower((string) $this->host)) {
            'smtp.zoho.com' => 'zoho',
            'smtp.gmail.com' => 'gmail',
            default => 'custom',
        };
    }

    protected static function booted(): void
    {
        static::saved(function (self $setting): void {
            if (! $setting->is_active) {
                return;
            }

            static::withoutEvents(function () use ($setting): void {
                static::query()
                    ->whereKeyNot($setting->getKey())
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            });
        });
    }

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->latest('id')->first();
    }
}
