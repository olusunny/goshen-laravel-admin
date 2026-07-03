<?php

namespace App\Filament\Widgets;

use App\Models\VisitorMetric;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class LocationInsightsWidget extends Widget
{
    protected string $view = 'filament.widgets.location-insights';

    protected int|string|array $columnSpan = 'full';

    public function getLocations(): Collection
    {
        return VisitorMetric::query()
            ->selectRaw("country, coalesce(region, '') as region, coalesce(city, '') as city, sum(visits) as visits, sum(consumptions) as consumptions")
            ->realTraffic()
            ->flutterApiTraffic()
            ->authenticatedMobileTraffic()
            ->where('visited_at', '>=', now()->subDays(30))
            ->groupBy('country', 'region', 'city')
            ->orderByDesc('visits')
            ->limit(8)
            ->get();
    }

    public function getTotalVisits(): int
    {
        return (int) VisitorMetric::realTraffic()
            ->flutterApiTraffic()
            ->authenticatedMobileTraffic()
            ->where('visited_at', '>=', now()->subDays(30))
            ->sum('visits');
    }
}
