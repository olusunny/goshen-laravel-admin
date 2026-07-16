<?php

namespace App\Filament\Resources\AddonResource\Pages;

use App\Filament\Resources\AddonResource;
use App\Services\Addons\AddonLifecycleService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Throwable;

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
                        ->acceptedFileTypes(config('addons.zip.allowed_mimes', ['application/zip']))
                        ->maxSize(config('addons.zip.max_size_kb', 51200))
                        ->storeFiles(false)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        $absolutePath = $this->storeUploadedZipForInstall($data['zip'] ?? null);

                        app(AddonLifecycleService::class)->installFromZip($absolutePath, Auth::user());

                        Notification::make()
                            ->title('Add-on installed and activated')
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('Add-on upload failed')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        throw new Halt;
                    }
                }),
        ];
    }

    private function storeUploadedZipForInstall(mixed $upload): string
    {
        $diskName = config('addons.storage.disk', 'local');
        $directory = trim((string) config('addons.storage.uploads_path', 'addons/uploads'), '/');
        $disk = Storage::disk($diskName);

        if ($upload instanceof TemporaryUploadedFile) {
            $path = $disk->putFileAs(
                $directory,
                $upload,
                Str::ulid().'.zip',
            );

            if (! is_string($path) || $path === '') {
                throw new RuntimeException('The uploaded add-on ZIP could not be stored for installation.');
            }

            return $disk->path($path);
        }

        $path = (string) $upload;
        if ($path === '') {
            throw new RuntimeException('The uploaded add-on ZIP could not be found.');
        }

        return $disk->path($path);
    }
}
