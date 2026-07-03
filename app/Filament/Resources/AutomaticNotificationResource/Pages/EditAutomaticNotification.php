<?php

namespace App\Filament\Resources\AutomaticNotificationResource\Pages;

use App\Filament\Resources\AutomaticNotificationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAutomaticNotification extends EditRecord
{
    protected static string $resource = AutomaticNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
