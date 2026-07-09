<?php
namespace App\Filament\Resources\AccommodationServiceResource\Pages;
use App\Filament\Resources\AccommodationServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditAccommodationService extends EditRecord { protected static string $resource = AccommodationServiceResource::class; protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; } }
