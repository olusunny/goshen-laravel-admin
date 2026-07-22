<?php

namespace ChurchTools\GoshenPrayerAttendance\Filament\Resources\Concerns;

use App\Models\Addon;
use App\Support\AdminMenuRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait AuthorizesPrayerAttendanceAdmin
{
    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewPrayerAttendance()
            && AdminMenuRegistry::visibleForResource(static::class);
    }

    public static function canViewAny(): bool
    {
        return static::canViewPrayerAttendance();
    }

    public static function canCreate(): bool
    {
        return static::canManagePrayerSessions();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewPrayerAttendance();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManagePrayerSessions() && $record->status !== 'active';
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canManagePrayerSessions(): bool
    {
        return static::hasPrayerAttendancePermission('manage_sessions');
    }

    public static function canControlPrayerSessions(): bool
    {
        return static::hasPrayerAttendancePermission('control_sessions');
    }

    public static function canViewPrayerAttendanceReports(): bool
    {
        return static::hasPrayerAttendancePermission('view_reports');
    }

    public static function canViewPrayerAttendanceQr(): bool
    {
        return static::hasPrayerAttendancePermission('view_qr');
    }

    public static function canCorrectPrayerAttendance(): bool
    {
        return static::hasPrayerAttendancePermission('correct_attendance', administratorOnly: true);
    }

    public static function canReopenPrayerSessions(): bool
    {
        return static::hasPrayerAttendancePermission('reopen_sessions', administratorOnly: true);
    }

    public static function canSendPrayerAttendanceReminder(): bool
    {
        return static::hasPrayerAttendancePermission('send_reminder');
    }

    protected static function canViewPrayerAttendance(): bool
    {
        return static::addonIsActive()
            && (static::canManagePrayerSessions()
                || static::canControlPrayerSessions()
                || static::canViewPrayerAttendanceReports()
                || static::hasPrayerAttendancePermission('assist_attendance'));
    }

    protected static function hasPrayerAttendancePermission(string $ability, bool $administratorOnly = false): bool
    {
        if (! static::addonIsActive()) {
            return false;
        }

        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($administratorOnly) {
            return false;
        }

        $permissionKeys = [
            'manage_sessions' => 'coordinate',
            'control_sessions' => 'coordinate',
            'view_reports' => 'report',
            'view_qr' => 'coordinate',
            'send_reminder' => 'coordinate',
            'assist_attendance' => 'confirm',
        ];

        $permissionKey = $permissionKeys[$ability] ?? $ability;
        $permission = (string) config('prayer-attendance.permissions.'.$permissionKey, '');

        return $permission !== '' && $user->can($permission);
    }

    protected static function addonIsActive(): bool
    {
        if (! filter_var(config('prayer-attendance.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if (! Schema::hasTable('addons')) {
            return false;
        }

        $packageKey = (string) config('prayer-attendance.package_key', 'church-tools.goshen-prayer-session-attendance');

        return Addon::query()
            ->where('package_key', $packageKey)
            ->where('status', Addon::STATUS_ACTIVE)
            ->exists();
    }
}
