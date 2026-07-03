<?php

namespace App\Filament\Resources\AppSettingResource\Pages;

use App\Filament\Resources\AppSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAppSetting extends EditRecord
{
    protected static string $resource = AppSettingResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return AppSettingResource::prepareVirtualValueFields($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return AppSettingResource::collapseVirtualValueFields($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
