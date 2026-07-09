<?php

namespace App\Filament\Resources\GoshenExperienceSurveyResource\Pages;

use App\Filament\Resources\GoshenExperienceSurveyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGoshenExperienceSurvey extends EditRecord
{
    protected static string $resource = GoshenExperienceSurveyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
