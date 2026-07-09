<?php

namespace App\Filament\Widgets;

use App\Models\MediaItem;
use Filament\Widgets\ChartWidget;

class MediaMixChart extends ChartWidget
{
    protected ?string $heading = 'Content library mix';

    protected ?string $description = 'Published media by type, with consumption weighted by recorded views.';

    protected string $color = 'info';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $rows = MediaItem::query()
            ->where('is_published', true)
            ->selectRaw('type, count(*) as total, sum(views_count) as views')
            ->groupBy('type')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Published items',
                    'data' => $rows->pluck('total')->map(fn ($value) => (int) $value)->all(),
                    'backgroundColor' => ['#f59e0b', '#0ea5e9', '#10b981', '#6366f1'],
                ],
            ],
            'labels' => $rows->map(fn ($row) => ucfirst($row->type).' ('.number_format((int) $row->views).' views)')->all(),
        ];
    }
}
