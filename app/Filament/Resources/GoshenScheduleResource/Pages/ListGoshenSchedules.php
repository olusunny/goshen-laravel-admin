<?php

namespace App\Filament\Resources\GoshenScheduleResource\Pages;

use App\Filament\Resources\GoshenScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGoshenSchedules extends ListRecords
{
    protected static string $resource = GoshenScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
