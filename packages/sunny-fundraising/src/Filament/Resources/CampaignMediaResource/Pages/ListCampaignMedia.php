<?php

namespace Sunny\Fundraising\Filament\Resources\CampaignMediaResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Sunny\Fundraising\Filament\Resources\CampaignMediaResource;

class ListCampaignMedia extends ListRecords
{
    protected static string $resource = CampaignMediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
