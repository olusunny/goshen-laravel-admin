<?php

namespace App\Filament\Resources\CommunityPrayerRequestResource\Pages;

use App\Filament\Resources\CommunityPrayerRequestResource;
use App\Models\CommunityPrayerRequest;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCommunityPrayerRequests extends ListRecords
{
    protected static string $resource = CommunityPrayerRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => $this->export('csv')),
            Actions\Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-table-cells')
                ->action(fn () => $this->export('xls')),
            Actions\Action::make('export_pdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->action(fn () => $this->exportPdf()),
        ];
    }

    private function export(string $format)
    {
        $filename = 'interactive-prayer-requests-'.now()->format('Y-m-d-His').'.'.$format;
        $headers = ['Content-Type' => $format === 'xls' ? 'application/vnd.ms-excel' : 'text/csv'];

        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['id', 'type', 'anonymous', 'submitted_by', 'text', 'audio_path', 'duration', 'flags', 'comments', 'hidden_reason', 'created_at', 'expires_at']);

            CommunityPrayerRequest::with('mobileUser')->latest()->chunk(200, function ($requests) use ($output) {
                foreach ($requests as $request) {
                    fputcsv($output, [
                        $request->id,
                        $request->type,
                        $request->is_anonymous ? 'yes' : 'no',
                        $request->mobileUser?->email,
                        $request->text,
                        $request->audio_path,
                        $request->audio_duration_seconds,
                        $request->flags_count,
                        $request->comments_count,
                        $request->hidden_reason,
                        $request->created_at,
                        $request->expires_at,
                    ]);
                }
            });

            fclose($output);
        }, $filename, $headers);
    }

    private function exportPdf()
    {
        $filename = 'interactive-prayer-requests-'.now()->format('Y-m-d-His').'.pdf';
        $rows = CommunityPrayerRequest::with('mobileUser')->latest()->limit(300)->get();

        return response()->streamDownload(function () use ($rows) {
            echo $this->simplePdf($rows->map(function (CommunityPrayerRequest $request): string {
                $name = $request->is_anonymous ? 'Anonymous' : ($request->mobileUser?->name ?? 'Member');
                $text = str($request->text ?: '[Audio prayer request]')->squish()->limit(120)->toString();

                return "#{$request->id} | {$request->type} | {$name} | Flags: {$request->flags_count} | {$text}";
            })->all());
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    private function simplePdf(array $lines): string
    {
        $content = "BT\n/F1 11 Tf\n50 790 Td\n";
        $content .= '('.$this->pdfText('Interactive Prayer Requests - '.now()->toDateTimeString()).") Tj\n0 -20 Td\n";

        foreach ($lines as $line) {
            $content .= '('.$this->pdfText($line).") Tj\n0 -15 Td\n";
        }

        $content .= 'ET';
        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj',
            '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
            '5 0 obj << /Length '.strlen($content)." >> stream\n{$content}\nendstream endobj",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($index = 1; $index <= count($objects); $index++) {
            $pdf .= str_pad((string) $offsets[$index], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    private function pdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $text);
    }
}
