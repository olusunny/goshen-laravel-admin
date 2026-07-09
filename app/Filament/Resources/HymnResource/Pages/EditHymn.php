<?php

namespace App\Filament\Resources\HymnResource\Pages;

use App\Filament\Resources\HymnResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHymn extends EditRecord
{
    protected static string $resource = HymnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
