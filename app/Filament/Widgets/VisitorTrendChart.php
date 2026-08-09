<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\MediaItemResource;
use App\Models\VisitorMetric;
use Filament\Widgets\ChartWidget;

class VisitorTrendChart extends ChartWidget
{
    protected static ?int $sort = -50;

    protected ?string $heading = 'Visitors and content consumption';

    protected ?string $description = 'Last 30 days of public web/API traffic and media consumption events.';

    protected string $color = 'primary';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return MediaItemResource::canViewAny();
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn (int $daysAgo) => today()->subDays($daysAgo));
        $totals = VisitorMetric::realTraffic()
            ->whereBetween('visited_at', [$days->first()->copy()->startOfDay(), $days->last()->copy()->endOfDay()])
            ->selectRaw('DATE(visited_at) as metric_date, SUM(visits) as visits, SUM(consumptions) as consumptions')
            ->groupBy('metric_date')
            ->get()
            ->keyBy('metric_date');

        return [
            'datasets' => [
                [
                    'label' => 'Visits',
                    'data' => $days->map(fn ($date): int => (int) ($totals->get($date->toDateString())?->visits ?? 0))->all(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.14)',
                    'tension' => 0.35,
                    'fill' => true,
                ],
                [
                    'label' => 'Consumption',
                    'data' => $days->map(fn ($date): int => (int) ($totals->get($date->toDateString())?->consumptions ?? 0))->all(),
                    'borderColor' => '#0ea5e9',
                    'backgroundColor' => 'rgba(14, 165, 233, 0.12)',
                    'tension' => 0.35,
                    'fill' => true,
                ],
            ],
            'labels' => $days->map(fn ($date) => $date->format('M j'))->all(),
        ];
    }
}
