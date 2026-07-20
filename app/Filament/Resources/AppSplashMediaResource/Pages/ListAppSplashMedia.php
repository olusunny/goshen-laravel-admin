<?php

namespace App\Filament\Resources\AppSplashMediaResource\Pages;

use App\Filament\Resources\AppSplashMediaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAppSplashMedia extends ListRecords
{
    protected static string $resource = AppSplashMediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Upload splash media'),
        ];
    }
}
