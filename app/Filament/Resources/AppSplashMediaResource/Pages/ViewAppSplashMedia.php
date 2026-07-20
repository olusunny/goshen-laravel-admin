<?php

namespace App\Filament\Resources\AppSplashMediaResource\Pages;

use App\Filament\Resources\AppSplashMediaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAppSplashMedia extends ViewRecord
{
    protected static string $resource = AppSplashMediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
