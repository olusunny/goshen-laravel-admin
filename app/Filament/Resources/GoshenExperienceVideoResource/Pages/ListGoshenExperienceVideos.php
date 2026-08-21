<?php

namespace App\Filament\Resources\GoshenExperienceVideoResource\Pages;

use App\Filament\Resources\GoshenExperienceVideoResource;
use App\Filament\Widgets\GoshenExperienceVideoQueueOverview;
use Filament\Resources\Pages\ListRecords;

class ListGoshenExperienceVideos extends ListRecords
{
    protected static string $resource = GoshenExperienceVideoResource::class;

    protected function getHeaderWidgets(): array
    {
        return [GoshenExperienceVideoQueueOverview::class];
    }
}
