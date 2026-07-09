<?php

namespace App\Filament\Resources\HymnResource\Pages;

use App\Filament\Resources\HymnResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHymns extends ListRecords
{
    protected static string $resource = HymnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
