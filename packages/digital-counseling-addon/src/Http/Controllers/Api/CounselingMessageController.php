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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CounselingMessageController extends Controller
{
    public function __construct(
        private readonly PermissionResolverContract $permissions,
        private readonly ProtectedMediaStorageContract $mediaStorage,
    ) {
    }

    public function store(Request $request, CounselingCase $counselingCase): JsonResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->permissions->canRespondToCase($actor, $counselingCase), 403);

        $type = (string) $request->input('message_type', CounselingMessage::TYPE_TEXT);
        $data = $request->validate([
            'message_type' => ['nullable', Rule::in([CounselingMessage::TYPE_TEXT, CounselingMessage::TYPE_AUDIO])],
            'body' => [$type === CounselingMessage::TYPE_AUDIO ? 'nullable' : 'required', 'string', 'max:5000'],
            'audio' => [$type === CounselingMessage::TYPE_AUDIO ? 'required' : 'nullable', 'file', 'mimetypes:'.implode(',', config('counseling.media.allowed_audio_mimetypes', [])), 'max:'.(int) config('counseling.media.max_audio_size_kb', 20480)],
            'audio_duration_seconds' => [$type === CounselingMessage::TYPE_AUDIO ? 'required' : 'nullable', 'integer', 'min:1', 'max:'.(int) config('counseling.media.max_audio_duration_seconds', 300)],
        ]);

        $media = $request->hasFile('audio')
            ? $this->mediaStorage->storeVoiceNote($request->file('audio'))
            : null;

        $message = DB::transaction(function () use ($actor, $counselingCase, $type, $data, $media): CounselingMessage {
            $isRequester = $actor::class === config('counseling.models.requester')
                && (int) $actor->getKey() === (int) $counselingCase->requester_mobile_user_id;

            $message = $counselingCase->messages()->create([
                'actor_type' => $actor::class,
                'actor_id' => (int) $actor->getKey(),
                'direction' => $isRequester ? 'inbound' : 'outbound',
                'message_type' => $type,
                'body' => $data['body'] ?? null,
                'media_disk' => $media['disk'] ?? null,
                'media_path' => $media['path'] ?? null,
                'media_mime' => $media['mime'] ?? null,
                'media_size_bytes' => $media['size_bytes'] ?? null,
                'media_duration_seconds' => isset($data['audio_duration_seconds']) ? (int) $data['audio_duration_seconds'] : null,
            ]);

            $counselingCase->forceFill([
                'status' => $isRequester
                    ? CounselingCase::STATUS_AWAITING_COUNSELOR
                    : CounselingCase::STATUS_AWAITING_REQUESTER,
                'last_message_at' => now(),
            ])->save();

            CounselingCaseEvent::query()->create([
                'case_id' => $counselingCase->id,
                'actor_type' => $actor::class,
                'actor_id' => (int) $actor->getKey(),
                'event_type' => 'message.created',
                'payload' => ['message_type' => $type],
            ]);

            return $message;
        });

        return response()->json(['data' => $this->messagePayload($message, $counselingCase)], 201);
    }

    public function audio(Request $request, CounselingCase $counselingCase, CounselingMessage $message): StreamedResponse
    {
        $actor = $this->actor($request);
        abort_unless((int) $message->case_id === (int) $counselingCase->id, 404);
        abort_unless($this->permissions->canViewCase($actor, $counselingCase), 403);
        abort_unless($message->message_type === CounselingMessage::TYPE_AUDIO && $message->media_disk && $message->media_path, 404);

        return Storage::disk($message->media_disk)->response($message->media_path, null, [
            'Content-Type' => $message->media_mime ?: 'application/octet-stream',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function actor(Request $request): object
    {
        $actor = $request->user('mobile') ?? $request->user();
        abort_unless(is_object($actor) && method_exists($actor, 'getKey'), 401);

        return $actor;
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
