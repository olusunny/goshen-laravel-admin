<?php

namespace App\Filament\Resources\ContactRecipientResource\Pages;

use App\Filament\Resources\ContactRecipientResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContactRecipients extends ListRecords
{
    protected static string $resource = ContactRecipientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
