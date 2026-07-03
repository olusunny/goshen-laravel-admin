<?php

namespace App\Filament\Resources\GoshenQuizCelebrationMediaResource\Pages;

use App\Filament\Resources\GoshenQuizCelebrationMediaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGoshenQuizCelebrationMedia extends EditRecord
{
    protected static string $resource = GoshenQuizCelebrationMediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
