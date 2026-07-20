<?php

namespace App\Filament\Resources\AppSplashMediaResource\Pages;

use App\Filament\Resources\AppSplashMediaResource;
use App\Services\SplashMediaService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditAppSplashMedia extends EditRecord
{
    protected static string $resource = AppSplashMediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('activate')
                ->label('Activate')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => ! $this->getRecord()->active)
                ->action(fn (): mixed => AppSplashMediaResource::activateRecord($this->getRecord())),
            Actions\DeleteAction::make()
                ->visible(fn (): bool => ! $this->getRecord()->active || $this->getModel()::query()->count() === 1),
        ];
    }

    protected function afterSave(): void
    {
        app(SplashMediaService::class)->refreshMetadata($this->record);

        if ($this->record->active) {
            app(SplashMediaService::class)->activate($this->record, Auth::user());
        }
    }
}
