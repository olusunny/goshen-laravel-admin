<?php

namespace Sunny\Fundraising\Filament\Resources\CampaignMediaResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Sunny\Fundraising\Filament\Resources\CampaignMediaResource;

class EditCampaignMedia extends EditRecord
{
    protected static string $resource = CampaignMediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
