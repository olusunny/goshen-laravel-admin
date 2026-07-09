<?php

namespace App\Filament\Resources\GoshenAccommodationAllocationResource\Pages;

use App\Filament\Resources\GoshenAccommodationAllocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGoshenAccommodationAllocations extends ListRecords
{
    protected static string $resource = GoshenAccommodationAllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
