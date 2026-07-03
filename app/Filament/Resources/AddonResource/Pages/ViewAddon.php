<?php

namespace App\Filament\Resources\AddonResource\Pages;

use App\Filament\Resources\AddonResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAddon extends ViewRecord
{
    protected static string $resource = AddonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AddonResource::activateAction(),
            AddonResource::deactivateAction(),
            AddonResource::healthAction(),
            AddonResource::uninstallAction(),
        ];
    }
}
