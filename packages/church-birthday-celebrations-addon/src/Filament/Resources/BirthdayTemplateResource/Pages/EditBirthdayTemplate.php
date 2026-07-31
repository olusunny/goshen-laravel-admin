<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayTemplateResource\Pages;

use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBirthdayTemplate extends EditRecord
{
    protected static string $resource = BirthdayTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->requiresConfirmation(),
        ];
    }
}
