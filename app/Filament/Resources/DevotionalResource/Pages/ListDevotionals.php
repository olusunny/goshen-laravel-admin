<?php

namespace App\Filament\Resources\DevotionalResource\Pages;

use App\Filament\Resources\DevotionalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDevotionals extends ListRecords
{
    protected static string $resource = DevotionalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
