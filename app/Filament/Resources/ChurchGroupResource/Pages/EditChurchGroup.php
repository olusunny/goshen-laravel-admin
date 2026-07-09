<?php

namespace App\Filament\Resources\ChurchGroupResource\Pages;

use App\Filament\Resources\ChurchGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChurchGroup extends EditRecord
{
    protected static string $resource = ChurchGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
