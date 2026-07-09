<?php

namespace App\Filament\Resources\AddonResource\Pages;

use App\Filament\Resources\AddonResource;
use App\Services\Addons\AddonLifecycleService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ListAddons extends ListRecords
{
    protected static string $resource = AddonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('uploadAddon')
                ->label('Upload add-on ZIP')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Forms\Components\FileUpload::make('zip')
                        ->label('Add-on ZIP')
                        ->disk(config('addons.storage.disk', 'local'))
                        ->directory(config('addons.storage.uploads_path', 'addons/uploads'))
                        ->acceptedFileTypes(config('addons.zip.allowed_mimes', ['application/zip']))
                        ->maxSize(config('addons.zip.max_size_kb', 51200))
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $path = (string) ($data['zip'] ?? '');
                    $absolutePath = Storage::disk(config('addons.storage.disk', 'local'))->path($path);
                    app(AddonLifecycleService::class)->installFromZip($absolutePath, Auth::user());

                    Notification::make()
                        ->title('Add-on installed and activated')
                        ->success()
                        ->send();
                }),
        ];
    }
}
