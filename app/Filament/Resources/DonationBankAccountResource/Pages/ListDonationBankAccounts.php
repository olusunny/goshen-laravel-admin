<?php

namespace App\Filament\Resources\DonationBankAccountResource\Pages;

use App\Filament\Resources\DonationBankAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDonationBankAccounts extends ListRecords
{
    protected static string $resource = DonationBankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
