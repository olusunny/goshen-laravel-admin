<?php

namespace App\Filament\Resources\GoshenRetreatEventResource\Pages;

use App\Filament\Resources\GoshenRetreatEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGoshenRetreatEvents extends ListRecords
{
    protected static string $resource = GoshenRetreatEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
