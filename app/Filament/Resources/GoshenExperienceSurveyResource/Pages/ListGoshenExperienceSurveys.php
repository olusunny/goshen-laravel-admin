<?php

namespace App\Filament\Resources\GoshenExperienceSurveyResource\Pages;

use App\Filament\Resources\GoshenExperienceSurveyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGoshenExperienceSurveys extends ListRecords
{
    protected static string $resource = GoshenExperienceSurveyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
