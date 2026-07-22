<?php

namespace ChurchTools\GoshenPrayerAttendance\Filament\Resources\PrayerSessionResource\Pages;

use ChurchTools\GoshenPrayerAttendance\Filament\Resources\PrayerSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPrayerSessions extends ListRecords
{
    protected static string $resource = PrayerSessionResource::class;

    protected function getHeaderActions(): array
    {
        return PrayerSessionResource::canManagePrayerSessions()
            ? [Actions\CreateAction::make()->label('Create prayer session')->icon('heroicon-o-plus')]
            : [];
    }
}
