<?php

namespace App\Filament\Resources\GoshenExperienceSurveyResource\Pages;

use App\Filament\Resources\GoshenExperienceSurveyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGoshenExperienceSurvey extends CreateRecord
{
    protected static string $resource = GoshenExperienceSurveyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_id'] = auth()->id();

        return $data;
    }
}
