<?php

namespace App\Filament\Pages;

use App\Filament\Resources\RoleResource;
use App\Services\AdminAccessService;
use Filament\Pages\Page;

class AdminMenuSettings extends Page
{
    protected static ?string $slug = 'admin-menu-settings';

    protected string $view = 'filament.pages.admin-menu-settings';

    public static function canAccess(): bool
    {
        return AdminAccessService::isSuperAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->redirect(RoleResource::getUrl());
    }

    public function save(): void
    {
        // Reject saves from a legacy form left open before the transition.
        abort(403, 'Menu access is now controlled by role and individual permissions.');
    }
}
