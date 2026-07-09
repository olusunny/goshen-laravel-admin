<?php

namespace App\Filament\Resources\AppSettingResource\Pages;

use App\Filament\Resources\AppSettingResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListAppSettings extends ListRecords
{
    protected static string $resource = AppSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('clearCache')
                ->label('Clear cache')
                ->icon('heroicon-o-trash')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Clear server cache?')
                ->modalDescription('This clears Laravel application, config, route, event, and view caches. It is useful after content, settings, or deployment changes.')
                ->action(function (): void {
                    Artisan::call('optimize:clear');

                    Notification::make()
                        ->title('Server cache cleared')
                        ->body('Application, config, route, event, and view caches were cleared successfully.')
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
