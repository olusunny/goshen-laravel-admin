<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
        static::saving(function (self $material): void {
            $material->ensureGoshenEvent();

            $disk = Storage::disk('local');
            if (! $material->file_path || ! $disk->exists($material->file_path)) {
                return;
            }

            $fileChanged = $material->isDirty('file_path');
            if (($fileChanged && ! $material->isDirty('mime_type')) || blank($material->mime_type)) {
                $material->mime_type = $disk->mimeType($material->file_path) ?: 'application/octet-stream';
            }
            if (($fileChanged && ! $material->isDirty('file_size')) || $material->file_size === null) {
                $material->file_size = $disk->size($material->file_path) ?: 0;
            }
        });

        static::updated(function (self $material): void {
            $previousPath = (string) $material->getOriginal('file_path');
            if ($material->wasChanged('file_path') && $previousPath !== '' && $previousPath !== $material->file_path) {
                Storage::disk('local')->delete($previousPath);
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

    private function ensureGoshenEvent(): void
    {
        $event = $this->relationLoaded('event') ? $this->event : Event::query()->find($this->event_id);
        $settings = is_array($event?->settings) ? $event->settings : [];
        $module = strtolower(trim((string) ($settings['module'] ?? $settings['app_module'] ?? '')));
        $isGoshen = in_array($module, ['goshen_retreat', 'goshen-retreat'], true)
            || str_starts_with(strtolower((string) $event?->slug), 'goshen-')
            || str_contains(strtolower((string) $event?->name), 'goshen retreat');

        if (! $isGoshen) {
            throw ValidationException::withMessages([
                'event_id' => 'Retreat materials can only be attached to a Goshen Retreat edition.',
            ]);
        }
    }
}
