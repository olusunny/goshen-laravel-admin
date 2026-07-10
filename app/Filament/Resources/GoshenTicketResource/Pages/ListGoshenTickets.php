<?php

namespace App\Filament\Resources\GoshenTicketResource\Pages;

use App\Filament\Resources\GoshenTicketResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGoshenTickets extends ListRecords
{
    protected static string $resource = GoshenTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Issue ticket')
                ->icon('heroicon-o-ticket'),
        ];
    }
}
