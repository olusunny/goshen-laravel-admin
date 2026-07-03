<?php

namespace App\Filament\Resources\DonationBankAccountResource\Pages;

use App\Filament\Resources\DonationBankAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDonationBankAccount extends EditRecord
{
    protected static string $resource = DonationBankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
