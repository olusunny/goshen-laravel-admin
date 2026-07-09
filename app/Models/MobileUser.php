<?php

namespace App\Models;

use App\Services\TriumphantIdService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class MobileUser extends Authenticatable
{
    use HasApiTokens;
    use HasRoles {
        assignRole as protected spatieAssignRole;
        syncRoles as protected spatieSyncRoles;
    }

    protected string $guard_name = 'mobile';

    protected $guarded = [];

    protected $hidden = ['password', 'api_token_hash'];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_blocked' => 'boolean',
        'is_deleted' => 'boolean',
        'password' => 'hashed',
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'email_verification_expires_at' => 'datetime',
        'password_reset_expires_at' => 'datetime',
        'last_login_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'scanner_suspended_at' => 'datetime',
        'notification_preferences' => 'array',
        'wallet_security_reset_required' => 'boolean',
        'wallet_security_reset_requested_at' => 'datetime',
        'wallet_security_reset_acknowledged_at' => 'datetime',
    ];

    public const TITLE_OPTIONS = [
        'Mr.' => 'Mr.',
        'Mrs.' => 'Mrs.',
        'Miss' => 'Miss',
    ];

    public const MARITAL_STATUS_OPTIONS = [
        'Single' => 'Single',
        'Married' => 'Married',
        'Widowed' => 'Widowed',
        'Divorced/Separated' => 'Divorced/Separated',
        'Prefer not to say' => 'Prefer not to say',
    ];

    public const NOTIFICATION_CATEGORIES = [
        'general' => [
            'label' => 'Church announcements',
            'description' => 'General messages from the church admin and ministry office.',
            'default' => true,
        ],
        'events' => [
            'label' => 'Events and programmes',
            'description' => 'Event reminders, programme updates, registrations, and schedule notices.',
            'default' => true,
        ],
        'media' => [
            'label' => 'Audio, video, and livestreams',
            'description' => 'New sermons, videos, livestream updates, and media recommendations.',
            'default' => true,
        ],
        'prayer_wall' => [
            'label' => 'Interactive prayer wall',
            'description' => 'Prayer wall activity, prayer responses, moderation, and community prayer updates.',
            'default' => true,
        ],
        'prophetic_decree' => [
            'label' => 'Daily prophetic decree',
            'description' => 'Daily decree alerts and audio reminders from the G.O.',
            'default' => true,
        ],
        'testimonies' => [
            'label' => 'Testimonies and thanksgiving',
            'description' => 'Testimony review updates and thanksgiving wall activity.',
            'default' => true,
        ],
        'accommodation' => [
            'label' => 'Accommodation bookings',
            'description' => 'Booking receipts, payment updates, confirmations, and checkout reminders.',
            'default' => true,
        ],
        'groups' => [
            'label' => 'Church groups',
            'description' => 'Group joining requests, approvals, and group member updates.',
            'default' => true,
        ],
        'giving' => [
            'label' => 'Giving',
            'description' => 'Donation and giving-related updates.',
            'default' => true,
        ],
        'devotionals' => [
            'label' => 'Devotionals',
            'description' => 'Daily devotional reading reminders and updates.',
            'default' => true,
        ],
    ];

    public function communityPrayerRequests(): HasMany
    {
        return $this->hasMany(CommunityPrayerRequest::class);
    }

    public function testimonies(): HasMany
    {
        return $this->hasMany(Testimony::class);
    }

    public function propheticDecrees(): HasMany
    {
        return $this->hasMany(PropheticDecree::class, 'go_user_id');
    }

    public function churchGroup(): BelongsTo
    {
        return $this->belongsTo(ChurchGroup::class, 'group_id');
    }

    public function walletSecurityResetRequests(): HasMany
    {
        return $this->hasMany(WalletSecurityResetRequest::class, 'mobile_user_id');
    }

    public function goshenReferralCode(): HasOne
    {
        return $this->hasOne(GoshenReferralCode::class, 'mobile_user_id');
    }

    public function goshenReferralPointEntries(): HasMany
    {
        return $this->hasMany(GoshenReferralPointEntry::class, 'referrer_mobile_user_id');
    }

    public function pendingWalletSecurityResetRequest(): HasOne
    {
        return $this->hasOne(WalletSecurityResetRequest::class, 'mobile_user_id')
            ->where('status', WalletSecurityResetRequest::STATUS_PENDING)
            ->latestOfMany();
    }

    public function latestWalletSecurityResetRequest(): HasOne
    {
        return $this->hasOne(WalletSecurityResetRequest::class, 'mobile_user_id')
            ->latestOfMany();
    }

    public function issueApiToken(): string
    {
        $token = Str::random(80);

        $this->forceFill([
            'api_token_hash' => hash('sha256', $token),
            'last_login_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        return $token;
    }

    public function assignRole(...$roles)
    {
        app(TriumphantIdService::class)->assertReservedMobileRoleArgumentsAvailable($roles, $this->exists ? $this : null);
        $assignAfterSave = ! $this->exists;
        $result = $this->spatieAssignRole(...$roles);

        if ($assignAfterSave) {
            $this->assignTriumphantIdAfterSave();
        } elseif ($this->exists) {
            app(TriumphantIdService::class)->assignFor($this);
        }

        return $result;
    }

    public function syncRoles(...$roles)
    {
        app(TriumphantIdService::class)->assertReservedMobileRoleArgumentsAvailable($roles, $this->exists ? $this : null);
        $assignAfterSave = ! $this->exists;
        $result = $this->spatieSyncRoles(...$roles);

        if ($assignAfterSave) {
            $this->assignTriumphantIdAfterSave();
        } elseif ($this->exists) {
            app(TriumphantIdService::class)->assignFor($this);
        }

        return $result;
    }

    private function assignTriumphantIdAfterSave(): void
    {
        $model = $this;
        $assigned = false;

        static::saved(function (MobileUser $saved) use ($model, &$assigned): void {
            if ($assigned || $model->getKey() !== $saved->getKey()) {
                return;
            }

            app(TriumphantIdService::class)->assignFor($saved);
            $assigned = true;
        });
    }

    public function markApiSeen(): void
    {
        if ($this->last_seen_at?->gt(now()->subMinutes(5))) {
            return;
        }

        $this->forceFill(['last_seen_at' => now()])->saveQuietly();
    }

    public static function defaultNotificationPreferences(): array
    {
        return collect(self::NOTIFICATION_CATEGORIES)
            ->mapWithKeys(fn (array $category, string $key): array => [$key => (bool) ($category['default'] ?? true)])
            ->all();
    }

    public static function notificationPreferenceDefinitions(): array
    {
        return collect(self::NOTIFICATION_CATEGORIES)
            ->map(fn (array $category, string $key): array => [
                'key' => $key,
                'label' => $category['label'],
                'description' => $category['description'],
                'default_enabled' => (bool) ($category['default'] ?? true),
            ])
            ->values()
            ->all();
    }

    public function effectiveNotificationPreferences(): array
    {
        $stored = is_array($this->notification_preferences) ? $this->notification_preferences : [];
        $normalized = collect(array_intersect_key($stored, self::defaultNotificationPreferences()))
            ->map(fn ($value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false)
            ->all();

        return array_replace(self::defaultNotificationPreferences(), $normalized);
    }

    public function wantsNotificationCategory(?string $category): bool
    {
        $category = $category ?: 'general';

        if (! array_key_exists($category, self::NOTIFICATION_CATEGORIES)) {
            $category = 'general';
        }

        return (bool) ($this->effectiveNotificationPreferences()[$category] ?? true);
    }

    public function canUseCommunity(): bool
    {
        return $this->is_verified && ! $this->is_blocked && ! $this->is_deleted;
    }

    public function canManagePropheticDecree(): bool
    {
        return $this->canUseCommunity() && $this->hasPropheticDecreeRole();
    }

    public function hasPropheticDecreeRole(): bool
    {
        if ($this->hasGeneralOverseerRole() || $this->hasAnyRole(['Triumphant Main pastor', 'Triumphant main pastor'])) {
            return true;
        }

        return $this->roles()
            ->pluck('name')
            ->contains(fn ($role) => in_array(
                str($role)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                ['go', 'gorole', 'generaloverseer', 'generaloverseerrole', 'propheticdecreego', 'propheticdecreegorole', 'triumphantmainpastor'],
                true,
            ));
    }

    public function hasGeneralOverseerRole(): bool
    {
        if ($this->hasAnyRole(['G.O', 'GO', 'General Overseer'])) {
            return true;
        }

        return $this->roles()
            ->pluck('name')
            ->contains(fn ($role) => in_array(
                str($role)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                ['go', 'gorole', 'generaloverseer', 'generaloverseerrole', 'propheticdecreego', 'propheticdecreegorole'],
                true,
            ));
    }

    public function isScannerSuspended(): bool
    {
        return $this->scanner_suspended_at !== null;
    }
}
