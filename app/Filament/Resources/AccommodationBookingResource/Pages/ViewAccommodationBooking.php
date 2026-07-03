<?php

namespace App\Filament\Resources\AccommodationBookingResource\Pages;

use App\Filament\Resources\AccommodationBookingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAccommodationBooking extends ViewRecord
{
    protected static string $resource = AccommodationBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn (): bool => AccommodationBookingResource::canEdit($this->getRecord())),
            Actions\Action::make('printReceipt')
                ->label('Print receipt')
                ->icon('heroicon-o-printer')
                ->url(fn (): string => url('/admin/accommodation-bookings/' . $this->getRecord()->id . '/receipt'))
                ->openUrlInNewTab(),
        ];
    }
}
