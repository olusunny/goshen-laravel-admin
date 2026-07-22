<?php

namespace ChurchTools\GoshenPrayerAttendance\Filament\Resources\PrayerSessionResource\Pages;

use ChurchTools\GoshenPrayerAttendance\Filament\Resources\PrayerSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPrayerSession extends ViewRecord
{
    protected static string $resource = PrayerSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PrayerSessionResource::activateAction(),
            PrayerSessionResource::closeAction(),
            PrayerSessionResource::reopenAction(),
            PrayerSessionResource::remindAction(),
            PrayerSessionResource::previewQrAction(),
            PrayerSessionResource::downloadQrAction(),
            PrayerSessionResource::correctionAction(),
            Actions\EditAction::make(),
        ];
    }
}
