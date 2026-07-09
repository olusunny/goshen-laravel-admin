<?php

namespace App\Filament\Resources\DonationCategoryResource\Pages;

use App\Filament\Resources\DonationCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDonationCategory extends EditRecord
{
    protected static string $resource = DonationCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
