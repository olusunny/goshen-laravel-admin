<?php
namespace App\Filament\Resources\AccommodationServiceResource\Pages;
use App\Filament\Resources\AccommodationServiceResource;
use Filament\Resources\Pages\ListRecords;
class ListAccommodationServices extends ListRecords { protected static string $resource = AccommodationServiceResource::class; protected function getHeaderActions(): array { return []; } }
