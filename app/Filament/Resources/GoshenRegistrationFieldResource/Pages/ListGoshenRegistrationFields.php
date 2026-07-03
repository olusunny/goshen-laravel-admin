<?php

namespace App\Filament\Resources\GoshenRegistrationFieldResource\Pages;

use App\Filament\Resources\GoshenRegistrationFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGoshenRegistrationFields extends ListRecords
{
    protected static string $resource = GoshenRegistrationFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
