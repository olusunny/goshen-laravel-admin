<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Models;

use Illuminate\Database\Eloquent\Model;

class BirthdayTemplate extends Model
{
    protected $table = 'birthday_celebration_templates';
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean', 'is_default' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(function (self $template): void {
            if ($template->is_default) {
                static::query()->whereKeyNot($template->getKey())->where('is_default', true)->update(['is_default' => false]);
            }
        });
    }

    public static function selected(): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderByDesc('version')
            ->orderBy('id')
            ->first();
    }

    public function celebrations()
    {
        return $this->hasMany(BirthdayCelebration::class, 'template_id');
    }
}
