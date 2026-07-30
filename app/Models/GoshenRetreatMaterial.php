<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Personal\EventInstallments\Models\Event;

class GoshenRetreatMaterial extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_published' => 'boolean',
        'file_size' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updated(function (self $material): void {
            if ($material->wasChanged('file_path')) {
                Storage::disk('local')->delete((string) $material->getOriginal('file_path'));
            }
        });

        static::deleted(function (self $material): void {
            Storage::disk('local')->delete((string) $material->file_path);
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
