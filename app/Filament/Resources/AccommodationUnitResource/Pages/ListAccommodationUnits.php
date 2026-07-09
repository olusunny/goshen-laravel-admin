<?php
namespace App\Filament\Resources\AccommodationUnitResource\Pages;
use App\Filament\Resources\AccommodationUnitResource;
use Filament\Resources\Pages\ListRecords;
class ListAccommodationUnits extends ListRecords { protected static string $resource = AccommodationUnitResource::class; protected function getHeaderActions(): array { return []; } }
