<?php

namespace ChurchTools\DigitalCounseling\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use ChurchTools\DigitalCounseling\Contracts\PermissionResolverContract;
use ChurchTools\DigitalCounseling\Contracts\ProtectedMediaStorageContract;
use ChurchTools\DigitalCounseling\Models\CounselingCase;
use ChurchTools\DigitalCounseling\Models\CounselingCaseEvent;
use ChurchTools\DigitalCounseling\Models\CounselingMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CounselingCaseController extends Controller
{
    public function __construct(
        private readonly PermissionResolverContract $permissions,
        private readonly ProtectedMediaStorageContract $mediaStorage,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        $cases = CounselingCase::query()
            ->with(['assignedProviderProfile', 'latestMessage'])
            ->when(! $this->permissions->canTriage($actor), function ($query) use ($actor): void {
                $actorType = $actor::class;
                $actorId = (int) $actor->getKey();

                $query->where(function ($query) use ($actor, $actorType, $actorId): void {
                    $query->whereRaw('0 = 1');

                    if ($actorType === config('counseling.models.requester')) {
                        $query->orWhere('requester_mobile_user_id', $actorId);
                    }

                    $query->orWhereHas('assignments', function ($query) use ($actorType, $actorId): void {
                        $query->whereNull('ended_at')
                            ->where('assignee_type', $actorType)
                            ->where('assignee_id', $actorId);
                    });
                });
            })
            ->latest('last_message_at')
            ->latest()
            ->paginate((int) $request->integer('per_page', 15));

        return response()->json([
            'data' => collect($cases->items())->map(fn (CounselingCase $case): array => $this->casePayload($case))->values(),
            'meta' => [
                'current_page' => $cases->currentPage(),
                'last_page' => $cases->lastPage(),
                'per_page' => $cases->perPage(),
                'total' => $cases->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->permissions->canRequest($actor), 403, 'Only verified members can request private counseling.');

        $type = (string) $request->input('message_type', CounselingMessage::TYPE_TEXT);
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:160'],
            'category' => ['nullable', 'string', 'max:80'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high'])],
            'country_code' => ['nullable', 'string', 'size:2'],
            'locale' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'string', 'max:80'],
            'message_type' => ['nullable', Rule::in([CounselingMessage::TYPE_TEXT, CounselingMessage::TYPE_AUDIO])],
            'body' => [$type === CounselingMessage::TYPE_AUDIO ? 'nullable' : 'required', 'string', 'max:5000'],
            'audio' => [$type === CounselingMessage::TYPE_AUDIO ? 'required' : 'nullable', 'file', 'mimetypes:'.implode(',', config('counseling.media.allowed_audio_mimetypes', [])), 'max:'.(int) config('counseling.media.max_audio_size_kb', 20480)],
            'audio_duration_seconds' => [$type === CounselingMessage::TYPE_AUDIO ? 'required' : 'nullable', 'integer', 'min:1', 'max:'.(int) config('counseling.media.max_audio_duration_seconds', 300)],
        ]);

        $media = $request->hasFile('audio')
            ? $this->mediaStorage->storeVoiceNote($request->file('audio'))
            : null;

        $case = DB::transaction(function () use ($actor, $data, $media, $type): CounselingCase {
            $case = CounselingCase::query()->create([
                'reference' => $this->newReference(),
                'requester_mobile_user_id' => (int) $actor->getKey(),
                'status' => CounselingCase::STATUS_SUBMITTED,
                'priority' => $data['priority'] ?? 'normal',
                'category' => $data['category'] ?? null,
                'subject' => $data['subject'] ?? null,
                'summary' => isset($data['body']) ? Str::limit($data['body'], 500) : null,
                'country_code' => isset($data['country_code']) ? strtoupper($data['country_code']) : config('counseling.case.default_country_code'),
                'locale' => $data['locale'] ?? config('counseling.case.default_locale'),
                'timezone' => $data['timezone'] ?? config('counseling.case.default_timezone'),
                'last_message_at' => now(),
            ]);

            $case->messages()->create($this->messageAttributes(
                actor: $actor,
                direction: 'inbound',
                type: $type,
                body: $data['body'] ?? null,
                media: $media,
                duration: $data['audio_duration_seconds'] ?? null,
            ));

            $this->recordEvent($case, $actor, 'case.submitted', [
                'message_type' => $type,
                'country_code' => $case->country_code,
            ]);

            return $case->load(['assignedProviderProfile', 'latestMessage', 'messages']);
        });

        return response()->json(['data' => $this->casePayload($case, includeMessages: true)], 201);
    }

    public function show(Request $request, CounselingCase $counselingCase): JsonResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->permissions->canViewCase($actor, $counselingCase), 403);

        $counselingCase->load(['assignedProviderProfile', 'assignments', 'messages' => fn ($query) => $query->oldest()]);

        return response()->json(['data' => $this->casePayload($counselingCase, includeMessages: true)]);
    }

    public function close(Request $request, CounselingCase $counselingCase): JsonResponse
    {
        $actor = $this->actor($request);
        $isRequester = $actor::class === config('counseling.models.requester')
            && (int) $actor->getKey() === (int) $counselingCase->requester_mobile_user_id;

        abort_unless(
            $this->permissions->canTriage($actor)
                || ($isRequester && (bool) config('counseling.case.allow_requester_close', true)),
            403,
        );

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:160'],
        ]);

        if (! $counselingCase->isClosed()) {
            $counselingCase->forceFill([
                'status' => CounselingCase::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by_type' => $actor::class,
                'closed_by_id' => (int) $actor->getKey(),
                'closure_reason' => $data['reason'] ?? null,
            ])->save();

            $this->recordEvent($counselingCase, $actor, 'case.closed', [
                'reason' => $data['reason'] ?? null,
            ]);
        }

        return response()->json(['data' => $this->casePayload($counselingCase->fresh())]);
    }

    private function actor(Request $request): object
    {
        $actor = $request->user('mobile') ?? $request->user();
        abort_unless(is_object($actor) && method_exists($actor, 'getKey'), 401);

        return $actor;
    }

    /**
     * @param array{disk: string, path: string, mime: string|null, size_bytes: int|null}|null $media
     * @return array<string, mixed>
     */
    private function messageAttributes(object $actor, string $direction, string $type, ?string $body, ?array $media, mixed $duration): array
    {
        return [
            'actor_type' => $actor::class,
            'actor_id' => (int) $actor->getKey(),
            'direction' => $direction,
            'message_type' => $type,
            'body' => $body,
            'media_disk' => $media['disk'] ?? null,
            'media_path' => $media['path'] ?? null,
            'media_mime' => $media['mime'] ?? null,
            'media_size_bytes' => $media['size_bytes'] ?? null,
            'media_duration_seconds' => $duration ? (int) $duration : null,
        ];
    }

    private function recordEvent(CounselingCase $case, object $actor, string $type, array $payload = []): void
    {
        CounselingCaseEvent::query()->create([
            'case_id' => $case->id,
            'actor_type' => $actor::class,
            'actor_id' => (int) $actor->getKey(),
            'event_type' => $type,
            'payload' => $payload,
        ]);
    }

    private function newReference(): string
    {
        do {
            $reference = 'CS-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (CounselingCase::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function casePayload(CounselingCase $case, bool $includeMessages = false): array
    {
        $payload = [
            'id' => $case->id,
            'reference' => $case->reference,
            'status' => $case->status,
            'priority' => $case->priority,
            'category' => $case->category,
            'subject' => $case->subject,
            'country_code' => $case->country_code,
            'locale' => $case->locale,
            'timezone' => $case->timezone,
            'assigned_provider' => $case->assignedProviderProfile ? [
                'id' => $case->assignedProviderProfile->id,
                'display_name' => $case->assignedProviderProfile->display_name,
                'role' => $case->assignedProviderProfile->role,
            ] : null,
            'last_message_at' => $case->last_message_at?->toIso8601String(),
            'closed_at' => $case->closed_at?->toIso8601String(),
            'created_at' => $case->created_at?->toIso8601String(),
            'updated_at' => $case->updated_at?->toIso8601String(),
        ];

        if ($includeMessages) {
            $payload['messages'] = $case->messages
                ->map(fn (CounselingMessage $message): array => $this->messagePayload($message, $case))
                ->values()
                ->all();
        }

        return $payload;
    }

    private function messagePayload(CounselingMessage $message, CounselingCase $case): array
    {
        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'message_type' => $message->message_type,
            'body' => $message->body,
            'audio_url' => $message->message_type === CounselingMessage::TYPE_AUDIO
                ? route('counseling.api.cases.messages.audio', [$case, $message], false)
                : null,
            'audio_duration_seconds' => $message->media_duration_seconds,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
