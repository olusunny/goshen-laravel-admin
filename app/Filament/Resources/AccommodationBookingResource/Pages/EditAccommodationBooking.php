<?php
namespace App\Filament\Resources\AccommodationBookingResource\Pages;
use App\Filament\Resources\AccommodationBookingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditAccommodationBooking extends EditRecord { protected static string $resource = AccommodationBookingResource::class; protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; } }
