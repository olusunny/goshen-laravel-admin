<?php

namespace App\Filament\Resources\DevotionalResource\Pages;

use App\Filament\Resources\DevotionalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDevotional extends EditRecord
{
    protected static string $resource = DevotionalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $state = $this->form->getRawState();

        if ((bool) ($state['send_push_after_save'] ?? false)) {
            DevotionalResource::sendPushNotification($this->record);
        }
    }
}
