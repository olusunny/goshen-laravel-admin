<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Models;

use Illuminate\Database\Eloquent\Model;

class BirthdaySetting extends Model
{
    protected $table = 'birthday_celebration_settings';

    protected $guarded = [];

    protected $casts = ['value' => 'array'];

    public static function value(string $key, mixed $fallback = null): mixed
    {
        $value = static::query()->where('key', $key)->first()?->value;

        return is_array($value) && array_key_exists('value', $value) ? $value['value'] : $fallback;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => ['value' => $value]]);
    }
}
