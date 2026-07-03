<?php

namespace App\Filament\Resources\GoshenTicketTypeResource\Pages;

use App\Filament\Resources\GoshenTicketTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGoshenTicketTypes extends ListRecords
{
    protected static string $resource = GoshenTicketTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
