<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ChurchEventResource;
use App\Filament\Resources\DonationResource;
use App\Filament\Resources\MediaItemResource;
use App\Filament\Resources\MobileUserResource;
use App\Models\AppSetting;
use App\Models\ChurchEvent;
use App\Models\Donation;
use App\Models\MediaItem;
use App\Models\MobileUser;
use App\Models\VisitorMetric;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -90;

    protected ?string $heading = 'Ministry pulse';

    protected ?string $description = 'Live operational snapshot across content, mobile users, giving, and consumption.';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return MediaItemResource::canViewAny()
            || MobileUserResource::canViewAny()
            || ChurchEventResource::canViewAny()
            || DonationResource::canViewAny();
    }

    protected function getStats(): array
    {
        $stats = [];

        if (MediaItemResource::canViewAny()) {
            $visitsToday = VisitorMetric::realTraffic()->whereDate('visited_at', today())->sum('visits');
            $visitsYesterday = VisitorMetric::realTraffic()->whereDate('visited_at', today()->subDay())->sum('visits');
            $consumptions = VisitorMetric::realTraffic()->where('visited_at', '>=', now()->subDays(30))->sum('consumptions');
            $series = $this->dailySeries();

            $stats[] = Stat::make('Visitors today', number_format($visitsToday))
                ->description($this->changeDescription($visitsToday, $visitsYesterday))
                ->descriptionIcon($visitsToday >= $visitsYesterday ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($visitsToday >= $visitsYesterday ? 'success' : 'warning')
                ->chart($series['visits']);
            $stats[] = Stat::make('30-day consumption', number_format($consumptions))
                ->description('Real media opens, searches, streams, and API consumption')
                ->descriptionIcon('heroicon-m-play-circle')
                ->color('info')
                ->chart($series['consumptions']);
            $stats[] = Stat::make('Published media', number_format(MediaItem::where('is_published', true)->count()))
                ->description(MediaItem::where('is_featured', true)->count().' featured in discover')
                ->descriptionIcon('heroicon-m-photo')
                ->color('primary');
        }

        if (MobileUserResource::canViewAny()) {
            $stats[] = Stat::make('Mobile users', number_format(MobileUser::count()))
                ->description('Registered mobile app accounts')
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->color('gray');
        }

        if (ChurchEventResource::canViewAny()) {
            $stats[] = Stat::make('Upcoming events', number_format(ChurchEvent::where('is_published', true)->where('starts_at', '>=', now())->count()))
                ->description('Published future events')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('success');
        }

        if (DonationResource::canViewAny()) {
            $stats[] = Stat::make('Giving recorded', $this->givingSummary())
                ->description('Completed donation records')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning');
        }

        return $stats;
    }

    private function givingSummary(): string
    {
        $defaultCurrency = $this->systemCurrency();

        $totals = Donation::query()
            ->where(function ($query): void {
                $query
                    ->whereIn('status', ['paid', 'success', 'completed'])
                    ->orWhereNotNull('paid_at');
            })
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->get()
            ->reduce(function (array $totals, Donation $donation) use ($defaultCurrency): array {
                $currency = $this->normalizedCurrency($donation->currency, $defaultCurrency);
                $totals[$currency] = ($totals[$currency] ?? 0) + $this->amountInMinorUnits($donation->total);

                return $totals;
            }, []);

        if ($totals === []) {
            return $this->formatCurrency(0, $defaultCurrency);
        }

        ksort($totals);

        return collect($totals)
            ->map(fn (int $amount, string $currency): string => $this->formatCurrency($amount, $currency))
            ->implode(' · ');
    }

    private function systemCurrency(): string
    {
        return $this->normalizedCurrency(AppSetting::value('currency', 'NGN'), 'NGN');
    }

    private function normalizedCurrency(mixed $currency, string $fallback): string
    {
        $currency = strtoupper(trim((string) $currency));

        return match ($currency) {
            '£' => 'GBP',
            '₦' => 'NGN',
            '$' => 'USD',
            '€' => 'EUR',
            default => preg_match('/^[A-Z]{3}$/', $currency) ? $currency : $fallback,
        };
    }

    private function amountInMinorUnits(mixed $amount): int
    {
        $amount = trim((string) $amount);

        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            return 0;
        }

        $minor = ((int) $matches[2] * 100) + (int) str_pad($matches[3] ?? '', 2, '0');

        return $matches[1] === '-' ? -$minor : $minor;
    }

    private function formatCurrency(int $amount, string $currency): string
    {
        $sign = $amount < 0 ? '-' : '';
        $amount = abs($amount);

        return sprintf('%s%s %d.%02d', $sign, $currency, intdiv($amount, 100), $amount % 100);
    }

    private function dailySeries(): array
    {
        $days = collect(range(13, 0))->map(fn (int $daysAgo) => today()->subDays($daysAgo));
        $totals = VisitorMetric::realTraffic()
            ->whereBetween('visited_at', [$days->first()->copy()->startOfDay(), $days->last()->copy()->endOfDay()])
            ->selectRaw('DATE(visited_at) as metric_date, SUM(visits) as visits, SUM(consumptions) as consumptions')
            ->groupBy('metric_date')
            ->get()
            ->keyBy('metric_date');

        return [
            'visits' => $days->map(fn ($date): int => (int) ($totals->get($date->toDateString())?->visits ?? 0))->all(),
            'consumptions' => $days->map(fn ($date): int => (int) ($totals->get($date->toDateString())?->consumptions ?? 0))->all(),
        ];
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
