<?php

namespace App\Filament\Resources\GoshenRetreatEventResource\Pages;

use App\Filament\Resources\GoshenRetreatEventResource;
use App\Services\GoshenRegistrationFieldService;
use Filament\Resources\Pages\CreateRecord;
use Personal\EventInstallments\Models\Event;

class CreateGoshenRetreatEvent extends CreateRecord
{
    protected static string $resource = GoshenRetreatEventResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $settings['module'] = 'goshen_retreat';
        $data['settings'] = $settings;

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record instanceof Event) {
            app(GoshenRegistrationFieldService::class)->ensureDefaultsForEvent($this->record);
        }
    }
}
