<?php

namespace App\Filament\Resources\GoshenAccommodationAllocationResource\Pages;

use App\Filament\Resources\GoshenAccommodationAllocationResource;
use App\Services\GoshenAccommodationEligibility;
use Filament\Resources\Pages\CreateRecord;

class CreateGoshenAccommodationAllocation extends CreateRecord
{
    protected static string $resource = GoshenAccommodationAllocationResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(GoshenAccommodationEligibility::class)->validateAndHydrateAllocationData($data);
    }
}
