<?php

namespace App\Filament\Resources\GoshenQuizResource\Pages;

use App\Filament\Resources\GoshenQuizResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGoshenQuizzes extends ListRecords
{
    protected static string $resource = GoshenQuizResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
