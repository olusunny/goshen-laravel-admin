<?php

namespace App\Filament\Resources\GoshenWalletResource\Pages;

use App\Filament\Resources\GoshenWalletResource;
use App\Services\WalletSecurityResetService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewGoshenWallet extends ViewRecord
{
    protected static string $resource = GoshenWalletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('resetWalletSecurity')
                ->label('Reset wallet security')
                ->icon('heroicon-o-shield-exclamation')
                ->color('danger')
                ->visible(fn (): bool => GoshenWalletResource::canResetWalletSecurity($this->record))
                ->form(GoshenWalletResource::walletSecurityResetForm())
                ->requiresConfirmation()
                ->modalHeading('Reset wallet security')
                ->modalDescription('Use this only after verifying the member through support. The old wallet PIN will not be viewed or recovered. The member must sign in again and create a new wallet PIN.')
                ->modalSubmitActionLabel('Reset wallet security')
                ->action(function (array $data, WalletSecurityResetService $resets): void {
                    GoshenWalletResource::requestWalletSecurityReset($this->record, $data, $resets);
                    $this->record->refresh();
                }),
        ];
    }
}
