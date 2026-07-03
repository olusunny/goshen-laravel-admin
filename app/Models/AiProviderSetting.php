<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AiProviderSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'last_tested_at' => 'datetime',
        'temperature' => 'float',
    ];

    public function getApiKeyAttribute(?string $value): ?string
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

    public function setApiKeyAttribute(?string $value): void
    {
        if (filled($value)) {
            $this->attributes['api_key'] = Crypt::encryptString($value);
        }
    }

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->latest('id')->first();
    }
}
