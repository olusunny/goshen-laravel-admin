<?php

namespace App\Services;

use App\Models\InboxMessage;
use Illuminate\Support\Collection;

class InboxMessageDeliveryService
{
    public function __construct(
        private readonly MessageRecipientResolver $recipients,
        private readonly FirebasePushSender $pushSender,
        private readonly DynamicSmtpMailer $mailer,
        private readonly MessagePersonalizationService $personalization,
    ) {}

    public function dispatch(InboxMessage $message): array
    {
        $this->recipients->snapshotInboxRecipients($message);

        $push = ['sent' => 0, 'failed' => 0, 'error' => null];
        if ($message->send_push) {
            $push = $this->pushSender->sendInboxMessage($message);
            $message->forceFill([
                'push_sent_count' => $push['sent'] ?? 0,
                'push_failed_count' => $push['failed'] ?? 0,
                'push_sent_at' => now(),
                'push_last_error' => $push['error'] ?? null,
            ])->save();
        }

        $email = ['sent' => 0, 'failed' => 0, 'error' => null];
        if ($message->send_email) {
            $email = $this->sendEmail($message);
            $message->forceFill([
                'email_sent_count' => $email['sent'] ?? 0,
                'email_failed_count' => $email['failed'] ?? 0,
                'email_sent_at' => now(),
                'email_last_error' => $email['error'] ?? null,
            ])->save();
        }

        return [
            'push' => $push,
            'email' => $email,
        ];
    }

    public function snapshotRecipients(InboxMessage $message): array
    {
        return $this->recipients->snapshotInboxRecipients($message);
    }

    private function sendEmail(InboxMessage $message): array
    {
        $sent = 0;
        $failed = 0;
        $lastError = null;

        foreach ($this->emailRecipients($message) as $user) {
            try {
                $subject = $this->personalization->renderText((string) $message->title, $user, $message);
                $html = $this->personalization->renderHtml((string) $message->content, $user, $message);

                $this->mailer->sendHtml(
                    (string) $user->email,
                    $subject,
                    $html,
                    str($html)->stripTags()->toString(),
                );
                $sent++;
            } catch (\Throwable $exception) {
                $failed++;
                $lastError = $exception->getMessage();
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'error' => $lastError];
    }

    private function emailRecipients(InboxMessage $message): Collection
    {
        return $this->recipients
            ->usersFor($message)
            ->filter(fn ($user): bool => filled($user->email))
            ->unique(fn ($user): string => strtolower((string) $user->email))
            ->values();
    }
}
