<?php

namespace App\Filament\Widgets;

use App\Models\MediaItem;
use Filament\Widgets\Widget;

class AdminSummaryCards extends Widget
{
    protected string $view = 'filament.widgets.admin-summary-cards';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    public function getCards(): array
    {
        return [
            [
                'label' => 'Videos',
                'value' => number_format(MediaItem::where('type', 'video')->count()),
                'icon' => 'heroicon-o-play-circle',
                'description' => 'Published and uploaded video content',
                'accent' => '#ffb522',
            ],
            [
                'label' => 'Audios',
                'value' => number_format(MediaItem::whereIn('type', ['audio', 'music'])->count()),
                'icon' => 'heroicon-o-musical-note',
                'description' => 'Sermons, teachings, music, and voice content',
                'accent' => '#22d3ee',
            ],
        ];
    }
}
