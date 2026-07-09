<?php

namespace App\Filament\Resources\GoshenQuizResource\Pages;

use App\Filament\Resources\GoshenQuizResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateGoshenQuiz extends CreateRecord
{
    protected static string $resource = GoshenQuizResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_id'] = Auth::id();

        return $data;
    }
}
