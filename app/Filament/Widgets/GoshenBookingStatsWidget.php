<?php

namespace App\Filament\Widgets;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Filament\Widgets\Widget;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Models\Ticket;

class GoshenBookingStatsWidget extends Widget
{
    protected string $view = 'filament.widgets.goshen-booking-stats';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1;

    public function getOverview(): array
    {
        $eventIds = $this->goshenEventIds();
        $now = CarbonImmutable::now();

        if ($eventIds->isEmpty()) {
            return [
                'tickets_sold' => 0,
                'paid_bookings' => 0,
                'revenue' => $this->moneySummary(collect()),
                'today_tickets' => 0,
                'today_revenue' => $this->moneySummary(collect()),
                'week_tickets' => 0,
                'week_revenue' => $this->moneySummary(collect()),
                'month_tickets' => 0,
                'month_revenue' => $this->moneySummary(collect()),
                'checked_in' => 0,
                'awaiting_check_in' => 0,
            ];
        }

        $todayStart = $now->startOfDay();
        $weekStart = $now->startOfWeek();
        $monthStart = $now->startOfMonth();

        $paidBookings = Booking::query()
            ->whereIn('event_id', $eventIds)
            ->where('status', BookingStatus::Paid->value)
            ->get(['id', 'currency', 'paid_total']);

        $tickets = Ticket::query()
            ->whereIn('event_id', $eventIds)
            ->whereIn('status', $this->soldTicketStatusValues())
            ->get(['id', 'status', 'issued_at', 'created_at']);

        $todayPayments = $this->paidTransactionsBetween($eventIds, $todayStart, $now->endOfDay());
        $weekPayments = $this->paidTransactionsBetween($eventIds, $weekStart, $now->endOfDay());
        $monthPayments = $this->paidTransactionsBetween($eventIds, $monthStart, $now->endOfDay());

        return [
            'tickets_sold' => $tickets->count(),
            'paid_bookings' => $paidBookings->count(),
            'revenue' => $this->moneySummary($paidBookings, 'paid_total'),
            'today_tickets' => $this->ticketsIssuedFrom($tickets, $todayStart)->count(),
            'today_revenue' => $this->moneySummary($todayPayments),
            'week_tickets' => $this->ticketsIssuedFrom($tickets, $weekStart)->count(),
            'week_revenue' => $this->moneySummary($weekPayments),
            'month_tickets' => $this->ticketsIssuedFrom($tickets, $monthStart)->count(),
            'month_revenue' => $this->moneySummary($monthPayments),
            'checked_in' => $tickets->where('status', TicketStatus::CheckedIn)->count(),
            'awaiting_check_in' => $tickets->where('status', TicketStatus::NotCheckedIn)->count(),
        ];
    }

    public function getDailySales(): array
    {
        return $this->periodSales('day', 14);
    }

    public function getWeeklySales(): array
    {
        return $this->periodSales('week', 8);
    }

    public function getMonthlySales(): array
    {
        return $this->periodSales('month', 6);
    }

    public function getEditionBreakdown(): array
    {
        $eventIds = $this->goshenEventIds();

        if ($eventIds->isEmpty()) {
            return [];
        }

        $events = Event::query()
            ->whereIn('id', $eventIds)
            ->withCount([
                'bookings as paid_bookings_count' => fn (Builder $query) => $query->where('status', BookingStatus::Paid->value),
                'tickets as tickets_sold_count' => fn (Builder $query) => $query->whereIn('status', $this->soldTicketStatusValues()),
                'tickets as checked_in_count' => fn (Builder $query) => $query->where('status', TicketStatus::CheckedIn->value),
            ])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        $paidBookings = Booking::query()
            ->whereIn('event_id', $eventIds)
            ->where('status', BookingStatus::Paid->value)
            ->get(['event_id', 'currency', 'paid_total', 'updated_at']);

        return $events
            ->map(function (Event $event) use ($paidBookings): array {
                $eventBookings = $paidBookings->where('event_id', $event->id);
                $latestSale = $eventBookings
                    ->sortByDesc(fn (Booking $booking): int => $booking->updated_at?->getTimestamp() ?? 0)
                    ->first();

                return [
                    'name' => $event->name,
                    'venue' => trim(collect([$event->venue_name, $event->venue_address])->filter()->implode(' · ')),
                    'tickets_sold' => (int) $event->tickets_sold_count,
                    'paid_bookings' => (int) $event->paid_bookings_count,
                    'checked_in' => (int) $event->checked_in_count,
                    'revenue' => $this->moneySummary($eventBookings, 'paid_total'),
                    'latest_sale' => $latestSale?->updated_at?->format('M j, Y g:i A') ?: 'No sale yet',
                ];
            })
            ->all();
    }

