<?php

namespace App\Services;

use App\Models\InboxMessage;
use App\Models\Testimony;
use Illuminate\Support\Facades\Log;

class TestimonyNotificationService
{
    public function sendRejection(Testimony $testimony): void
    {
        $user = $testimony->mobileUser;

        if (! $user) {
            return;
        }

        $reason = trim((string) $testimony->rejection_reason);
        $reason = $reason !== '' ? $reason : 'The admin team could not approve this testimony for public display at this time.';

        $message = InboxMessage::create([
            'title' => 'Testimony review update',
            'notification_category' => 'testimonies',
            'content' => $this->content($testimony, $reason),
            'send_push' => true,
            'recipient_mode' => 'selected',
            'selected_mobile_user_ids' => [$user->id],
            'is_published' => true,
            'published_at' => now(),
        ]);

        try {
            $result = app(FirebasePushSender::class)->sendInboxMessage($message);
            $message->forceFill([
                'push_sent_count' => $result['sent'] ?? 0,
                'push_failed_count' => $result['failed'] ?? 0,
                'push_last_error' => $result['error'] ?? null,
                'push_sent_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            Log::warning('Testimony rejection push notification failed.', [
                'testimony_id' => $testimony->id,
                'inbox_message_id' => $message->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function content(Testimony $testimony, string $reason): string
    {
        return '<p>Hello '.e($testimony->mobileUser?->name ?: 'dear member').',</p>'
            .'<p>Thank you for sharing your testimony, <strong>'.e($testimony->title).'</strong>. After review, it was not approved for the public Testimonies & Thanksgiving Wall.</p>'
            .'<p><strong>Reason:</strong> '.e($reason).'</p>'
            .'<p>You may review the feedback and submit a fresh testimony whenever you are ready. God bless you.</p>';
    }
}
