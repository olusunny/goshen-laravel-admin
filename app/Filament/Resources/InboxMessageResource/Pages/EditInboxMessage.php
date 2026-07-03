<?php

namespace App\Filament\Resources\InboxMessageResource\Pages;

use App\Filament\Resources\InboxMessageResource;
use App\Services\InboxMessageDeliveryService;
use App\Services\PrayerAiService;
use App\Services\ScheduledInboxMessageService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditInboxMessage extends EditRecord
{
    protected static string $resource = InboxMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('aiDraft')
                ->label('AI draft message')
                ->icon('heroicon-o-sparkles')
                ->form([
                    Forms\Components\Textarea::make('purpose')
                        ->label('What should this message say?')
                        ->rows(5)
                        ->required()
                        ->helperText('Describe the announcement, reminder, survey request, or prayerful message. You can edit the generated draft before sending.'),
                    Forms\Components\Toggle::make('include_verse')
                        ->label('Ask AI to add a relevant Bible verse'),
                ])
                ->action(function (array $data): void {
                    $draft = app(PrayerAiService::class)->draftMinistryMessage(
                        (string) $data['purpose'],
                        (bool) ($data['include_verse'] ?? false),
                        'inbox and push notification'
                    );

                    $this->form->fill(array_merge($this->form->getRawState(), [
                        'title' => $draft['subject'],
                        'content' => nl2br(e($draft['body'])),
                    ]));

                    Notification::make()
                        ->title('AI draft added')
                        ->body('Review and edit the message before publishing or sending.')
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return InboxMessageResource::normalizePublishingData($data);
    }

    protected function afterSave(): void
    {
        app(ScheduledInboxMessageService::class)->normalizeSchedule($this->record);

        if (! $this->record->schedule_enabled && $this->record->is_published) {
            app(InboxMessageDeliveryService::class)->snapshotRecipients($this->record);
        }
    }
}
