<?php

namespace App\Filament\Resources\ChurchGroupResource\Pages;

use App\Filament\Resources\ChurchGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChurchGroups extends ListRecords
{
    protected static string $resource = ChurchGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
