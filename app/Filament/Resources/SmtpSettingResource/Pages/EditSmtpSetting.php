<?php

namespace App\Filament\Resources\SmtpSettingResource\Pages;

use App\Filament\Resources\SmtpSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSmtpSetting extends EditRecord
{
    protected static string $resource = SmtpSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
