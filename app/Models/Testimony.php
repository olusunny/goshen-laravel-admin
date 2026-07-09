<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use App\Services\TestimonyNotificationService;

class Testimony extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $guarded = [];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Testimony $testimony) {
            if ($testimony->audio_path) {
                Storage::disk('public')->delete($testimony->audio_path);
            }
        });
    }

    public function mobileUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function approve(?int $adminId = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $adminId,
            'approved_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ])->save();
    }

    public function reject(?int $adminId = null, ?string $reason = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_REJECTED,
            'rejected_by' => $adminId,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        app(TestimonyNotificationService::class)->sendRejection($this->fresh(['mobileUser']));
    }
}
