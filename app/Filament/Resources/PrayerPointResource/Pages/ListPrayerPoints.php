<?php

namespace App\Filament\Resources\PrayerPointResource\Pages;

use App\Filament\Resources\PrayerPointResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPrayerPoints extends ListRecords
{
    protected static string $resource = PrayerPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
