<?php

namespace App\Filament\Resources\MobileUserResource\Pages;

use App\Filament\Resources\MobileUserResource;
use App\Models\MobileUser;
use App\Services\TriumphantIdService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMobileUser extends EditRecord
{
    protected static string $resource = MobileUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        if ($this->record instanceof MobileUser) {
            if ($this->record->is_deleted) {
                app(TriumphantIdService::class)->release($this->record);

                return;
            }

            app(TriumphantIdService::class)->assignFor($this->record);
        }
    }
}
