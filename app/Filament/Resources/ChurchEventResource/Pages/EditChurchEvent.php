<?php

namespace App\Filament\Resources\ChurchEventResource\Pages;

use App\Filament\Resources\ChurchEventResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChurchEvent extends EditRecord
{
    protected static string $resource = ChurchEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
