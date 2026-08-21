<?php

namespace App\Filament\Resources\GoshenYouTubeConnectionResource\Pages;

use App\Filament\Resources\GoshenYouTubeConnectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGoshenYouTubeConnections extends ListRecords
{
    protected static string $resource = GoshenYouTubeConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('connect')
                ->label('Connect YouTube channel')
                ->icon('heroicon-o-link')
                ->url(route('admin.goshen-youtube.connect')),
        ];
    }
}
