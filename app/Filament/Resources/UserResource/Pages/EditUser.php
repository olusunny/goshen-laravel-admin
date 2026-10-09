<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Services\AdminAccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected array $submittedAccess = [];

    protected function beforeValidate(): void
    {
        // Filament reloads relationships after validation; retain the submitted grants.
        $this->submittedAccess = $this->data ?? [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(AdminAccessService::class)->saveUser($record, $data, $this->submittedAccess);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $field): array => ['data.'.$field => $messages])->all());
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->using(fn (User $record): bool => app(AdminAccessService::class)->deleteUser($record)),
        ];
    }
}
