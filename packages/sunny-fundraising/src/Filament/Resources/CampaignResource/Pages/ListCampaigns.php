<?php

namespace Sunny\Fundraising\Filament\Resources\CampaignResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Sunny\Fundraising\Filament\Resources\CampaignResource;

class ListCampaigns extends ListRecords
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
