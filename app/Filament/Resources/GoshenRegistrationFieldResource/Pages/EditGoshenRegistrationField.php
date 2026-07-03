<?php

namespace App\Filament\Resources\GoshenRegistrationFieldResource\Pages;

use App\Filament\Resources\GoshenRegistrationFieldResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGoshenRegistrationField extends EditRecord
{
    protected static string $resource = GoshenRegistrationFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
