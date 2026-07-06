<?php

namespace App\Services;

use App\Models\InboxMessage;
use App\Models\MobileUser;
use Illuminate\Support\Facades\Log;

class GoshenRetreatNotificationService
{
    public function __construct(
        private readonly DynamicSmtpMailer $mailer,
        private readonly FirebasePushSender $pushSender,
    ) {
    }

    public function notifyUser(
        MobileUser $user,
        string $title,
        string $body,
        string $category = 'events',
        bool $email = true,
        bool $push = true,
    ): InboxMessage {
        $message = InboxMessage::query()->create([
            'title' => $title,
            'message_source' => InboxMessage::SOURCE_SYSTEM,
            'content' => nl2br(e($body)),
            'recipient_mode' => 'selected',
            'selected_mobile_user_ids' => [(int) $user->id],
            'notification_category' => $category,
            'send_push' => $push,
            'is_published' => true,
            'published_at' => now(),
        ]);

        if ($push) {
            try {
                $this->pushSender->sendInboxMessage($message);
            } catch (\Throwable $exception) {
                Log::warning('Goshen Retreat push notification failed.', [
                    'mobile_user_id' => $user->id,
                    'inbox_message_id' => $message->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($email && filled($user->email)) {
            try {
                $this->mailer->sendRaw($user->email, $title, $body);
            } catch (\Throwable $exception) {
                Log::warning('Goshen Retreat email notification failed.', [
                    'mobile_user_id' => $user->id,
                    'inbox_message_id' => $message->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $message;
    }
}
