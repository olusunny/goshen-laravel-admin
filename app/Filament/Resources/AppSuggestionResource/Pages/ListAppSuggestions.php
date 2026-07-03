<?php

namespace App\Filament\Resources\AppSuggestionResource\Pages;

use App\Filament\Resources\AppSuggestionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAppSuggestions extends ListRecords
{
    protected static string $resource = AppSuggestionResource::class;
}
