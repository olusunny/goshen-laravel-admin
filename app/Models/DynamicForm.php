<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DynamicForm extends Model
{
    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_AUTHENTICATED = 'authenticated';

    public const PAYMENT_FREE = 'free';
    public const PAYMENT_FIXED = 'fixed';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'one_submission_per_user' => 'boolean',
        'allow_stripe' => 'boolean',
        'allow_wallet' => 'boolean',
        'fixed_amount' => 'decimal:2',
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (DynamicForm $form): void {
            if (blank($form->slug)) {
                $form->slug = Str::slug((string) $form->title);
            }

            $form->slug = Str::slug((string) $form->slug);
            $form->currency = strtoupper((string) ($form->currency ?: 'GBP'));
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DynamicFormField::class)->orderBy('sort_order')->orderBy('id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(DynamicFormSubmission::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('opens_at')->orWhere('opens_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('closes_at')->orWhere('closes_at', '>=', now());
            });
    }

    public function isOpen(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->opens_at && $this->opens_at->isFuture()) {
            return false;
        }

        if ($this->closes_at && $this->closes_at->lt(now())) {
            return false;
        }

        return true;
    }

    public function requiresLogin(): bool
    {
        return $this->visibility === self::VISIBILITY_AUTHENTICATED;
    }

    public function requiresPayment(): bool
    {
        return $this->payment_type === self::PAYMENT_FIXED && (float) $this->fixed_amount > 0;
    }
}
