<?php
namespace App\Filament\Resources\AccommodationFacilityResource\Pages;
use App\Filament\Resources\AccommodationFacilityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditAccommodationFacility extends EditRecord { protected static string $resource = AccommodationFacilityResource::class; protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; } }
