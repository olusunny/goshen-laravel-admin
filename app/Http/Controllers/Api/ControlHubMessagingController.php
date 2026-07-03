<?php

namespace App\Http\Controllers\Api;

use App\Filament\Resources\InboxMessageResource;
use App\Models\InboxMessage;
use App\Models\MobileUser;
use App\Services\InboxMessageDeliveryService;
use App\Services\MessagePersonalizationService;
use App\Services\MessageRecipientResolver;
use App\Services\ScheduledInboxMessageService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class ControlHubMessagingController extends Controller
{
    public function options(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if (! $this->canSendAdminMessages($user)) {
            return $this->forbidden();
        }

        return response()->json([
            'status' => 'ok',
            'data' => [
                'categories' => collect(MobileUser::notificationPreferenceDefinitions())
                    ->map(fn (array $category): array => [
                        'key' => $category['key'],
                        'label' => $category['label'],
                    ])
                    ->values(),
                'countries' => MobileUser::query()
                    ->whereNotNull('country_of_residence')
                    ->where('country_of_residence', '!=', '')
                    ->distinct()
                    ->orderBy('country_of_residence')
                    ->pluck('country_of_residence')
                    ->values(),
                'genders' => MobileUser::query()
                    ->whereNotNull('gender')
                    ->where('gender', '!=', '')
                    ->distinct()
                    ->orderBy('gender')
                    ->pluck('gender')
                    ->values(),
                'roles' => Role::query()
                    ->where('guard_name', 'mobile')
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Role $role): array => ['id' => $role->id, 'name' => $role->name])
                    ->values(),
                'goshen_events' => collect(app(MessageRecipientResolver::class)->goshenEventOptions())
                    ->map(fn (string $label, int|string $id): array => ['id' => (int) $id, 'name' => $label])
                    ->values(),
                'fundraising_campaigns' => collect(app(MessageRecipientResolver::class)->fundraisingCampaignOptions())
                    ->map(fn (string $label, int|string $id): array => ['id' => (int) $id, 'title' => $label])
                    ->values(),
                'quizzes' => collect(app(MessageRecipientResolver::class)->quizOptions())
                    ->map(fn (string $label, int|string $id): array => ['id' => (int) $id, 'title' => $label])
                    ->values(),
                'personalization_tags' => app(MessagePersonalizationService::class)->tags(),
            ],
        ]);
    }

    public function send(
        Request $request,
        InboxMessageDeliveryService $delivery,
        ScheduledInboxMessageService $scheduler,
    ): JsonResponse {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if (! $this->canSendAdminMessages($user)) {
            return $this->forbidden();
        }

        $validated = validator($this->payload($request), [
            'title' => ['required', 'string', 'max:180'],
            'content' => ['required', 'string', 'max:5000'],
            'notification_category' => ['nullable', 'string', 'max:80'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', 'in:inbox,push,email'],
            'send_inbox' => ['nullable', 'boolean'],
            'send_push' => ['nullable', 'boolean'],
            'send_email' => ['nullable', 'boolean'],
            'recipient_mode' => ['required', 'string', 'in:all,countries,genders,roles,goshen_paid,goshen_unpaid,goshen_paid_between,goshen_paid_recent_days,goshen_paid_week,goshen_paid_month,fundraising_participants,quiz_participants'],
            'selected_country_of_residences' => ['nullable', 'array'],
            'selected_country_of_residences.*' => ['string', 'max:120'],
            'selected_genders' => ['nullable', 'array'],
            'selected_genders.*' => ['string', 'max:80'],
            'selected_role_ids' => ['nullable', 'array'],
            'selected_role_ids.*' => ['integer', Rule::exists('roles', 'id')->where('guard_name', 'mobile')],
            'goshen_event_id' => ['nullable', 'integer', Rule::exists('ei_events', 'id')],
            'goshen_payment_filter' => ['nullable', 'string', 'in:paid,unpaid,paid_between,paid_recent_days,paid_week,paid_month'],
            'goshen_paid_from' => ['nullable', 'date'],
            'goshen_paid_until' => ['nullable', 'date', 'after_or_equal:goshen_paid_from'],
            'goshen_recent_days' => ['nullable', 'integer', 'min:1', 'max:366'],
            'goshen_paid_week' => ['nullable', 'date'],
            'goshen_paid_month' => ['nullable', 'date_format:Y-m'],
            'fundraising_campaign_id' => ['nullable', 'integer', Rule::exists('fundraising_campaigns', 'id')],
            'goshen_quiz_id' => ['nullable', 'integer', Rule::exists('goshen_quizzes', 'id')],
            'schedule_enabled' => ['nullable', 'boolean'],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ])->validate();

        $mode = (string) $validated['recipient_mode'];
        $channels = collect($validated['channels'] ?? [])
            ->map(fn ($channel) => strtolower((string) $channel))
            ->filter()
            ->values();
        $sendInbox = $channels->isNotEmpty()
            ? $channels->contains('inbox')
            : filter_var($validated['send_inbox'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $sendPush = $channels->isNotEmpty()
            ? $channels->contains('push')
            : filter_var($validated['send_push'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $sendEmail = $channels->isNotEmpty()
            ? $channels->contains('email')
            : filter_var($validated['send_email'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $sendInbox && ! $sendPush && ! $sendEmail) {
            return response()->json(['status' => 'error', 'message' => 'Choose at least one delivery channel.'], 422);
        }

        if ($sendPush && ! $sendInbox) {
            return response()->json(['status' => 'error', 'message' => 'Push notifications must also be delivered to the app inbox.'], 422);
        }

        if ($mode === 'countries' && empty($validated['selected_country_of_residences'])) {
            return response()->json(['status' => 'error', 'message' => 'Choose at least one country.'], 422);
        }
        if ($mode === 'genders' && empty($validated['selected_genders'])) {
            return response()->json(['status' => 'error', 'message' => 'Choose at least one gender.'], 422);
        }
        if ($mode === 'roles' && empty($validated['selected_role_ids'])) {
            return response()->json(['status' => 'error', 'message' => 'Choose at least one role.'], 422);
        }
        if (MessageRecipientResolver::isGoshenMode($mode) && empty($validated['goshen_event_id'])) {
            return response()->json(['status' => 'error', 'message' => 'Choose a Goshen retreat edition.'], 422);
        }
        if ($mode === 'goshen_paid_between' && (empty($validated['goshen_paid_from']) || empty($validated['goshen_paid_until']))) {
            return response()->json(['status' => 'error', 'message' => 'Choose the paid date range.'], 422);
        }
        if ($mode === 'goshen_paid_recent_days' && empty($validated['goshen_recent_days'])) {
            return response()->json(['status' => 'error', 'message' => 'Enter the number of recent paid days.'], 422);
        }
        if ($mode === 'goshen_paid_week' && empty($validated['goshen_paid_week'])) {
            return response()->json(['status' => 'error', 'message' => 'Choose the paid week.'], 422);
        }
        if ($mode === 'goshen_paid_month' && empty($validated['goshen_paid_month'])) {
            return response()->json(['status' => 'error', 'message' => 'Choose the paid month.'], 422);
        }
        if ($mode === 'fundraising_participants' && empty($validated['fundraising_campaign_id'])) {
            return response()->json(['status' => 'error', 'message' => 'Choose a fundraising campaign.'], 422);
        }
        if ($mode === 'quiz_participants' && empty($validated['goshen_quiz_id'])) {
            return response()->json(['status' => 'error', 'message' => 'Choose a quiz.'], 422);
        }

        $scheduledFor = $validated['scheduled_for'] ?? $validated['scheduled_at'] ?? null;
        $scheduleEnabled = filter_var($validated['schedule_enabled'] ?? filled($scheduledFor), FILTER_VALIDATE_BOOLEAN);
        if ($scheduleEnabled && blank($scheduledFor)) {
            return response()->json(['status' => 'error', 'message' => 'Choose when this message should be sent.'], 422);
        }

        $message = InboxMessage::query()->create(InboxMessageResource::normalizePublishingData([
            'title' => trim((string) $validated['title']),
            'content' => nl2br(e(trim((string) $validated['content']))),
            'notification_category' => $validated['notification_category'] ?? 'general',
            'send_inbox' => $sendInbox,
            'send_push' => $sendPush,
            'send_email' => $sendEmail,
            'recipient_mode' => $mode,
            'selected_country_of_residences' => $mode === 'countries' ? array_values($validated['selected_country_of_residences'] ?? []) : null,
            'selected_genders' => $mode === 'genders' ? array_values($validated['selected_genders'] ?? []) : null,
            'selected_role_ids' => $mode === 'roles' ? array_values($validated['selected_role_ids'] ?? []) : null,
            'goshen_event_id' => MessageRecipientResolver::isGoshenMode($mode) ? $validated['goshen_event_id'] : null,
            'goshen_payment_filter' => MessageRecipientResolver::paymentFilterForMode($mode),
            'goshen_paid_from' => $mode === 'goshen_paid_between' ? Carbon::parse($validated['goshen_paid_from']) : null,
            'goshen_paid_until' => $mode === 'goshen_paid_between' ? Carbon::parse($validated['goshen_paid_until'])->endOfDay() : null,
            'goshen_recent_days' => $mode === 'goshen_paid_recent_days' ? (int) $validated['goshen_recent_days'] : null,
            'goshen_paid_week' => $mode === 'goshen_paid_week' ? $validated['goshen_paid_week'] : null,
            'goshen_paid_month' => $mode === 'goshen_paid_month' ? $validated['goshen_paid_month'] : null,
            'fundraising_campaign_id' => $mode === 'fundraising_participants' ? $validated['fundraising_campaign_id'] : null,
            'goshen_quiz_id' => $mode === 'quiz_participants' ? $validated['goshen_quiz_id'] : null,
            'schedule_enabled' => $scheduleEnabled,
            'schedule_type' => $scheduleEnabled ? 'scheduled' : 'manual',
            'scheduled_for' => $scheduleEnabled ? Carbon::parse($scheduledFor) : null,
            'is_published' => $scheduleEnabled ? false : $sendInbox,
            'published_at' => $scheduleEnabled ? null : now(),
        ]));

        if ($scheduleEnabled) {
            $scheduler->normalizeSchedule($message);
            $result = [
                'push' => ['sent' => 0, 'failed' => 0, 'error' => null],
                'email' => ['sent' => 0, 'failed' => 0, 'error' => null],
            ];
        } else {
            $result = $delivery->dispatch($message);
        }

        return response()->json([
            'status' => 'ok',
            'message' => $scheduleEnabled ? 'Admin message scheduled.' : 'Admin message sent.',
            'data' => [
                'id' => $message->id,
                'title' => $message->title,
                'recipient_mode' => $message->recipient_mode,
                'send_inbox' => (bool) $message->send_inbox,
                'send_push' => (bool) $message->send_push,
                'send_email' => (bool) $message->send_email,
                'scheduled' => (bool) $message->schedule_enabled,
                'scheduled_for' => $message->scheduled_for?->toIso8601String(),
                'push_sent_count' => (int) data_get($result, 'push.sent', 0),
                'push_failed_count' => (int) data_get($result, 'push.failed', 0),
                'push_last_error' => data_get($result, 'push.error'),
                'email_sent_count' => (int) data_get($result, 'email.sent', 0),
                'email_failed_count' => (int) data_get($result, 'email.failed', 0),
                'email_last_error' => data_get($result, 'email.error'),
                'published_at' => $message->published_at?->toIso8601String(),
            ],
        ]);
    }

    private function requireUser(Request $request): MobileUser|JsonResponse
    {
        $user = $this->mobileUserFromRequest($request);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in before sending admin messages.',
            ], 401);
        }

        if (! $user->canUseCommunity()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please verify your email address before sending admin messages.',
            ], 403);
        }

        return $user;
    }

    private function mobileUserFromRequest(Request $request): ?MobileUser
    {
        $data = $this->payload($request);
        $token = $data['api_token'] ?? $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return null;
        }

        $user = MobileUser::query()
            ->where('api_token_hash', hash('sha256', $token))
            ->first();

        $user?->markApiSeen();

        return $user;
    }

    private function canSendAdminMessages(MobileUser $user): bool
    {
        if ($user->can('send_admin_messages') || $user->can('manage_inbox_message') || $user->can('manage_inbox_messages')) {
            return true;
        }

        return $user->roles()
            ->pluck('name')
            ->contains(fn ($role): bool => in_array(
                str($role)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                ['admin', 'superadmin', 'eventmanager', 'goshenmanager', 'retreatmanager', 'messagingmanager'],
                true,
            ));
    }

    private function payload(Request $request): array
    {
        $payload = $request->isJson()
            ? $request->json()->all()
            : $request->all();

        return is_array($payload['data'] ?? null)
            ? $payload['data']
            : $payload;
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Your account is not authorized to send admin messages.',
        ], 403);
    }
}
