<?php

namespace App\Filament\Resources\GoshenExperienceResponseResource\Pages;

use App\Filament\Resources\GoshenExperienceResponseResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewGoshenExperienceResponse extends ViewRecord
{
    protected static string $resource = GoshenExperienceResponseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
