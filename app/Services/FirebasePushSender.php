<?php

namespace App\Services;

use App\Models\FcmToken;
use App\Models\InboxMessage;
use App\Models\MobileUser;
use App\Support\MediaUrl;
use Illuminate\Support\Collection;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;

class FirebasePushSender
{
    public function __construct(
        private readonly MessagePersonalizationService $personalization,
    ) {}

    public function sendInboxMessage(InboxMessage $message): array
    {
        $users = $this->recipientUsers($message);
        $tokensByEmail = $this->tokensForUsers($users);

        if ($users->isNotEmpty()) {
            $deliveredIds = collect($message->delivered_mobile_user_ids ?? [])
                ->merge($users->pluck('id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $message->forceFill([
                'delivered_mobile_user_ids' => $deliveredIds,
            ])->saveQuietly();
        }

        if ($tokensByEmail->flatten()->isEmpty()) {
            return ['sent' => 0, 'failed' => 0, 'error' => 'No device tokens matched the selected recipients.'];
        }

        $sent = 0;
        $failed = 0;
        $lastError = null;
        $imageUrl = MediaUrl::resolve($message->thumbnail) ?: null;
        $toneUrl = $message->notification_tone_enabled
            ? (MediaUrl::resolve($message->notification_tone_path) ?: null)
            : null;

        foreach ($users as $user) {
            $userTokens = $tokensByEmail->get(strtolower((string) $user->email), collect());

            if ($userTokens->isEmpty()) {
                continue;
            }

            $title = $this->personalization->renderText((string) $message->title, $user, $message);
            $content = $this->personalization->renderHtml((string) $message->content, $user, $message);
            $plainMessage = str($content)->stripTags()->limit(160)->toString();

            foreach ($userTokens->chunk(500) as $chunk) {
                try {
                    $messaging = app(Messaging::class);
                    $cloudMessage = CloudMessage::new()
                        ->withNotification(FirebaseNotification::create(
                            $title,
                            $plainMessage,
                            $imageUrl,
                        ))
                        ->withData([
                            'action' => 'inbox',
                            'inbox_id' => (string) $message->id,
                            'title' => $title,
                            'message' => $plainMessage,
                            'image_url' => (string) ($imageUrl ?? ''),
                            'tone_enabled' => $toneUrl ? '1' : '0',
                            'tone_url' => (string) ($toneUrl ?? ''),
                            'tone_label' => (string) ($message->notification_tone_label ?? ''),
                            'inbox' => json_encode($this->inboxPayload($message, $user), JSON_THROW_ON_ERROR),
                        ])
                        ->withAndroidConfig(AndroidConfig::fromArray([
                            'notification' => [
                                'sound' => 'default',
                            ],
                        ]));

                    $report = $messaging->sendMulticast($cloudMessage, $chunk->values()->all());
                    $sent += $report->successes()->count();
                    $failed += $report->failures()->count();

                    if ($report->hasFailures()) {
                        $staleTokens = collect([
                            ...$report->unknownTokens(),
                            ...$report->invalidTokens(),
                        ])->filter()->unique()->values();

                        if ($staleTokens->isNotEmpty()) {
                            FcmToken::whereIn('token', $staleTokens)->delete();
                        }

                        $failureMessages = collect($report->failures()->getItems())
                            ->map(fn ($failure) => $failure->error()?->getMessage())
                            ->filter()
                            ->unique()
                            ->take(3)
                            ->implode(' | ');

                        $lastError = $staleTokens->isNotEmpty()
                            ? "Removed {$staleTokens->count()} stale Firebase device token(s).".($failureMessages ? " {$failureMessages}" : '')
                            : ($failureMessages ?: 'Some Firebase tokens failed.');
                    }
                } catch (\Throwable $exception) {
                    $failed += $chunk->count();
                    $lastError = $exception->getMessage();
                }
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'error' => $lastError];
    }

    private function recipientUsers(InboxMessage $message): Collection
    {
        return app(MessageRecipientResolver::class)->usersFor($message, true);
    }

    private function tokensForUsers(Collection $users): Collection
    {
        $emails = $users->pluck('email')->filter()->values();

        if ($emails->isEmpty()) {
            return collect();
        }

        return FcmToken::query()
            ->whereIn('email', $emails)
            ->get(['email', 'token'])
            ->filter(fn (FcmToken $token): bool => filled($token->email) && filled($token->token))
            ->groupBy(fn (FcmToken $token): string => strtolower((string) $token->email))
            ->map(fn (Collection $tokens): Collection => $tokens->pluck('token')->unique()->values());
    }

    private function inboxPayload(InboxMessage $message, ?MobileUser $user = null): array
    {
        $title = $this->personalization->renderText((string) $message->title, $user, $message);
        $content = $this->personalization->renderHtml((string) $message->content, $user, $message);

        return [
            'id' => $message->id,
            'title' => $title,
            'message' => str($content)->stripTags()->toString(),
            'content' => $content,
            'thumbnail' => MediaUrl::resolve($message->thumbnail) ?: '',
            'image_url' => MediaUrl::resolve($message->thumbnail) ?: '',
            'tone_enabled' => (bool) $message->notification_tone_enabled,
            'tone_url' => $message->notification_tone_enabled ? (MediaUrl::resolve($message->notification_tone_path) ?: '') : '',
            'tone_label' => $message->notification_tone_label ?: '',
            'notification_category' => $message->notification_category ?: 'general',
            'date' => optional($message->published_at ?? $message->created_at)->timestamp,
            'dateInserted' => optional($message->published_at ?? $message->created_at)->toDateTimeString(),
        ];
    }
}
