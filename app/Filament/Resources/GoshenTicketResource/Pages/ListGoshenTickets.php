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
            Actions\Action::make('exportTicketsCsv')
                ->label('Export tickets CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): StreamedResponse => $this->exportTickets('csv')),
            Actions\Action::make('exportTicketsExcel')
                ->label('Export tickets Excel')
                ->icon('heroicon-o-table-cells')
                ->action(fn (): StreamedResponse => $this->exportTickets('xls')),
        ];
    }

    private function exportTickets(string $format): StreamedResponse
    {
        $extension = $format === 'xls' ? 'xls' : 'csv';
        $filename = 'goshen-tickets-'.now()->format('Ymd-His').'.'.$extension;
        $contentType = $format === 'xls' ? 'application/vnd.ms-excel' : 'text/csv';
        $query = $this->getTableQueryForExport();

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');

            app(GoshenTicketExportService::class)->writeCsv($query, $output);

            fclose($output);
        }, $filename, ['Content-Type' => $contentType]);
    }
}
