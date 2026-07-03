<?php

namespace App\Filament\Resources\GoshenAccommodationAllocationResource\Pages;

use App\Filament\Resources\GoshenAccommodationAllocationResource;
use App\Services\GoshenAccommodationEligibility;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGoshenAccommodationAllocation extends EditRecord
{
    protected static string $resource = GoshenAccommodationAllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(GoshenAccommodationEligibility::class)->validateAndHydrateAllocationData($data);
    }
}
