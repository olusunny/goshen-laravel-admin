<?php

namespace App\Filament\Resources\GoshenRetreatMaterialResource\Pages;

use App\Filament\Resources\GoshenRetreatMaterialResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateGoshenRetreatMaterial extends CreateRecord
{
    protected static string $resource = GoshenRetreatMaterialResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $disk = Storage::disk('local');
        $data['mime_type'] = $disk->mimeType($data['file_path']) ?: 'application/octet-stream';
        $data['file_size'] = $disk->size($data['file_path']) ?: 0;

        return $data;
    }
}
