<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayTemplateResource\Pages;

use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBirthdayTemplates extends ListRecords
{
    protected static string $resource = BirthdayTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return BirthdayTemplateResource::canManageBirthdayCelebrations()
            ? [Actions\CreateAction::make()]
            : [];
    }
}
