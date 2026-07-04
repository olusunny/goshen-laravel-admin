<?php

namespace App\Services;

use App\Models\ChurchEvent;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RecurringChurchEventService
{
    private const DEFAULT_PAST_DAYS = 90;

    private const DEFAULT_FUTURE_DAYS = 365;

    public function expandForRequest(Collection $events, ?string $date = null): Collection
    {
        if (filled($date)) {
            $target = CarbonImmutable::parse($date);

            return $this->expandBetween(
                $events,
                $target->startOfDay(),
                $target->endOfDay(),
            );
        }

        $now = CarbonImmutable::now();

        return $this->expandBetween(
            $events,
            $now->subDays(self::DEFAULT_PAST_DAYS)->startOfDay(),
            $now->addDays(self::DEFAULT_FUTURE_DAYS)->endOfDay(),
            includeOneTimeOutsideWindow: true,
        );
    }

    public function upcomingOccurrences(Collection $events, int $limit = 5): Collection
    {
        $now = CarbonImmutable::now();

        return $this->expandBetween(
            $events,
            $now->startOfDay(),
            $now->addDays(self::DEFAULT_FUTURE_DAYS)->endOfDay(),
        )->take($limit)->values();
    }

    public function expandBetween(
        Collection $events,
        CarbonInterface $from,
        CarbonInterface $to,
        bool $includeOneTimeOutsideWindow = false,
    ): Collection {
        $from = CarbonImmutable::instance($from);
        $to = CarbonImmutable::instance($to);

        return $events
            ->flatMap(function (ChurchEvent $event) use ($from, $to, $includeOneTimeOutsideWindow): array {
                if (! $event->isRecurring()) {
                    return $includeOneTimeOutsideWindow || $this->eventIntersectsWindow($event, $from, $to)
                        ? [$event]
                        : [];
                }

                return $this->occurrencesBetween($event, $from, $to);
            })
            ->sortBy(fn (ChurchEvent $event): int => $event->starts_at?->timestamp ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * @return array<int, ChurchEvent>
     */
    private function occurrencesBetween(ChurchEvent $event, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! $event->starts_at) {
            return [];
        }

        return match ($event->recurrence_type) {
            ChurchEvent::RECURRENCE_WEEKLY => $this->weeklyOccurrencesBetween($event, $from, $to),
            ChurchEvent::RECURRENCE_MONTHLY_NTH_WEEKDAY => $this->monthlyOccurrencesBetween($event, $from, $to),
            default => [],
        };
    }

    /**
     * @return array<int, ChurchEvent>
     */
    private function weeklyOccurrencesBetween(ChurchEvent $event, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $start = $this->asImmutable($event->starts_at);
        $until = $this->untilDate($event, $to);
        $weekday = $this->weekday($event);
        $interval = max(1, (int) ($event->recurrence_interval ?: 1));
        $occurrenceDate = $this->firstWeekdayOnOrAfter($start->startOfDay(), $weekday);

        while ($occurrenceDate->lt($from->startOfDay())) {
            $occurrenceDate = $occurrenceDate->addWeeks($interval);
        }

        $occurrences = [];
        while ($occurrenceDate->lte($to) && $occurrenceDate->lte($until)) {
            $occurrences[] = $this->occurrenceForDate($event, $occurrenceDate);
            $occurrenceDate = $occurrenceDate->addWeeks($interval);
        }

        return $occurrences;
    }

    /**
     * @return array<int, ChurchEvent>
     */
    private function monthlyOccurrencesBetween(ChurchEvent $event, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $start = $this->asImmutable($event->starts_at);
        $until = $this->untilDate($event, $to);
        $weekday = $this->weekday($event);
        $weekOfMonth = (int) ($event->recurrence_week_of_month ?: 1);
        $interval = max(1, (int) ($event->recurrence_interval ?: 1));
        $cursor = $from->greaterThan($start)
            ? $from->startOfMonth()
            : $start->startOfMonth();
        $startMonth = $start->startOfMonth();
        $occurrences = [];

        while ($cursor->lte($to) && $cursor->lte($until)) {
            $monthsSinceStart = (int) $startMonth->diffInMonths($cursor);
            if ($monthsSinceStart % $interval === 0) {
                $occurrenceDate = $this->nthWeekdayOfMonth($cursor, $weekday, $weekOfMonth);
                if (
                    $occurrenceDate
                    && $occurrenceDate->gte($start->startOfDay())
                    && $occurrenceDate->betweenIncluded($from->startOfDay(), $to->endOfDay())
                    && $occurrenceDate->lte($until)
                ) {
                    $occurrences[] = $this->occurrenceForDate($event, $occurrenceDate);
                }
            }

            $cursor = $cursor->addMonthNoOverflow();
        }

        return $occurrences;
    }

    private function occurrenceForDate(ChurchEvent $event, CarbonImmutable $date): ChurchEvent
    {
        $start = $this->asImmutable($event->starts_at);
        $occurrenceStart = $date->setTime($start->hour, $start->minute, $start->second);
        $occurrence = clone $event;

        $occurrence->setAttribute('starts_at', $occurrenceStart);
        $occurrence->setAttribute('recurring_parent_id', $event->id);
        $occurrence->setAttribute('recurrence_occurrence_date', $occurrenceStart->toDateString());

        if ($event->ends_at && $this->asImmutable($event->ends_at)->greaterThan($start)) {
            $durationSeconds = $start->diffInSeconds($this->asImmutable($event->ends_at));
            $occurrence->setAttribute('ends_at', $occurrenceStart->addSeconds($durationSeconds));
        }

        return $occurrence;
    }

    private function eventIntersectsWindow(ChurchEvent $event, CarbonImmutable $from, CarbonImmutable $to): bool
    {
        if (! $event->starts_at) {
            return false;
        }

        $start = $this->asImmutable($event->starts_at);
        $end = $event->ends_at ? $this->asImmutable($event->ends_at) : $start;

        return $start->lte($to) && $end->gte($from);
    }

    private function firstWeekdayOnOrAfter(CarbonImmutable $date, int $weekday): CarbonImmutable
    {
        $offset = ($weekday - $date->dayOfWeek + 7) % 7;

        return $date->addDays($offset);
    }

    private function nthWeekdayOfMonth(CarbonImmutable $month, int $weekday, int $weekOfMonth): ?CarbonImmutable
    {
        if ($weekOfMonth === -1) {
            $date = $month->endOfMonth()->startOfDay();
            while ($date->dayOfWeek !== $weekday) {
                $date = $date->subDay();
            }

            return $date;
        }

        if ($weekOfMonth < 1 || $weekOfMonth > 4) {
            return null;
        }

        $first = $this->firstWeekdayOnOrAfter($month->startOfMonth(), $weekday);
        $date = $first->addWeeks($weekOfMonth - 1);

        return $date->month === $month->month ? $date : null;
    }

    private function weekday(ChurchEvent $event): int
    {
        if ($event->recurrence_weekday !== null) {
            return max(0, min(6, (int) $event->recurrence_weekday));
        }

        return $event->starts_at ? $this->asImmutable($event->starts_at)->dayOfWeek : 0;
    }

    private function untilDate(ChurchEvent $event, CarbonImmutable $fallback): CarbonImmutable
    {
        return $event->recurrence_until
            ? CarbonImmutable::parse($event->recurrence_until)->endOfDay()
            : $fallback;
    }

    private function asImmutable(mixed $value): CarbonImmutable
    {
        return $value instanceof CarbonInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse($value);
    }
}
