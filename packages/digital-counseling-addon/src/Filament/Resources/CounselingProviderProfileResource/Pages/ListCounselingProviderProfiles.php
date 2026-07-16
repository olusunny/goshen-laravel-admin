<?php

namespace ChurchTools\DigitalCounseling\Filament\Resources\CounselingProviderProfileResource\Pages;

use ChurchTools\DigitalCounseling\Filament\Resources\CounselingProviderProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCounselingProviderProfiles extends ListRecords
{
    protected static string $resource = CounselingProviderProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