    public function getRecentPurchases(): array
    {
        $eventIds = $this->goshenEventIds();

        if ($eventIds->isEmpty()) {
            return [];
        }

        return $this->paidTransactionsQuery($eventIds)
            ->with([
                'booking' => fn ($query) => $query
                    ->with('event')
                    ->withCount(['tickets' => fn (Builder $tickets) => $tickets->whereIn('status', $this->soldTicketStatusValues())]),
            ])
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(function (PaymentTransaction $transaction): array {
                $booking = $transaction->booking;

                return [
                    'customer' => $booking?->customer_name ?: 'Unknown attendee',
                    'email' => $booking?->customer_email ?: 'No email recorded',
                    'event' => $booking?->event?->name ?: 'Goshen Retreat',
                    'amount' => $this->formatMoney((float) $transaction->amount, (string) $transaction->currency),
                    'method' => str((string) $transaction->gateway)->replace(['_', '-'], ' ')->title()->toString(),
                    'tickets' => (int) ($booking?->tickets_count ?? 0),
                    'paid_at' => ($transaction->paid_at ?: $transaction->created_at)?->format('M j, Y g:i A') ?: 'Date unavailable',
                ];
            })
            ->all();
    }

    private function periodSales(string $period, int $count): array
    {
        $eventIds = $this->goshenEventIds();

        if ($eventIds->isEmpty()) {
            return [];
        }

        $now = CarbonImmutable::now();
        $start = match ($period) {
            'week' => $now->startOfWeek()->subWeeks($count - 1),
            'month' => $now->startOfMonth()->subMonths($count - 1),
            default => $now->startOfDay()->subDays($count - 1),
        };
        $end = $now->endOfDay();

        $payments = $this->paidTransactionsBetween($eventIds, $start, $end);
        $tickets = Ticket::query()
            ->whereIn('event_id', $eventIds)
            ->whereIn('status', $this->soldTicketStatusValues())
            ->where(function (Builder $query) use ($start, $end): void {
                $query
                    ->whereBetween('issued_at', [$start, $end])
                    ->orWhere(function (Builder $fallback) use ($start, $end): void {
                        $fallback
                            ->whereNull('issued_at')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            })
            ->get(['id', 'issued_at', 'created_at']);

        $rows = collect();

        for ($index = 0; $index < $count; $index++) {
            $periodStart = match ($period) {
                'week' => $start->addWeeks($index)->startOfWeek(),
                'month' => $start->addMonths($index)->startOfMonth(),
                default => $start->addDays($index)->startOfDay(),
            };
            $periodEnd = match ($period) {
                'week' => $periodStart->endOfWeek(),
                'month' => $periodStart->endOfMonth(),
                default => $periodStart->endOfDay(),
            };

            $periodPayments = $payments->filter(fn (PaymentTransaction $transaction): bool => $this->transactionDate($transaction)?->betweenIncluded($periodStart, $periodEnd) ?? false);
            $periodTickets = $tickets->filter(fn (Ticket $ticket): bool => $this->ticketDate($ticket)?->betweenIncluded($periodStart, $periodEnd) ?? false);

            $rows->push([
                'label' => match ($period) {
                    'week' => $periodStart->format('M j').' - '.$periodEnd->format('M j'),
                    'month' => $periodStart->format('M Y'),
                    default => $periodStart->format('M j'),
                },
                'tickets' => $periodTickets->count(),
                'amount' => $this->moneySummary($periodPayments),
                'amount_numeric' => $periodPayments->sum(fn (PaymentTransaction $transaction): float => (float) $transaction->amount),
            ]);
        }

        $maxAmount = max(1, (float) $rows->max('amount_numeric'));

        return $rows
            ->map(fn (array $row): array => [
                ...$row,
                'bar' => (int) round(((float) $row['amount_numeric'] / $maxAmount) * 100),
            ])
            ->all();
    }

    private function goshenEventIds(): Collection
    {
        if (! Schema::hasTable('ei_events')) {
            return collect();
        }

        return Event::query()
            ->where(function (Builder $query): void {
                $this->applyGoshenEventScope($query);
            })
            ->pluck('id');
    }

    private function applyGoshenEventScope(Builder $query): void
    {
        $query
            ->where('settings->module', 'goshen_retreat')
            ->orWhere('settings->module', 'goshen-retreat')
            ->orWhere('settings->app_module', 'goshen_retreat')
            ->orWhere('slug', 'like', 'goshen-retreat%')
            ->orWhere('slug', 'like', 'goshen-%')
            ->orWhere('name', 'like', '%Goshen Retreat%');
    }

    private function paidTransactionsQuery(Collection $eventIds): Builder
    {
        return PaymentTransaction::query()
            ->where('status', 'paid')
            ->whereHas('booking', fn (Builder $query) => $query->whereIn('event_id', $eventIds));
    }

    /**
     * @return array<int, string>
     */
    private function soldTicketStatusValues(): array
    {
        return [
            TicketStatus::NotCheckedIn->value,
            TicketStatus::CheckedIn->value,
        ];
    }

    private function paidTransactionsBetween(Collection $eventIds, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        if (! Schema::hasTable('ei_payment_transactions')) {
            return collect();
        }

        return $this->paidTransactionsQuery($eventIds)
            ->where(function (Builder $query) use ($start, $end): void {
                $query
                    ->whereBetween('paid_at', [$start, $end])
                    ->orWhere(function (Builder $fallback) use ($start, $end): void {
                        $fallback
                            ->whereNull('paid_at')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            })
            ->get(['id', 'booking_id', 'gateway', 'currency', 'amount', 'paid_at', 'created_at']);
    }

    private function ticketsIssuedFrom(Collection $tickets, CarbonImmutable $start): Collection
    {
        return $tickets->filter(fn (Ticket $ticket): bool => ($this->ticketDate($ticket)?->greaterThanOrEqualTo($start)) ?? false);
    }

    private function ticketDate(Ticket $ticket): ?CarbonImmutable
    {
        $date = $ticket->issued_at ?: $ticket->created_at;

        return $date ? CarbonImmutable::instance($date) : null;
    }

    private function transactionDate(PaymentTransaction $transaction): ?CarbonImmutable
    {
        $date = $transaction->paid_at ?: $transaction->created_at;

        return $date ? CarbonImmutable::instance($date) : null;
    }

    private function moneySummary(Collection $items, string $amountKey = 'amount', string $currencyKey = 'currency'): string
    {
        $totals = $items
            ->groupBy(fn ($item): string => strtoupper((string) (data_get($item, $currencyKey) ?: 'GBP')))
            ->map(fn (Collection $group): float => $group->sum(fn ($item): float => (float) data_get($item, $amountKey)))
            ->filter(fn (float $amount): bool => abs($amount) > 0.0001);

        if ($totals->isEmpty()) {
            return $this->formatMoney(0, 'GBP');
        }

        return $totals
            ->map(fn (float $amount, string $currency): string => $this->formatMoney($amount, $currency))
            ->values()
            ->implode(' · ');
    }

    private function formatMoney(float $amount, string $currency): string
    {
        return strtoupper($currency ?: 'GBP').' '.number_format($amount, 2);
    }
}
