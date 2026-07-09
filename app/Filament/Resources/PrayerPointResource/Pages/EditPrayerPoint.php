<?php

namespace App\Filament\Resources\PrayerPointResource\Pages;

use App\Filament\Resources\PrayerPointResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPrayerPoint extends EditRecord
{
    protected static string $resource = PrayerPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
