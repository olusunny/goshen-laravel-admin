<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\AdminAccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

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
            return app(AdminAccessService::class)->saveUser(null, $data, $this->submittedAccess);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $field): array => ['data.'.$field => $messages])->all());
        }
    }
}
