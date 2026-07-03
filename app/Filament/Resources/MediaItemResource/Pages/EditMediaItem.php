<?php

namespace App\Filament\Resources\MediaItemResource\Pages;

use App\Filament\Resources\MediaItemResource;
use App\Services\MediaVideoCoverGenerator;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMediaItem extends EditRecord
{
    protected static string $resource = MediaItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $cover = app(MediaVideoCoverGenerator::class)->generateFor($this->record);

        if ($cover) {
            Notification::make()
                ->title('Video cover generated')
                ->body('A cover image was automatically created for this video.')
                ->success()
                ->send();
        }
    }
}
