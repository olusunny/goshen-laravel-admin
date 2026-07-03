<?php

namespace App\Filament\Resources\AutomaticNotificationResource\Pages;

use App\Filament\Resources\AutomaticNotificationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAutomaticNotifications extends ListRecords
{
    protected static string $resource = AutomaticNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
