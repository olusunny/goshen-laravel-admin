<?php

namespace ChurchTools\DigitalCounseling\Filament\Resources\CounselingCountryResourceResource\Pages;

use ChurchTools\DigitalCounseling\Filament\Resources\CounselingCountryResourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCounselingCountryResources extends ListRecords
{
    protected static string $resource = CounselingCountryResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
