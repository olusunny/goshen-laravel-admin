<?php

namespace App\Filament\Resources\AiProviderSettingResource\Pages;

use App\Filament\Resources\AiProviderSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAiProviderSettings extends ListRecords
{
    protected static string $resource = AiProviderSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
