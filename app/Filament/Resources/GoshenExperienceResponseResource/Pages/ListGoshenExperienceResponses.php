<?php

namespace App\Filament\Resources\GoshenExperienceResponseResource\Pages;

use App\Filament\Resources\GoshenExperienceResponseResource;
use App\Models\GoshenExperienceResponse;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListGoshenExperienceResponses extends ListRecords
{
    protected static string $resource = GoshenExperienceResponseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportCsv')
                ->label('Export responses CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    private function exportCsv(): StreamedResponse
    {
        $filename = 'goshen-survey-responses-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'Response ID',
                'Survey',
                'Retreat edition',
                'Attendee',
                'Email',
                'Country',
                'Submitted At',
                'Question',
                'Question Type',
                'Answer',
            ]);

            GoshenExperienceResponse::query()
                ->with(['survey', 'event', 'mobileUser'])
                ->orderByDesc('submitted_at')
                ->chunk(200, function ($responses) use ($output): void {
                    foreach ($responses as $response) {
                        $rows = GoshenExperienceResponseResource::answerRows($response);
                        if ($rows === []) {
                            $rows[] = [
                                'question' => '',
                                'type' => '',
                                'answer' => '',
                            ];
                        }

                        foreach ($rows as $row) {
                            fputcsv($output, [
                                $response->id,
                                $response->survey?->title,
                                $response->event?->name,
                                $response->mobileUser?->name,
                                $response->mobileUser?->email,
                                $response->mobileUser?->country_of_residence,
                                $response->submitted_at?->toDateTimeString(),
                                $row['question'],
                                $row['type'],
                                $row['answer'],
                            ]);
                        }
                    }
                });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
