<?php
namespace App\Filament\Resources\AccommodationBookingResource\Pages;
use App\Filament\Resources\AccommodationBookingResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
class ListAccommodationBookings extends ListRecords { protected static string $resource = AccommodationBookingResource::class; protected function getHeaderActions(): array { return [Actions\Action::make('exportCsv')->label('Export CSV')->icon('heroicon-o-arrow-down-tray')->url('/admin/accommodation-bookings/export-csv')->openUrlInNewTab()]; } }
