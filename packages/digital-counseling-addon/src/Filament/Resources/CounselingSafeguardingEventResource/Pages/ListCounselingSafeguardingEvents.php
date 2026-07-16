<?php

namespace ChurchTools\DigitalCounseling\Filament\Resources\CounselingSafeguardingEventResource\Pages;

use ChurchTools\DigitalCounseling\Filament\Resources\CounselingSafeguardingEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCounselingSafeguardingEvents extends ListRecords
{
    protected static string $resource = CounselingSafeguardingEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
