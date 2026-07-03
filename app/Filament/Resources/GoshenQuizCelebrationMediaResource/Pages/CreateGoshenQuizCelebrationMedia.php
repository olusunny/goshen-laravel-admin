<?php

namespace App\Filament\Resources\GoshenQuizCelebrationMediaResource\Pages;

use App\Filament\Resources\GoshenQuizCelebrationMediaResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateGoshenQuizCelebrationMedia extends CreateRecord
{
    protected static string $resource = GoshenQuizCelebrationMediaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_id'] = Auth::id();

        return $data;
    }
}
