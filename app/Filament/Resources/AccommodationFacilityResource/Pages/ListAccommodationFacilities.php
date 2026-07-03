<?php
namespace App\Filament\Resources\AccommodationFacilityResource\Pages;
use App\Filament\Resources\AccommodationFacilityResource;
use Filament\Resources\Pages\ListRecords;
class ListAccommodationFacilities extends ListRecords { protected static string $resource = AccommodationFacilityResource::class; protected function getHeaderActions(): array { return []; } }
