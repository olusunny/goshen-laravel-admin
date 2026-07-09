<?php

namespace App\Filament\Resources\TransportationArrangementResource\Pages;

use App\Filament\Resources\TransportationArrangementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransportationArrangement extends EditRecord
{
    protected static string $resource = TransportationArrangementResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (empty($data['contacts']) && (! empty($data['contact_person_name']) || ! empty($data['contact_person_phone']))) {
            $data['contacts'] = [[
                'name' => $data['contact_person_name'] ?? '',
                'phone' => $data['contact_person_phone'] ?? '',
            ]];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return TransportationArrangementResource::syncLegacyContactFields($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
