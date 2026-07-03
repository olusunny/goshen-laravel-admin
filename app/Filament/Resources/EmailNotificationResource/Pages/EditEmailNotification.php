<?php

namespace App\Filament\Resources\EmailNotificationResource\Pages;

use App\Filament\Resources\EmailNotificationResource;
use App\Services\PrayerAiService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEmailNotification extends EditRecord
{
    protected static string $resource = EmailNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('aiDraft')
                ->label('AI draft email')
                ->icon('heroicon-o-sparkles')
                ->form([
                    Forms\Components\Textarea::make('purpose')
                        ->label('What should this email say?')
                        ->rows(5)
                        ->required()
                        ->helperText('Describe the message. AI will draft it, but you remain in control before sending.'),
                    Forms\Components\Toggle::make('include_verse')
                        ->label('Ask AI to add a relevant Bible verse'),
                ])
                ->action(function (array $data): void {
                    $draft = app(PrayerAiService::class)->draftMinistryMessage(
                        (string) $data['purpose'],
                        (bool) ($data['include_verse'] ?? false),
                        'email'
                    );

                    $this->form->fill(array_merge($this->form->getRawState(), [
                        'subject' => $draft['subject'],
                        'body' => $draft['body'],
                    ]));

                    Notification::make()
                        ->title('AI email draft added')
                        ->body('Review and edit the email before sending.')
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
