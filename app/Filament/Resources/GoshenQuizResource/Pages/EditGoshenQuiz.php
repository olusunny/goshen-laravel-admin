<?php

namespace App\Filament\Resources\GoshenQuizResource\Pages;

use App\Filament\Resources\GoshenQuizResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGoshenQuiz extends EditRecord
{
    protected static string $resource = GoshenQuizResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
