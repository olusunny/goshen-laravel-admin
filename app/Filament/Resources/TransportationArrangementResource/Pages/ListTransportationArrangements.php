<?php

namespace App\Filament\Resources\TransportationArrangementResource\Pages;

use App\Filament\Resources\TransportationArrangementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransportationArrangements extends ListRecords
{
    protected static string $resource = TransportationArrangementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
