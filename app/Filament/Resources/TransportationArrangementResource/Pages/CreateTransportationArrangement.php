<?php

namespace App\Filament\Resources\TransportationArrangementResource\Pages;

use App\Filament\Resources\TransportationArrangementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransportationArrangement extends CreateRecord
{
    protected static string $resource = TransportationArrangementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return TransportationArrangementResource::syncLegacyContactFields($data);
    }
}
