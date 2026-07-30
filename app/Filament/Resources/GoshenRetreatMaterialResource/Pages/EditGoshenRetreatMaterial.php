<?php

namespace App\Filament\Resources\GoshenRetreatMaterialResource\Pages;

use App\Filament\Resources\GoshenRetreatMaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditGoshenRetreatMaterial extends EditRecord
{
    protected static string $resource = GoshenRetreatMaterialResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['file_path'] ?? null) !== $this->record->file_path) {
            $disk = Storage::disk('local');
            $data['mime_type'] = $disk->mimeType($data['file_path']) ?: 'application/octet-stream';
            $data['file_size'] = $disk->size($data['file_path']) ?: 0;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
