<?php

namespace App\Filament\Resources\HymnResource\Pages;

use App\Filament\Resources\HymnResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class EditHymn extends EditRecord
{
    protected static string $resource = HymnResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return HymnResource::administration()->formData($this->record);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return HymnResource::administration()->save($data, Auth::user(), $record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reviewHymn')->label('Mark reviewed')->icon('heroicon-o-check-badge')
                ->authorize(fn () => HymnResource::canEdit($this->record))->requiresConfirmation()
                ->action(function (): void {
                    HymnResource::administration()->review($this->record, Auth::user());
                    $this->data['review_state'] = 'reviewed';
                    Notification::make()->title('Hymn reviewed')->success()->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
