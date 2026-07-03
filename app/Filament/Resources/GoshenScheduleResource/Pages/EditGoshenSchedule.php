<?php

namespace App\Filament\Resources\GoshenScheduleResource\Pages;

use App\Filament\Resources\GoshenScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGoshenSchedule extends EditRecord
{
    protected static string $resource = GoshenScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
