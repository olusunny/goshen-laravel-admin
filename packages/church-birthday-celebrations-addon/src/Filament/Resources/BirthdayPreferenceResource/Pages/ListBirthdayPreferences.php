<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayPreferenceResource\Pages;

use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayPreferenceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBirthdayPreferences extends ListRecords
{
    protected static string $resource = BirthdayPreferenceResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
