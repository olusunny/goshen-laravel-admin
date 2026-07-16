<?php

namespace ChurchTools\DigitalCounseling\Filament\Resources\CounselingCaseResource\Pages;

use ChurchTools\DigitalCounseling\Filament\Resources\CounselingCaseResource;
use Filament\Resources\Pages\EditRecord;

class EditCounselingCase extends EditRecord
{
    protected static string $resource = CounselingCaseResource::class;

    protected function afterSave(): void
    {
        $providerId = $this->record->assigned_provider_profile_id;

        if (! $providerId) {
            return;
        }

        $hasActiveAssignment = $this->record->assignments()
            ->whereNull('ended_at')
            ->where('provider_profile_id', $providerId)
            ->exists();

        if (! $hasActiveAssignment) {
            CounselingCaseResource::assignProvider($this->record, (int) $providerId);
        }
    }
}
