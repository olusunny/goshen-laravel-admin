<?php

namespace App\Filament\Resources\VerseOfDayResource\Pages;

use App\Filament\Resources\VerseOfDayResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVerseOfDays extends ListRecords
{
    protected static string $resource = VerseOfDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
