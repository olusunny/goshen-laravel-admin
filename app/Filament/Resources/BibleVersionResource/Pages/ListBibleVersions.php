<?php

namespace App\Filament\Resources\BibleVersionResource\Pages;

use App\Filament\Resources\BibleVersionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBibleVersions extends ListRecords
{
    protected static string $resource = BibleVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
