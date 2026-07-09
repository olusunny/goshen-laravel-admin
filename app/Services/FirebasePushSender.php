<?php

namespace App\Services;

use App\Models\FcmToken;
use App\Models\Devotional;
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
                            'priority' => 'high',
                            'notification' => [
                                'channel_id' => 'churchapp',
                                'notification_priority' => 'PRIORITY_HIGH',
                                'visibility' => 'PUBLIC',
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

    public function sendDevotional(Devotional $devotional): array
    {
        $tokenRows = FcmToken::query()
            ->whereNotNull('token')
            ->get(['email', 'token'])
            ->filter(fn (FcmToken $token): bool => filled($token->token))
            ->unique('token')
            ->values();

        if ($tokenRows->isEmpty()) {
            return ['sent' => 0, 'failed' => 0, 'error' => 'No Firebase device tokens are available.'];
        }

        $usersByEmail = MobileUser::query()
            ->whereIn('email', $tokenRows->pluck('email')->filter()->unique())
            ->get()
            ->keyBy(fn (MobileUser $user): string => strtolower((string) $user->email));

        $tokens = $tokenRows
            ->filter(function (FcmToken $token) use ($usersByEmail): bool {
                $email = strtolower((string) $token->email);
                $user = $email !== '' ? $usersByEmail->get($email) : null;

                return ! $user || $user->wantsNotificationCategory('devotionals');
            })
            ->pluck('token')
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return ['sent' => 0, 'failed' => 0, 'error' => 'No subscribed devices matched the devotional notification category.'];
        }

        $title = (string) ($devotional->title ?: 'Today\'s devotional');
        $plainMessage = str($devotional->content ?? '')->stripTags()->limit(160)->toString();
        $imageUrl = MediaUrl::resolve($devotional->thumbnail) ?: null;
        $payload = $this->devotionalPayload($devotional);
        $sent = 0;
        $failed = 0;
        $lastError = null;

        foreach ($tokens->chunk(500) as $chunk) {
            try {
                $cloudMessage = CloudMessage::new()
                    ->withNotification(FirebaseNotification::create(
                        $title,
                        $plainMessage,
                        $imageUrl,
                    ))
                    ->withData([
                        'action' => 'devotional',
                        'devotional_id' => (string) $devotional->id,
                        'title' => $title,
                        'message' => $plainMessage,
                        'image_url' => (string) ($imageUrl ?? ''),
                        'notification_category' => 'devotionals',
                        'devotional' => json_encode($payload, JSON_THROW_ON_ERROR),
                    ])
                    ->withAndroidConfig(AndroidConfig::fromArray([
                        'priority' => 'high',
                        'notification' => [
                            'channel_id' => 'churchapp',
                            'notification_priority' => 'PRIORITY_HIGH',
                            'visibility' => 'PUBLIC',
                            'sound' => 'default',
                        ],
                    ]));

                $report = app(Messaging::class)->sendMulticast($cloudMessage, $chunk->values()->all());
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

                    $lastError = $staleTokens->isNotEmpty()
                        ? "Removed {$staleTokens->count()} stale Firebase device token(s)."
                        : 'Some Firebase tokens failed.';
                }
            } catch (\Throwable $exception) {
                $failed += $chunk->count();
                $lastError = $exception->getMessage();
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

    private function devotionalPayload(Devotional $devotional): array
    {
        return [
            'id' => $devotional->id,
            'title' => $devotional->title,
            'author' => $devotional->author ?? '',
            'date' => optional($devotional->date)->toDateString(),
            'content' => $devotional->content ?? '',
            'thumbnail' => MediaUrl::resolve($devotional->thumbnail) ?: '',
            'thumbnail_url' => MediaUrl::resolve($devotional->thumbnail) ?: '',
            'excerpt' => str($devotional->content ?? '')->stripTags()->limit(140)->toString(),
        ];
    }
}
