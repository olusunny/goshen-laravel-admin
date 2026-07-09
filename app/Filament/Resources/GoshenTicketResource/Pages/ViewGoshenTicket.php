<?php

namespace App\Filament\Resources\GoshenTicketResource\Pages;

use App\Filament\Resources\GoshenTicketResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Personal\EventInstallments\Services\TicketNotificationService;

class ViewGoshenTicket extends ViewRecord
{
    protected static string $resource = GoshenTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sendTicketEmail')
                ->label('Email ticket')
                ->icon('heroicon-o-envelope')
                ->color('success')
                ->modalHeading('Send Goshen ticket')
                ->modalDescription('Send this ticket to the registered attendee email or enter another email address.')
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
                    $log = $notifications->sendTicket($this->record, $recipient);

                    if ($log->status === 'sent') {
                        Notification::make()
                            ->title('Ticket email sent')
                            ->body('The ticket was sent to ' . $log->recipient . '.')
                            ->success()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Ticket email could not be sent')
                        ->body($log->error ?: 'The mail service rejected the ticket email. Please check SMTP settings and try again.')
                        ->danger()
                        ->send();
                }),
        ];
    }
}
