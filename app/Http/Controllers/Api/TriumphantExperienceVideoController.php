<?php

namespace App\Http\Controllers\Api;

use App\Enums\GoshenExperienceVideoUploadStatus;
use App\Exceptions\ActiveTriumphantExperienceSubmissionException;
use App\Http\Resources\TriumphantExperienceVideoResource;
use App\Jobs\QueueTriumphantExperienceVideoUpload;
use App\Models\GoshenExperienceVideo;
use App\Models\GoshenYouTubeConnection;
use App\Models\MobileUser;
use App\Services\GoshenExperienceEligibility;
use App\Services\VideoInspectionService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Personal\EventInstallments\Models\Event;
use Throwable;

class TriumphantExperienceVideoController extends Controller
{
    public function __construct(
        private readonly GoshenExperienceEligibility $eligibility,
        private readonly VideoInspectionService $inspection,
    ) {}

    public function index(Request $request): mixed
    {
        if (! $this->enabled()) {
            return $this->disabledResponse();
        }

        $videos = GoshenExperienceVideo::query()
            ->with('event')
            ->feedVisible()
            ->latest('approved_at')
            ->latest('id')
            ->cursorPaginate($this->perPage($request));

        return TriumphantExperienceVideoResource::collection($videos)
            ->additional(['feature' => $this->featurePayload($request)]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->enabled()) {
            return $this->disabledResponse();
        }

        $user = $this->requireMobileUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $existing = $this->existingForClientUpload($user, $request->input('client_upload_id'));
        if ($existing) {
            return $this->acceptedResponse($existing);
        }

        $validated = $request->validate([
            'client_upload_id' => ['required', 'uuid'],
            'event_id' => ['required', 'string', 'max:64'],
            'caption' => ['nullable', 'string', 'max:500'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'is_anonymous' => ['required', 'boolean'],
            'consent_version' => ['required', 'string', Rule::in([(string) config('goshen_experience.release_version')])],
            'video' => ['required', 'file', 'mimetypes:'.implode(',', $this->allowedMimeTypes()), 'max:'.$this->maximumFileSizeKilobytes()],
        ]);

        $event = Event::query()->where('public_id', $validated['event_id'])->first();
        if (! $event || ! $this->eligibleEvent($event)) {
            return response()->json(['status' => 'error', 'message' => 'This retreat video feature is not available for the selected event.'], 404);
        }

        $ticket = $this->eligibility->eligibleTicketFor($user, $event);
        if (! $ticket) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only checked-in attendees for this Goshen Retreat can share a Triumphant Experience video.',
            ], 403);
        }

        $rateLimitKey = "triumphant-experience-video:{$user->id}:{$event->id}";
        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            return response()->json(['status' => 'error', 'message' => 'Please wait before trying another video upload.'], 429);
        }

        $sourceDisk = (string) config('goshen_experience.source_disk', 'local');
        if ($sourceDisk !== 'local') {
            report(new \LogicException('Triumphant Experience source disk must remain Laravel local private storage.'));

            return response()->json(['status' => 'error', 'message' => 'Secure video upload is temporarily unavailable.'], 503);
        }

        /** @var UploadedFile $uploadedVideo */
        $uploadedVideo = $validated['video'];
        $sourceDirectory = trim((string) config('goshen_experience.source_path', 'goshen/experience/shorts/source'), '/').'/'.str()->uuid();
        $sourcePath = Storage::disk($sourceDisk)->putFileAs($sourceDirectory, $uploadedVideo, 'source.mp4');
        if (! $sourcePath) {
            return response()->json(['status' => 'error', 'message' => 'Secure video upload is temporarily unavailable.'], 503);
        }

        try {
            $absolutePath = Storage::disk($sourceDisk)->path($sourcePath);
            $inspection = $this->inspection->inspect($absolutePath, $uploadedVideo->getMimeType());
            $sha256 = hash_file('sha256', $absolutePath);
            if (! is_string($sha256)) {
                throw new \RuntimeException('Unable to hash securely staged video.');
            }

            $video = DB::transaction(function () use ($user, $event, $ticket, $validated, $sourceDisk, $sourcePath, $uploadedVideo, $inspection, $sha256): GoshenExperienceVideo {
                if ($this->activeSubmissionFor($user, $event, lockForUpdate: true)) {
                    throw new ActiveTriumphantExperienceSubmissionException;
                }

                return GoshenExperienceVideo::query()->create([
                    'event_id' => $event->id,
                    'mobile_user_id' => $user->id,
                    'booking_id' => $ticket->booking_id,
                    'ticket_id' => $ticket->id,
                    'client_upload_id' => $validated['client_upload_id'],
                    'caption' => $validated['caption'] ?? null,
                    'display_name' => $validated['display_name'] ?? null,
                    'is_anonymous' => $validated['is_anonymous'],
                    'consent_version' => $validated['consent_version'],
                    'consented_at' => now(),
                    'local_disk' => $sourceDisk,
                    'local_path' => $sourcePath,
                    'original_filename' => substr((string) $uploadedVideo->getClientOriginalName(), 0, 255),
                    'mime_type' => (string) $uploadedVideo->getMimeType(),
                    'file_size_bytes' => (int) $uploadedVideo->getSize(),
                    'duration_seconds' => $inspection->durationSeconds,
                    'width' => $inspection->width,
                    'height' => $inspection->height,
                    'sha256' => $sha256,
                    'upload_status' => GoshenExperienceVideoUploadStatus::Received,
                ]);
            });
        } catch (ActiveTriumphantExperienceSubmissionException) {
            Storage::disk($sourceDisk)->delete($sourcePath);

            return $this->activeSubmissionResponse();
        } catch (QueryException $exception) {
            Storage::disk($sourceDisk)->delete($sourcePath);

            $existing = $this->existingForClientUpload($user, $validated['client_upload_id']);
            if ($existing) {
                return $this->acceptedResponse($existing);
            }

            if ($this->activeSubmissionFor($user, $event)) {
                return $this->activeSubmissionResponse();
            }

            throw $exception;
        } catch (Throwable $exception) {
            Storage::disk($sourceDisk)->delete($sourcePath);

            throw $exception;
        }

        RateLimiter::hit($rateLimitKey, 3600);
        QueueTriumphantExperienceVideoUpload::dispatch($video->getKey())->afterCommit();

        return $this->acceptedResponse($video->loadMissing('event'));
    }

    public function mine(Request $request): mixed
    {
        if (! $this->enabled()) {
            return $this->disabledResponse();
        }

        $user = $this->requireMobileUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $videos = GoshenExperienceVideo::query()
            ->with('event')
            ->where('mobile_user_id', $user->id)
            ->latest('id')
            ->cursorPaginate($this->perPage($request));

        return TriumphantExperienceVideoResource::collection($videos)
            ->additional(['feature' => $this->featurePayload($request)]);
    }

    public function show(Request $request, string $uuid): mixed
    {
        if (! $this->enabled()) {
            return $this->disabledResponse();
        }

        $video = GoshenExperienceVideo::query()->with('event')->where('uuid', $uuid)->first();
        if (! $video) {
            abort(404);
        }

        if (! $video->isFeedVisible()) {
            $user = $this->mobileUser($request);
            abort_unless($user && $user->id === $video->mobile_user_id, 404);
        }

        return new TriumphantExperienceVideoResource($video);
    }

    private function acceptedResponse(GoshenExperienceVideo $video): JsonResponse
    {
        return response()->json([
            'status' => 'accepted',
            'message' => 'Your video was received securely. YouTube upload and moderation continue in the background.',
            'data' => (new TriumphantExperienceVideoResource($video))->resolve(),
        ], 202);
    }

    private function activeSubmissionResponse(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'You already have a Triumphant Experience video for this retreat. Contact the team if you need help with it.',
        ], 409);
    }

    private function existingForClientUpload(MobileUser $user, mixed $clientUploadId): ?GoshenExperienceVideo
    {
        if (! is_string($clientUploadId) || ! Str::isUuid($clientUploadId)) {
            return null;
        }

        return GoshenExperienceVideo::query()
            ->where('mobile_user_id', $user->id)
            ->where('client_upload_id', $clientUploadId)
            ->with('event')
            ->first();
    }

    private function activeSubmissionFor(MobileUser $user, Event $event, bool $lockForUpdate = false): ?GoshenExperienceVideo
    {
        $query = GoshenExperienceVideo::query()
            ->where('mobile_user_id', $user->id)
            ->where('event_id', $event->id)
            ->where('active_submission_key', 'active');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function enabled(): bool
    {
        return (bool) config('goshen_experience.enabled', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function featurePayload(Request $request): array
    {
        $user = $this->mobileUser($request);
        $events = Event::query()
            ->latest('id')
            ->limit(25)
            ->get()
            ->filter(fn (Event $event): bool => $this->eligibleEvent($event));

        $eligibleEvent = null;
        if ($user) {
            $eligibleEvent = $events->first(
                fn (Event $event): bool => $this->eligibility->eligibleTicketFor($user, $event) !== null,
            );
        }

        $fallbackEvent = $eligibleEvent ?: $events->first();
        $connection = GoshenYouTubeConnection::query()
            ->whereIn('health', [
                GoshenYouTubeConnection::HEALTH_HEALTHY,
                GoshenYouTubeConnection::HEALTH_QUOTA_BLOCKED,
            ])
            ->orderBy('id')
            ->first();

        return [
            'enabled' => $this->enabled(),
            'eligible_to_submit' => $eligibleEvent !== null,
            'event_id' => $fallbackEvent?->public_id,
            'event_label' => $fallbackEvent?->name,
            'consent_version' => (string) config('goshen_experience.release_version'),
            'release_copy' => 'The app uploads securely to MFM first. The approved channel receives it only after moderation.',
            'channel_label' => $connection?->channel_title,
            'visibility_label' => $connection?->default_privacy,
            'sharing_enabled' => false,
        ];
    }

    private function eligibleEvent(Event $event): bool
    {
        $settings = is_array($event->settings) ? $event->settings : [];
        $module = strtolower(trim((string) ($settings['module'] ?? $settings['app_module'] ?? '')));
        if (array_key_exists('triumphant_experience_enabled', $settings) && ! filter_var($settings['triumphant_experience_enabled'], FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return in_array($module, ['goshen_retreat', 'goshen-retreat'], true)
            || str_starts_with(strtolower((string) $event->slug), 'goshen-')
            || str_contains(strtolower((string) $event->name), 'goshen retreat');
    }

    private function requireMobileUser(Request $request): MobileUser|JsonResponse
    {
        $user = $this->mobileUser($request);
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Please sign in to continue.'], 401);
        }

        if (! $user->canUseCommunity()) {
            return response()->json(['status' => 'error', 'message' => 'Please verify your email address before sharing a Triumphant Experience video.'], 403);
        }

        return $user;
    }

    private function mobileUser(Request $request): ?MobileUser
    {
        $authenticated = $request->user('mobile');
        if ($authenticated instanceof MobileUser) {
            $authenticated->markApiSeen();

            return $authenticated;
        }

        $token = $request->bearerToken();
        if (! is_string($token) || $token === '') {
            return null;
        }

        $user = MobileUser::query()->where('api_token_hash', hash('sha256', $token))->first();
        $user?->markApiSeen();

        return $user;
    }

    private function maximumFileSizeKilobytes(): int
    {
        return max(1, (int) ceil(((int) config('goshen_experience.max_bytes', 104_857_600)) / 1024));
    }

    /** @return list<string> */
    private function allowedMimeTypes(): array
    {
        $allowed = config('goshen_experience.allowed_mime_types', ['video/mp4']);

        return is_array($allowed)
            ? array_values(array_filter($allowed, 'is_string'))
            : ['video/mp4'];
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 50);
    }

    private function disabledResponse(): JsonResponse
    {
        return response()->json(['status' => 'disabled', 'message' => 'Triumphant Experience videos are not available right now.'], 403);
    }
}
