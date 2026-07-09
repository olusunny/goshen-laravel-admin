<?php

namespace App\Filament\Resources\MediaItemResource\Pages;

use App\Filament\Resources\MediaItemResource;
use App\Services\MediaVideoCoverGenerator;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateMediaItem extends CreateRecord
{
    protected static string $resource = MediaItemResource::class;

    protected function afterCreate(): void
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
