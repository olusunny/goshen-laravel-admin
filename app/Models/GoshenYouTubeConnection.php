<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class GoshenYouTubeConnection extends Model
{
    use HasFactory;

    protected $table = 'goshen_youtube_connections';

    public const HEALTH_UNCONFIGURED = 'unconfigured';

    public const HEALTH_HEALTHY = 'healthy';

    public const HEALTH_REAUTH_REQUIRED = 'reauth_required';

    public const HEALTH_ERROR = 'error';

    public const HEALTH_QUOTA_BLOCKED = 'quota_blocked';

    public const HEALTH_DISCONNECTED = 'disconnected';

    protected $guarded = [];

    protected $hidden = [
        'refresh_token_payload',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'refresh_token_payload' => 'encrypted:array',
            'connected_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'quota_blocked_at' => 'datetime',
            'quota_resume_at' => 'datetime',
            'quota_resumed_at' => 'datetime',
        ];
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(GoshenExperienceVideo::class, 'youtube_connection_id');
    }

    public function hasUsableGrant(): bool
    {
        return filled(data_get($this->refresh_token_payload, 'refresh_token'));
    }

    public function quotaCircuitIsOpen(?Carbon $now = null): bool
    {
        return $this->quota_resume_at?->gt($now ?? now()) ?? false;
    }

    public function localizedQuotaResumeAt(string $timezone): ?string
    {
        return $this->quota_resume_at?->copy()->timezone($timezone)->toDayDateTimeString();
    }
}
