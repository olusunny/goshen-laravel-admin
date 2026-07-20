<?php

namespace App\Filament\Resources\AppSplashMediaResource\Pages;

use App\Filament\Resources\AppSplashMediaResource;
use App\Services\SplashMediaService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateAppSplashMedia extends CreateRecord
{
    protected static string $resource = AppSplashMediaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_id'] ??= Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        app(SplashMediaService::class)->refreshMetadata($this->record);

        if ($this->record->active) {
            app(SplashMediaService::class)->activate($this->record, Auth::user());
        }
    }
}
