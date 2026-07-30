<?php

namespace App\Filament\Resources\GoshenBookingResource\Pages;

use App\Filament\Resources\GoshenBookingResource;
use App\Services\GoshenBookingExportService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListGoshenBookings extends ListRecords
{
    protected static string $resource = GoshenBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportBookingsCsv')
                ->label('Export bookings CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): StreamedResponse => $this->exportBookings()),
        ];
    }

    private function exportBookings(): StreamedResponse
    {
        $filename = 'goshen-bookings-with-attendees-'.now()->format('Ymd-His').'.csv';
        $query = $this->getTableQueryForExport();

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');

            app(GoshenBookingExportService::class)->writeCsv(
                $query,
                $output,
            );

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
