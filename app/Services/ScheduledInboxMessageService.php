<?php

namespace App\Services;

use App\Models\InboxMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ScheduledInboxMessageService
{
    public function processDue(int $limit = 50): int
    {
        $processed = 0;

        InboxMessage::query()
            ->where('schedule_enabled', true)
            ->whereIn('schedule_type', ['scheduled', 'recurring_daily'])
            ->whereNotNull('next_dispatch_at')
            ->where('next_dispatch_at', '<=', now())
            ->orderBy('next_dispatch_at')
            ->limit($limit)
            ->get()
            ->each(function (InboxMessage $message) use (&$processed): void {
                $lock = Cache::lock("scheduled-inbox-message:{$message->id}", 120);

                if (! $lock->get()) {
                    return;
                }

                try {
                    $fresh = InboxMessage::query()
                        ->whereKey($message->id)
                        ->where('schedule_enabled', true)
                        ->whereIn('schedule_type', ['scheduled', 'recurring_daily'])
                        ->whereNotNull('next_dispatch_at')
                        ->where('next_dispatch_at', '<=', now())
                        ->first();

                    if (! $fresh) {
                        return;
                    }

                    $this->dispatch($fresh);
                    $processed++;
                } catch (\Throwable $exception) {
                    Log::warning('Scheduled inbox message dispatch failed.', [
                        'inbox_message_id' => $message->id,
                        'error' => $exception->getMessage(),
                    ]);
                } finally {
                    optional($lock)->release();
                }
            });

        return $processed;
    }

    public function normalizeSchedule(InboxMessage $message): void
    {
        if (! $message->schedule_enabled || ! in_array($message->schedule_type, ['scheduled', 'recurring_daily'], true)) {
            $message->forceFill([
                'schedule_enabled' => false,
                'schedule_type' => $message->schedule_type ?: 'manual',
                'next_dispatch_at' => null,
            ])->saveQuietly();

            return;
        }

        if ($message->schedule_type === 'scheduled') {
            $message->forceFill([
                'next_dispatch_at' => $message->scheduled_for,
                'is_published' => false,
            ])->saveQuietly();

            return;
        }

        $message->forceFill([
            'next_dispatch_at' => $this->nextDailyDispatch(
                $message->recurring_time ?: '09:00',
                $message->recurring_timezone ?: 'Africa/Lagos',
            ),
            'is_published' => false,
        ])->saveQuietly();
    }

    private function dispatch(InboxMessage $message): void
    {
        if ($message->schedule_type === 'scheduled') {
            $message->forceFill([
                'is_published' => (bool) $message->send_inbox,
                'published_at' => now(),
                'last_dispatched_at' => now(),
                'next_dispatch_at' => null,
                'schedule_enabled' => false,
            ])->save();

            app(InboxMessageDeliveryService::class)->dispatch($message);

            return;
        }

        $copy = $message->replicate([
            'id',
            'created_at',
            'updated_at',
            'push_sent_count',
            'push_failed_count',
            'push_sent_at',
            'push_last_error',
            'last_dispatched_at',
            'next_dispatch_at',
        ]);

        $copy->forceFill([
            'schedule_enabled' => false,
            'schedule_type' => 'generated',
            'scheduled_parent_id' => $message->id,
            'is_published' => (bool) $copy->send_inbox,
            'published_at' => now(),
        ])->save();

        app(InboxMessageDeliveryService::class)->dispatch($copy);

        $message->forceFill([
            'last_dispatched_at' => now(),
            'next_dispatch_at' => $this->nextDailyDispatch(
                $message->recurring_time ?: '09:00',
                $message->recurring_timezone ?: 'Africa/Lagos',
                now()->addMinute(),
            ),
        ])->save();
    }

    private function nextDailyDispatch(string $time, string $timezone, ?Carbon $after = null): Carbon
    {
        $after ??= now();
        $timezone = $timezone ?: 'Africa/Lagos';
        [$hour, $minute] = array_pad(explode(':', $time), 2, 0);

        $candidate = Carbon::now($timezone)
            ->setTime((int) $hour, (int) $minute)
            ->setSecond(0);

        if ($candidate->lessThanOrEqualTo($after->copy()->timezone($timezone))) {
            $candidate->addDay();
        }

        return $candidate->timezone(config('app.timezone'));
    }
}
