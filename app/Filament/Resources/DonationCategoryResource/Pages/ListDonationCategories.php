<?php

namespace App\Filament\Resources\DonationCategoryResource\Pages;

use App\Filament\Resources\DonationCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDonationCategories extends ListRecords
{
    protected static string $resource = DonationCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
