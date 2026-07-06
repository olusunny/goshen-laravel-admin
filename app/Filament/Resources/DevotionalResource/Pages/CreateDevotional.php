<?php

namespace App\Filament\Resources\DevotionalResource\Pages;

use App\Filament\Resources\DevotionalResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDevotional extends CreateRecord
{
    protected static string $resource = DevotionalResource::class;

    protected function afterCreate(): void
    {
        $state = $this->form->getRawState();

        if ((bool) ($state['send_push_after_save'] ?? false)) {
            DevotionalResource::sendPushNotification($this->record);
        }
    }
}
