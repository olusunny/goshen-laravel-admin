<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BirthdayCelebration extends Model
{
    public const PREVIEW_READY = 'preview_ready';
    public const PUBLISHED = 'published';
    public const CLOSED = 'closed';
    public const PURGED = 'purged';

    protected $table = 'birthday_celebrations';
    protected $guarded = [];
    protected $casts = ['birthday_date' => 'date', 'previewed_at' => 'datetime', 'published_at' => 'datetime', 'closes_at' => 'datetime', 'purge_due_at' => 'datetime', 'purged_at' => 'datetime', 'thank_you_at' => 'datetime', 'metadata' => 'array'];

    protected static function booted(): void { static::creating(fn (self $celebration) => $celebration->public_id ??= (string) Str::ulid()); }
    public function member(): BelongsTo { return $this->belongsTo(config('church-birthday-celebrations.models.mobile_user'), 'mobile_user_id'); }
    public function template(): BelongsTo { return $this->belongsTo(BirthdayTemplate::class, 'template_id'); }
    public function greetings(): HasMany { return $this->hasMany(BirthdayGreeting::class, 'celebration_id'); }
    public function reactions(): HasMany { return $this->hasMany(BirthdayReaction::class, 'celebration_id'); }
    public function deliveries(): HasMany { return $this->hasMany(BirthdayDelivery::class, 'celebration_id'); }
    public function isInteractive(): bool { return $this->status === self::PUBLISHED && $this->closes_at?->isFuture(); }
}
