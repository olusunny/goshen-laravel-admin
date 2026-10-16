<?php

namespace App\Filament\Resources\HymnResource\Pages;

use App\Filament\Resources\HymnResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateHymn extends CreateRecord
{
    protected static string $resource = HymnResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return HymnResource::administration()->save($data, Auth::user());
    }
}
