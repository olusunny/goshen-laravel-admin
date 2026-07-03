<?php

namespace App\Filament\Resources\MobileUserResource\Pages;

use App\Filament\Resources\MobileUserResource;
use App\Models\MobileUser;
use App\Services\TriumphantIdService;
use Filament\Resources\Pages\CreateRecord;

class CreateMobileUser extends CreateRecord
{
    protected static string $resource = MobileUserResource::class;

    protected function afterCreate(): void
    {
        if ($this->record instanceof MobileUser) {
            app(TriumphantIdService::class)->assignFor($this->record);
        }
    }
}
