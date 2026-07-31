<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayVerseResource\Pages;

use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayVerseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBirthdayVerse extends EditRecord
{
    protected static string $resource = BirthdayVerseResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()->requiresConfirmation()]; }
}
