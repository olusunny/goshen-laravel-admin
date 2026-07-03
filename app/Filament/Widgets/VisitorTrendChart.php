<?php

namespace App\Filament\Widgets;

use App\Models\VisitorMetric;
use Filament\Widgets\ChartWidget;

class VisitorTrendChart extends ChartWidget
{
    protected ?string $heading = 'Visitors and content consumption';

    protected ?string $description = 'Last 30 days of public web/API traffic and media consumption events.';

    protected string $color = 'primary';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn (int $daysAgo) => today()->subDays($daysAgo));

        return [
            'datasets' => [
                [
                    'label' => 'Visits',
                    'data' => $days->map(fn ($date) => (int) VisitorMetric::whereDate('visited_at', $date)->sum('visits'))->all(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.14)',
                    'tension' => 0.35,
                    'fill' => true,
                ],
                [
                    'label' => 'Consumption',
                    'data' => $days->map(fn ($date) => (int) VisitorMetric::whereDate('visited_at', $date)->sum('consumptions'))->all(),
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
