<?php

namespace App\Filament\Resources\GoshenTicketTypeResource\Pages;

use App\Filament\Resources\GoshenTicketTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGoshenTicketType extends EditRecord
{
    protected static string $resource = GoshenTicketTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
