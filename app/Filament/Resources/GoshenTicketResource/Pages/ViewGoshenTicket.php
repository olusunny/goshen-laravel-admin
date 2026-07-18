<?php

namespace App\Filament\Resources\GoshenTicketResource\Pages;

use App\Filament\Resources\GoshenTicketResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Personal\EventInstallments\Services\TicketNotificationService;

class ViewGoshenTicket extends ViewRecord
{
    protected static string $resource = GoshenTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sendTicketEmail')
                ->label('Send/resend PDF ticket')
                ->icon('heroicon-o-envelope')
                ->color('success')
                ->modalHeading('Send or resend Goshen ticket')
                ->modalDescription('Send the ticket details with the generated PDF ticket attached to the registered attendee email, or enter another email address.')
                ->modalSubmitActionLabel('Send ticket')
                ->form([
                    Forms\Components\TextInput::make('recipient')
                        ->label('Recipient email')
                        ->email()
                        ->required()
                        ->default(fn (): string => (string) ($this->record->attendee?->email ?: $this->record->booking?->customer_email ?: ''))
                        ->helperText('Defaults to the ticket owner when an email is available. You may replace it with another email address.'),
                ])
                ->action(function (array $data, TicketNotificationService $notifications): void {
                    $recipient = trim((string) ($data['recipient'] ?? ''));

                    GoshenTicketResource::sendTicketEmail($this->record, $recipient, $notifications);
                }),
        ];
    }
}
