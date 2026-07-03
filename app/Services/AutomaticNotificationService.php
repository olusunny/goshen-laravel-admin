<?php

namespace App\Services;

use App\Models\AutomaticNotification;
use App\Models\AutomaticNotificationDelivery;
use App\Models\InboxMessage;
use App\Models\MobileUser;
use Illuminate\Support\Facades\Log;

class AutomaticNotificationService
{
    public function enqueue(string $eventKey, MobileUser $user, array $context = []): ?AutomaticNotificationDelivery
    {
        $notification = AutomaticNotification::query()
            ->where('event_key', $eventKey)
            ->where('is_active', true)
            ->first();

        if (! $notification || $user->is_blocked || $user->is_deleted) {
            return null;
        }

        return AutomaticNotificationDelivery::query()->firstOrCreate(
            ['event_key' => $eventKey, 'mobile_user_id' => $user->id],
            [
                'automatic_notification_id' => $notification->id,
                'status' => 'pending',
                'context' => $context,
                'scheduled_at' => now()->addMinutes((int) $notification->delay_minutes),
            ],
        );
    }

    public function processDue(int $limit = 100): int
    {
        $processed = 0;

        AutomaticNotificationDelivery::with(['notification', 'mobileUser'])
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get()
            ->each(function (AutomaticNotificationDelivery $delivery) use (&$processed): void {
                $this->sendDelivery($delivery);
                $processed++;
            });

        return $processed;
    }

    public function sendDelivery(AutomaticNotificationDelivery $delivery): void
    {
        $notification = $delivery->notification;
        $user = $delivery->mobileUser;

        if (! $notification || ! $notification->is_active || ! $user || $user->is_blocked || $user->is_deleted) {
            $delivery->forceFill([
                'status' => 'skipped',
                'last_error' => 'Notification or recipient is no longer available.',
            ])->save();

            return;
        }

        $context = array_merge($delivery->context ?? [], $this->userContext($user));
        $title = app(MessagePersonalizationService::class)->renderText(
            $this->render($notification->title_template, $context),
            $user,
            $notification,
        );
        $body = app(MessagePersonalizationService::class)->renderText(
            $this->render($notification->body_template, $context),
            $user,
            $notification,
        );
        $failed = false;
        $lastError = null;

        if ($notification->send_email && $user->email) {
            try {
                app(DynamicSmtpMailer::class)->sendRaw($user->email, $title, $body);
            } catch (\Throwable $exception) {
                $failed = true;
                $lastError = $exception->getMessage();
                Log::warning('Automatic notification email failed.', [
                    'event_key' => $notification->event_key,
                    'mobile_user_id' => $user->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $inbox = null;
        if ($notification->send_inbox || $notification->send_push) {
            $inbox = InboxMessage::create([
                'title' => $title,
                'notification_category' => $notification->notification_category ?: 'general',
                'content' => nl2br(e($body)),
                'thumbnail' => $notification->image_path,
                'send_push' => (bool) $notification->send_push,
                'recipient_mode' => 'selected',
                'selected_mobile_user_ids' => [$user->id],
                'is_published' => true,
                'published_at' => now(),
            ]);
        }

        if ($notification->send_push && $inbox) {
            try {
                $result = app(FirebasePushSender::class)->sendInboxMessage($inbox);
                $inbox->forceFill([
                    'push_sent_count' => $result['sent'] ?? 0,
                    'push_failed_count' => $result['failed'] ?? 0,
                    'push_sent_at' => now(),
                    'push_last_error' => $result['error'] ?? null,
                ])->save();
            } catch (\Throwable $exception) {
                $failed = true;
                $lastError = $exception->getMessage();
            }
        }

        $delivery->forceFill([
            'status' => $failed ? 'failed' : 'sent',
            'sent_at' => $failed ? null : now(),
            'last_error' => $lastError,
        ])->save();

        $notification->forceFill([
            'sent_count' => $notification->sent_count + ($failed ? 0 : 1),
            'failed_count' => $notification->failed_count + ($failed ? 1 : 0),
            'last_sent_at' => $failed ? $notification->last_sent_at : now(),
            'last_error' => $lastError,
        ])->save();
    }

    private function userContext(MobileUser $user): array
    {
        return [
            'user_name' => $user->name ?: 'Beloved',
            'name' => $user->name ?: 'Beloved',
            'email' => $user->email,
            'phone' => $user->phone,
        ];
    }

    private function render(string $template, array $context): string
    {
        foreach ($context as $key => $value) {
            $template = str_replace('{'.$key.'}', (string) $value, $template);
        }

        return $template;
    }
}
