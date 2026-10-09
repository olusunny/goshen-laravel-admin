<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Services\AdminAccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

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
            return app(AdminAccessService::class)->saveRole($record, $data, $this->submittedAccess['permissions'] ?? []);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $field): array => ['data.'.$field => $messages])->all());
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->using(fn (Role $record): bool => app(AdminAccessService::class)->deleteRole($record)),
        ];
    }
}
