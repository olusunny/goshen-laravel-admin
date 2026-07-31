<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Services;

use App\Models\InboxMessage;
use App\Services\InboxMessageDeliveryService;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayCelebration;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayDelivery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class BirthdayNotifier
{
    /**
     * @param array<int, BirthdayCelebration> $linkedCelebrations
     */
    public function send(Model $member, ?BirthdayCelebration $celebration, string $kind, string $dedupeKey, string $title, string $content, array $linkedCelebrations = []): void
    {
        $delivery = DB::transaction(function () use ($member, $celebration, $kind, $dedupeKey, $title, $content, $linkedCelebrations): BirthdayDelivery {
            $delivery = BirthdayDelivery::query()->where('dedupe_key', $dedupeKey)->lockForUpdate()->first()
                ?? BirthdayDelivery::query()->create([
                    'celebration_id' => $celebration?->id,
                    'mobile_user_id' => $member->id,
                    'kind' => $kind,
                    'dedupe_key' => $dedupeKey,
                    'status' => 'pending',
                ]);
            $delivery->celebrations()->syncWithoutDetaching(collect($linkedCelebrations)->pluck('id')->filter()->all());

            if (! $delivery->inbox_message_id && class_exists(InboxMessage::class)) {
                $message = InboxMessage::query()->create([
                    'title' => $title,
                    'content' => e($content),
                    'message_source' => InboxMessage::SOURCE_SYSTEM,
                    'recipient_mode' => 'selected',
                    'selected_mobile_user_ids' => [(int) $member->id],
                    'notification_category' => 'general',
                    'send_inbox' => true,
                    'send_push' => true,
                    'send_email' => false,
                    'push_action' => 'church_birthday_celebrations',
                    'push_data' => array_filter([
                        'celebration_id' => $celebration?->public_id,
                        'deep_link' => 'triumphant://church-birthday-celebrations'.($celebration ? '?celebration_id='.$celebration->public_id : ''),
                    ]),
                    'push_visibility' => 'PRIVATE',
                    'is_published' => true,
                    'published_at' => now(),
                ]);
                $delivery->forceFill(['inbox_message_id' => $message->id])->save();
            }

            return $delivery;
        });

        $this->deliver($delivery);
    }

    public function retryFailed(): void
    {
        BirthdayDelivery::query()
            ->whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', max(1, (int) config('church-birthday-celebrations.notification_max_attempts', 5)))
            ->orderBy('id')
            ->cursor()
            ->each(fn (BirthdayDelivery $delivery) => $this->deliver($delivery));
    }

    private function deliver(BirthdayDelivery $delivery): void
    {
        $delivery->refresh();
        if ($delivery->status === 'sent'
            || $delivery->attempts >= max(1, (int) config('church-birthday-celebrations.notification_max_attempts', 5))) {
            return;
        }

        $message = $delivery->inbox_message_id ? InboxMessage::query()->find($delivery->inbox_message_id) : null;
        if (! $message || ! app()->bound(InboxMessageDeliveryService::class)) {
            $this->failed($delivery, 'Notification delivery service is unavailable.');

            return;
        }

        try {
            $result = app(InboxMessageDeliveryService::class)->dispatch($message);
            $push = $result['push'] ?? [];
            $error = trim((string) ($push['error'] ?? ''));
            if ((int) ($push['failed'] ?? 0) > 0 || $error !== '') {
                $this->failed($delivery, $error !== '' ? $error : 'One or more push deliveries failed.');

                return;
            }

            $delivery->forceFill([
                'status' => 'sent',
                'attempts' => $delivery->attempts + 1,
                'last_attempt_at' => now(),
                'last_error' => null,
                'sent_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $this->failed($delivery, $exception->getMessage());
            report($exception);
        }
    }

    private function failed(BirthdayDelivery $delivery, string $error): void
    {
        $delivery->forceFill([
            'status' => 'failed',
            'attempts' => $delivery->attempts + 1,
            'last_attempt_at' => now(),
            'last_error' => str($error)->limit(1000)->toString(),
        ])->save();
    }
}
