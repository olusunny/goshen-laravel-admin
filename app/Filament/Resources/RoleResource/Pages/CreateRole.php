<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Services\AdminAccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected array $submittedAccess = [];

    protected function beforeValidate(): void
    {
        // Filament reloads relationships after validation; retain the submitted grants.
        $this->submittedAccess = $this->data ?? [];
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(AdminAccessService::class)->saveRole(null, $data, $this->submittedAccess['permissions'] ?? []);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $field): array => ['data.'.$field => $messages])->all());
        }
    }
}
