<?php
namespace App\Filament\Resources\AccommodationBlockedDateResource\Pages;
use App\Filament\Resources\AccommodationBlockedDateResource;
use Filament\Resources\Pages\ListRecords;
class ListAccommodationBlockedDates extends ListRecords { protected static string $resource = AccommodationBlockedDateResource::class; protected function getHeaderActions(): array { return []; } }
