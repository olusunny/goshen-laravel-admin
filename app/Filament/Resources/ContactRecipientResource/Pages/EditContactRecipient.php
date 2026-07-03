<?php

namespace App\Filament\Resources\ContactRecipientResource\Pages;

use App\Filament\Resources\ContactRecipientResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContactRecipient extends EditRecord
{
    protected static string $resource = ContactRecipientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
