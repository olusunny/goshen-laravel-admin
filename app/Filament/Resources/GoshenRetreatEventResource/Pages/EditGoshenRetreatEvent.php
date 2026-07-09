<?php

namespace App\Filament\Resources\GoshenRetreatEventResource\Pages;

use App\Filament\Resources\GoshenRetreatEventResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGoshenRetreatEvent extends EditRecord
{
    protected static string $resource = GoshenRetreatEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $settings['module'] = 'goshen_retreat';
        $data['settings'] = $settings;

        return $data;
    }
}
