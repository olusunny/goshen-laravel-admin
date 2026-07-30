<?php

namespace App\Filament\Resources\GoshenRetreatMaterialResource\Pages;

use App\Filament\Resources\GoshenRetreatMaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGoshenRetreatMaterial extends EditRecord
{
    protected static string $resource = GoshenRetreatMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
