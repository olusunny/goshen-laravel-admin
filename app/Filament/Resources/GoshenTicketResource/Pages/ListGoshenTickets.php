<?php

namespace App\Filament\Resources\GoshenTicketResource\Pages;

use App\Filament\Resources\GoshenTicketResource;
use App\Services\GoshenTicketExportService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListGoshenTickets extends ListRecords
{
    protected static string $resource = GoshenTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Issue ticket')
                ->icon('heroicon-o-ticket'),
            GoshenTicketResource::linkExistingFamilyAction(),
            Actions\Action::make('exportTicketsCsv')
                ->label('Export tickets CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): StreamedResponse => $this->exportTickets()),
        ];
    }

    private function exportTickets(): StreamedResponse
    {
        $filename = 'goshen-tickets-'.now()->format('Ymd-His').'.csv';
        $query = $this->getTableQueryForExport();

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');

            app(GoshenTicketExportService::class)->writeCsv($query, $output);

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
