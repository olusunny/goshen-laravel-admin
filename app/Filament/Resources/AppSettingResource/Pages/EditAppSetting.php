<?php

namespace App\Filament\Resources\AppSettingResource\Pages;

use App\Filament\Resources\AppSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAppSetting extends EditRecord
{
    protected static string $resource = AppSettingResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function afterSave(): void
    {
        $this->fillForm();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return AppSettingResource::prepareVirtualValueFields($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = AppSettingResource::collapseVirtualValueFields($data);
        if ($this->getRecord()->is_secret && blank($data['value'] ?? null)) {
            unset($data['value']);
        }
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
