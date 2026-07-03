<?php

namespace App\Filament\Widgets;

use App\Models\ChurchEvent;
use App\Models\Donation;
use App\Models\MediaItem;
use App\Models\MobileUser;
use App\Models\VisitorMetric;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Ministry pulse';

    protected ?string $description = 'Live operational snapshot across content, mobile users, giving, and consumption.';

    protected function getStats(): array
    {
        $visitsToday = VisitorMetric::realTraffic()->whereDate('visited_at', today())->sum('visits');
        $visitsYesterday = VisitorMetric::realTraffic()->whereDate('visited_at', today()->subDay())->sum('visits');
        $consumptions = VisitorMetric::realTraffic()->where('visited_at', '>=', now()->subDays(30))->sum('consumptions');
        $donations = Donation::whereIn('status', ['paid', 'success', 'completed'])->sum('amount');

        return [
            Stat::make('Visitors today', number_format($visitsToday))
                ->description($this->changeDescription($visitsToday, $visitsYesterday))
                ->descriptionIcon($visitsToday >= $visitsYesterday ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($visitsToday >= $visitsYesterday ? 'success' : 'warning')
                ->chart($this->dailySeries('visits')),
            Stat::make('30-day consumption', number_format($consumptions))
                ->description('Real media opens, searches, streams, and API consumption')
                ->descriptionIcon('heroicon-m-play-circle')
                ->color('info')
                ->chart($this->dailySeries('consumptions')),
            Stat::make('Published media', number_format(MediaItem::where('is_published', true)->count()))
                ->description(MediaItem::where('is_featured', true)->count().' featured in discover')
                ->descriptionIcon('heroicon-m-photo')
                ->color('primary'),
            Stat::make('Mobile users', number_format(MobileUser::count()))
                ->description('Registered Flutter app accounts')
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->color('gray'),
            Stat::make('Upcoming events', number_format(ChurchEvent::where('is_published', true)->where('starts_at', '>=', now())->count()))
                ->description('Published future events')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('success'),
            Stat::make('Giving recorded', config('app.currency', 'NGN').' '.number_format((float) $donations, 2))
                ->description('Completed donation records')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),
        ];
    }

    private function dailySeries(string $column): array
    {
        return collect(range(13, 0))
            ->map(fn (int $daysAgo) => (int) VisitorMetric::realTraffic()->whereDate('visited_at', today()->subDays($daysAgo))->sum($column))
            ->all();
    }

    private function changeDescription(int|float $today, int|float $yesterday): string
    {
        if ($yesterday <= 0) {
            return $today > 0 ? 'New activity today' : 'No traffic yet today';
        }

        $change = (($today - $yesterday) / $yesterday) * 100;

        return sprintf('%s%% vs yesterday', number_format($change, 1));
    }
}
