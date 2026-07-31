<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdaySettingResource\Pages;

use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdaySettingResource;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdaySetting;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Widgets\Widget;

class ListBirthdaySettings extends ListRecords
{
    protected static string $resource = BirthdaySettingResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->visible(fn (): bool => BirthdaySetting::query()->count() < 4)];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
