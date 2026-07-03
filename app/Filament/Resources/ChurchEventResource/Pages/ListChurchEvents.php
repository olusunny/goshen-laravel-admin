<?php

namespace App\Filament\Resources\ChurchEventResource\Pages;

use App\Filament\Resources\ChurchEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChurchEvents extends ListRecords
{
    protected static string $resource = ChurchEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
