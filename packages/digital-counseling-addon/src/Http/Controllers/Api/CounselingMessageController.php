<?php

namespace ChurchTools\DigitalCounseling\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\MediaUrl;
use ChurchTools\DigitalCounseling\Contracts\PermissionResolverContract;
use ChurchTools\DigitalCounseling\Contracts\ProtectedMediaStorageContract;
use ChurchTools\DigitalCounseling\Models\CounselingCase;
use ChurchTools\DigitalCounseling\Models\CounselingCaseEvent;
use ChurchTools\DigitalCounseling\Models\CounselingMessage;
use Illuminate\Database\Eloquent\Model;
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
            'message_type' => ['nullable', Rule::in([CounselingMessage::TYPE_TEXT, CounselingMessage::TYPE_AUDIO, CounselingMessage::TYPE_IMAGE, CounselingMessage::TYPE_FILE])],
            'body' => [in_array($type, [CounselingMessage::TYPE_AUDIO, CounselingMessage::TYPE_IMAGE, CounselingMessage::TYPE_FILE], true) ? 'nullable' : 'required', 'string', 'max:5000'],
            'audio' => [$type === CounselingMessage::TYPE_AUDIO ? 'required' : 'nullable', 'file', 'mimetypes:'.implode(',', config('counseling.media.allowed_audio_mimetypes', [])), 'max:'.(int) config('counseling.media.max_audio_size_kb', 20480)],
            'attachment' => [in_array($type, [CounselingMessage::TYPE_IMAGE, CounselingMessage::TYPE_FILE], true) ? 'required' : 'nullable', 'file', 'mimetypes:'.implode(',', $this->allowedAttachmentMimetypes($type)), 'max:'.(int) config('counseling.media.max_attachment_size_kb', 20480)],
            'audio_duration_seconds' => [$type === CounselingMessage::TYPE_AUDIO ? 'required' : 'nullable', 'integer', 'min:1', 'max:'.(int) config('counseling.media.max_audio_duration_seconds', 300)],
            'reaction' => ['nullable', 'string', 'max:32'],
        ]);

        $media = $this->storeIncomingMedia($request, $type);

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
                'metadata' => [
                    'sender' => $this->actorPayload($actor),
                    'original_name' => $media['original_name'] ?? null,
                    'reactions' => [],
                ],
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

    public function reaction(Request $request, CounselingCase $counselingCase, CounselingMessage $message): JsonResponse
    {
        $actor = $this->actor($request);
        abort_unless((int) $message->case_id === (int) $counselingCase->id, 404);
        abort_unless($this->permissions->canViewCase($actor, $counselingCase), 403);

        $data = $request->validate([
            'reaction' => ['nullable', 'string', 'max:32'],
        ]);

        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $reactions = is_array($metadata['reactions'] ?? null) ? $metadata['reactions'] : [];
        $key = $actor::class.':'.(int) $actor->getKey();
        $reaction = trim((string) ($data['reaction'] ?? ''));

        if ($reaction === '') {
            unset($reactions[$key]);
        } else {
            $reactions[$key] = [
                'reaction' => $reaction,
                'actor' => $this->actorPayload($actor),
                'reacted_at' => now()->toIso8601String(),
            ];
        }

        $metadata['reactions'] = $reactions;
        $message->forceFill(['metadata' => $metadata])->save();

        return response()->json(['data' => $this->messagePayload($message->fresh(), $counselingCase)]);
    }

    public function audio(Request $request, CounselingCase $counselingCase, CounselingMessage $message): StreamedResponse
    {
        abort_unless($message->message_type === CounselingMessage::TYPE_AUDIO, 404);

        return $this->media($request, $counselingCase, $message);
    }

    public function media(Request $request, CounselingCase $counselingCase, CounselingMessage $message): StreamedResponse
    {
        $actor = $this->actor($request);
        abort_unless((int) $message->case_id === (int) $counselingCase->id, 404);
        abort_unless($this->permissions->canViewCase($actor, $counselingCase), 403);
        abort_unless(in_array($message->message_type, [CounselingMessage::TYPE_AUDIO, CounselingMessage::TYPE_IMAGE, CounselingMessage::TYPE_FILE], true) && $message->media_disk && $message->media_path, 404);

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
        $mediaUrl = $message->media_disk && $message->media_path
            ? route('counseling.api.cases.messages.media', [$case, $message], false)
            : null;

        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'message_type' => $message->message_type,
            'body' => $message->body,
            'sender' => $message->metadata['sender'] ?? $this->actorPayloadFromColumns($message->actor_type, $message->actor_id),
            'media_url' => $mediaUrl,
            'audio_url' => $message->message_type === CounselingMessage::TYPE_AUDIO
                ? route('counseling.api.cases.messages.audio', [$case, $message], false)
                : null,
            'audio_duration_seconds' => $message->media_duration_seconds,
            'attachment' => in_array($message->message_type, [CounselingMessage::TYPE_IMAGE, CounselingMessage::TYPE_FILE], true) ? [
                'url' => $mediaUrl,
                'mime' => $message->media_mime,
                'size_bytes' => $message->media_size_bytes,
                'name' => $message->metadata['original_name'] ?? basename((string) $message->media_path),
            ] : null,
            'reactions' => $message->metadata['reactions'] ?? [],
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    private function storeIncomingMedia(Request $request, string $type): ?array
    {
        if ($type === CounselingMessage::TYPE_AUDIO && $request->hasFile('audio')) {
            return $this->mediaStorage->storeVoiceNote($request->file('audio'));
        }

        if (in_array($type, [CounselingMessage::TYPE_IMAGE, CounselingMessage::TYPE_FILE], true) && $request->hasFile('attachment')) {
            return $this->mediaStorage->storeAttachment($request->file('attachment'));
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function allowedAttachmentMimetypes(string $type): array
    {
        if ($type === CounselingMessage::TYPE_IMAGE) {
            return config('counseling.media.allowed_image_mimetypes', []);
        }

        return config('counseling.media.allowed_file_mimetypes', []);
    }

    private function actorPayloadFromColumns(?string $type, mixed $id): ?array
    {
        if (! $type || ! class_exists($type) || ! $id) {
            return null;
        }

        $actor = $type::query()->find($id);

        return $actor ? $this->actorPayload($actor) : null;
    }

    private function actorPayload(object $actor): array
    {
        return [
            'id' => method_exists($actor, 'getKey') ? (int) $actor->getKey() : null,
            'type' => $actor::class,
            'name' => $this->actorName($actor),
            'email' => $actor instanceof Model ? (string) ($actor->email ?? '') : '',
            'phone' => $actor instanceof Model ? (string) ($actor->phone ?? '') : '',
            'avatar' => $this->actorAvatar($actor),
        ];
    }

    private function actorName(object $actor): string
    {
        foreach (['name', 'display_name', 'full_name'] as $field) {
            $value = $actor instanceof Model ? $actor->getAttribute($field) : ($actor->{$field} ?? null);
            if (filled($value)) {
                return (string) $value;
            }
        }

        return 'Member';
    }

    private function actorAvatar(object $actor): string
    {
        foreach (['avatar', 'profile_photo_path', 'photo', 'image'] as $field) {
            $value = $actor instanceof Model ? $actor->getAttribute($field) : ($actor->{$field} ?? null);
            if (filled($value)) {
                return MediaUrl::resolve($value) ?: (string) $value;
            }
        }

        return '';
    }
}
