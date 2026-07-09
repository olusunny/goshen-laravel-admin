<?php

namespace App\Filament\Resources\DonationAccountCategoryResource\Pages;

use App\Filament\Resources\DonationAccountCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDonationAccountCategories extends ListRecords
{
    protected static string $resource = DonationAccountCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
