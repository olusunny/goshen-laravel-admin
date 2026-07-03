<?php

namespace App\Filament\Resources\CommunityPrayerRequestResource\Pages;

use App\Filament\Resources\CommunityPrayerRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCommunityPrayerRequest extends EditRecord
{
    protected static string $resource = CommunityPrayerRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
