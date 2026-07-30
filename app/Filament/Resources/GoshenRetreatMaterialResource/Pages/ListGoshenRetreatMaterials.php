<?php

namespace App\Filament\Resources\GoshenRetreatMaterialResource\Pages;

use App\Filament\Resources\GoshenRetreatMaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGoshenRetreatMaterials extends ListRecords
{
    protected static string $resource = GoshenRetreatMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
