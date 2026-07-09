<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityPrayerCommentResource;
use App\Http\Resources\CommunityPrayerRequestResource;
use App\Http\Resources\PropheticDecreeResource;
use App\Models\CommunityPrayerAiLog;
use App\Models\CommunityPrayerCommentSuggestion;
use App\Models\CommunityPrayerRequest;
use App\Models\CommunityPrayerRequestComment;
use App\Models\CommunityPrayerRequestFlag;
use App\Models\MobileUser;
use App\Models\PropheticDecree;
use App\Services\CommunityPrayerQuotaService;
use App\Services\PrayerAiService;
use App\Services\PrayerModerationNotifier;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PrayerCommunityController extends Controller
{
    private const PRESETS = [
        'I pray for you',
        'I pray for mercy',
        "God's mercy on you",
        'May God strengthen you',
        'You are in my prayers',
        'The Lord will answer you',
        'Peace of God be with you',
    ];

    public function index(Request $request)
    {
        $data = $this->payload($request);
        $user = $this->verifiedUser($request);
        $query = CommunityPrayerRequest::query()
            ->with(['mobileUser', 'comments' => fn ($query) => $query->visible()->with('mobileUser')->latest()->limit(5), 'suggestions'])
            ->visible()
            ->latest();

        if (($data['type'] ?? null) && in_array($data['type'], ['text', 'audio'], true)) {
            $query->where('type', $data['type']);
        }

        $perPage = min(max((int) ($data['per_page'] ?? 20), 1), 50);
        $page = max((int) ($data['page'] ?? 1), 1);
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 'ok',
            'prophetic_decree' => $this->activePropheticDecreePayload($request),
            'prayer_requests' => CommunityPrayerRequestResource::collection(collect($paginator->items())),
            'preset_responses' => self::PRESETS,
            'submission_status' => $user
                ? app(CommunityPrayerQuotaService::class)->availability($user)
                : ['can_submit_prayer' => false, 'message' => 'Please sign in to submit a prayer request.'],
            'isLastPage' => ! $paginator->hasMorePages(),
        ]);
    }

    public function activePropheticDecree(Request $request)
    {
        return response()->json([
            'status' => 'ok',
            'prophetic_decree' => $this->activePropheticDecreePayload($request),
        ]);
    }

    public function propheticDecreeAudio(PropheticDecree $propheticDecree)
    {
        return $this->audioResponse($propheticDecree->audio_path, $propheticDecree->title ?: 'prophetic-decree');
    }

    public function replacePropheticDecree(Request $request)
    {
        $user = $this->verifiedUser($request);
        if (! $user) {
            return $this->authError();
        }
        if (! $user->canManagePropheticDecree()) {
            return response()->json(['status' => 'error', 'msg' => 'Only the G.O or Triumphant Main pastor can add Prophetic Decree.'], 403);
        }
        if ($this->limited($request, "prophetic-decree:{$user->id}", 6)) {
            return $this->rateError();
        }

        $data = $this->payload($request);
        $validated = Validator::make($data + ['audio' => $request->file('audio')], [
            'audio' => ['required', 'file', 'extensions:mp3,m4a,aac,wav,ogg,webm', 'max:12288'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:600'],
            'audio_duration_seconds' => ['nullable', 'integer', 'min:1', 'max:600'],
            'title' => ['nullable', 'string', 'max:120'],
        ])->validate();

        $file = $request->file('audio');
        $path = $file->store('prayer-community/prophetic-decrees', 'public');
        $oldPaths = [];

        $decree = DB::transaction(function () use ($file, $path, $user, $validated, &$oldPaths) {
            $oldPaths = PropheticDecree::where('is_active', true)
                ->whereNotNull('audio_path')
                ->pluck('audio_path')
                ->all();

            PropheticDecree::where('is_active', true)->update(['is_active' => false]);

            return PropheticDecree::create([
                'go_user_id' => $user->id,
                'title' => $validated['title'] ?? 'Prophetic Decree',
                'audio_path' => $path,
                'duration' => $validated['duration'] ?? $validated['audio_duration_seconds'] ?? null,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'is_active' => true,
                'expires_at' => now()->addDay(),
            ])->load('goUser');
        });

        foreach ($oldPaths as $oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json([
            'status' => 'ok',
            'prophetic_decree' => new PropheticDecreeResource($decree),
        ], 201);
    }

    public function show(Request $request, CommunityPrayerRequest $communityPrayerRequest)
    {
        if ($communityPrayerRequest->hidden_at || $communityPrayerRequest->expires_at->isPast()) {
            return response()->json(['status' => 'error', 'msg' => 'Prayer request is not available.'], 404);
        }

        $communityPrayerRequest->load([
            'mobileUser',
            'comments' => fn ($query) => $query->visible()->with('mobileUser')->latest(),
            'suggestions',
        ]);

        return response()->json([
            'status' => 'ok',
            'prayer_request' => new CommunityPrayerRequestResource($communityPrayerRequest),
            'preset_responses' => self::PRESETS,
        ]);
    }

    public function audio(CommunityPrayerRequest $communityPrayerRequest)
    {
        if (! $this->isPubliclyOpen($communityPrayerRequest) || ! $communityPrayerRequest->audio_path) {
            abort(404);
        }

        return $this->audioResponse($communityPrayerRequest->audio_path, 'prayer-request-'.$communityPrayerRequest->id);
    }

    public function store(Request $request, PrayerAiService $ai)
    {
        $user = $this->verifiedUser($request);
        if (! $user) {
            return $this->authError();
        }
        if ($this->limited($request, "prayer-submit:{$user->id}", 4)) {
            return $this->rateError();
        }
        $availability = app(CommunityPrayerQuotaService::class)->availability($user);
        if (! $availability['can_submit_prayer']) {
            return response()->json([
                'status' => 'error',
                'message' => $availability['message'],
                'msg' => $availability['message'],
                ...$availability,
            ], 429);
        }

        $data = $this->payload($request);
        if (($data['type'] ?? null) !== 'audio') {
            unset($data['audio_duration_seconds']);
        }

        $validator = Validator::make($data + ['audio' => $request->file('audio')], [
            'type' => ['required', Rule::in(['text', 'audio'])],
            'text' => ['nullable', 'string', 'max:3000', 'required_if:type,text'],
            'audio' => ['nullable', 'file', 'extensions:mp3,m4a,aac,wav,ogg,webm', 'max:8192', 'required_if:type,audio'],
            'audio_duration_seconds' => ['exclude_unless:type,audio', 'required_if:type,audio', 'integer', 'min:1', 'max:60'],
            'is_anonymous' => ['nullable', 'boolean'],
        ]);
        $validated = $validator->validate();

        $audioPath = null;
        if (($validated['type'] ?? 'text') === 'audio') {
            $audioPath = $request->file('audio')->store('prayer-community/audio', 'public');
        }

        $prayer = CommunityPrayerRequest::create([
            'mobile_user_id' => $user->id,
            'type' => $validated['type'],
            'text' => isset($validated['text']) ? str($validated['text'])->squish()->toString() : null,
            'audio_path' => $audioPath,
            'audio_duration_seconds' => $validated['audio_duration_seconds'] ?? null,
            'is_anonymous' => (bool) ($validated['is_anonymous'] ?? true),
            'expires_at' => now()->addDay(),
        ]);

        $this->saveSuggestions($prayer, $ai->suggestions($prayer->text ?: 'Audio prayer request'));

        return response()->json([
            'status' => 'ok',
            'prayer_request' => new CommunityPrayerRequestResource($prayer->load(['mobileUser', 'suggestions'])),
            'submission_status' => app(CommunityPrayerQuotaService::class)->availability($user),
        ], 201);
    }

    public function comment(Request $request, CommunityPrayerRequest $communityPrayerRequest)
    {
        $user = $this->verifiedUser($request);
        if (! $user) {
            return $this->authError();
        }
        if (! $this->isPubliclyOpen($communityPrayerRequest)) {
            return response()->json(['status' => 'error', 'msg' => 'Prayer request is not commentable.'], 409);
        }
        if ((int) $communityPrayerRequest->mobile_user_id === (int) $user->id) {
            return response()->json([
                'status' => 'error',
                'msg' => 'You cannot respond to your own prayer request.',
                'message' => 'You cannot respond to your own prayer request.',
            ], 403);
        }
        if ($this->limited($request, "prayer-comment:{$user->id}", 15)) {
            return $this->rateError();
        }

        $data = $this->payload($request);
        $type = $data['type'] ?? 'text';

        $validated = Validator::make($data + ['audio' => $request->file('audio')], [
            'type' => ['nullable', Rule::in(['text', 'audio'])],
            'text' => [$type === 'audio' ? 'nullable' : 'required', 'string', 'max:1000'],
            'audio' => [$type === 'audio' ? 'required' : 'nullable', 'file', 'extensions:mp3,m4a,aac,wav,ogg,webm', 'max:8192'],
            'audio_duration_seconds' => [$type === 'audio' ? 'required' : 'nullable', 'integer', 'min:1', 'max:10'],
            'source' => ['nullable', Rule::in(['manual', 'preset', 'ai_suggestion'])],
            'preset_key' => ['nullable', 'string', 'max:80'],
            'is_anonymous' => ['nullable', 'boolean'],
        ])->validate();

        $audioPath = null;
        if ($type === 'audio') {
            $audioPath = $request->file('audio')->store('prayer-community/comments', 'public');
        }

        $comment = CommunityPrayerRequestComment::create([
            'community_prayer_request_id' => $communityPrayerRequest->id,
            'mobile_user_id' => $user->id,
            'text' => isset($validated['text']) ? str($validated['text'])->squish()->toString() : null,
            'audio_path' => $audioPath,
            'audio_duration_seconds' => $validated['audio_duration_seconds'] ?? null,
            'source' => $validated['source'] ?? 'manual',
            'preset_key' => $validated['preset_key'] ?? null,
            'is_anonymous' => (bool) ($validated['is_anonymous'] ?? true),
        ]);
        $communityPrayerRequest->increment('comments_count');

        return response()->json([
            'status' => 'ok',
            'comment' => new CommunityPrayerCommentResource($comment->load('mobileUser')),
        ], 201);
    }

    public function commentAudio(CommunityPrayerRequestComment $communityPrayerRequestComment)
    {
        if ($communityPrayerRequestComment->hidden_at) {
            abort(404);
        }

        $prayer = $communityPrayerRequestComment->prayerRequest;
        if (! $prayer || ! $this->isPubliclyOpen($prayer) || ! $communityPrayerRequestComment->audio_path) {
            abort(404);
        }

        return $this->audioResponse($communityPrayerRequestComment->audio_path, 'prayer-comment-'.$communityPrayerRequestComment->id);
    }

    public function flag(Request $request, CommunityPrayerRequest $communityPrayerRequest)
    {
        $user = $this->verifiedUser($request);
        if (! $user) {
            return $this->authError();
        }
        if ($this->limited($request, "prayer-flag:{$user->id}", 10)) {
            return $this->rateError();
        }

        $validated = Validator::make($this->payload($request), [
            'reason' => ['nullable', 'string', 'max:80'],
            'details' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $existingFlag = CommunityPrayerRequestFlag::where([
            'community_prayer_request_id' => $communityPrayerRequest->id,
            'mobile_user_id' => $user->id,
        ])->first();

        if ($existingFlag) {
            return response()->json([
                'status' => 'error',
                'msg' => 'You have already flagged this prayer request.',
                'message' => 'You have already flagged this prayer request.',
                'flags_count' => $communityPrayerRequest->fresh()->flags_count,
            ], 409);
        }

        CommunityPrayerRequestFlag::create([
            'community_prayer_request_id' => $communityPrayerRequest->id,
            'mobile_user_id' => $user->id,
            'reason' => $validated['reason'] ?? 'inappropriate',
            'details' => $validated['details'] ?? null,
        ]);

        $communityPrayerRequest->forceFill([
            'flags_count' => $communityPrayerRequest->flags()->count(),
        ])->save();

        $freshRequest = $communityPrayerRequest->fresh(['mobileUser']);
        if ($freshRequest->flags_count >= CommunityPrayerRequest::AUTO_HIDE_FLAG_THRESHOLD && ! $freshRequest->hidden_at) {
            $freshRequest->hide('Automatically hidden after 3 community flags.');
            app(PrayerModerationNotifier::class)->notifyAutoHidden($freshRequest->fresh(['mobileUser']));
        }

        return response()->json(['status' => 'ok', 'flags_count' => $communityPrayerRequest->fresh()->flags_count]);
    }

    public function aiRewrite(Request $request, PrayerAiService $ai)
    {
        $user = $this->verifiedUser($request);
        if (! $user) {
            return $this->authError();
        }
        if ($this->limited($request, "prayer-ai:{$user->id}", 20)) {
            return $this->rateError();
        }

        $validated = Validator::make($this->payload($request), [
            'text' => ['required', 'string', 'max:3000'],
            'kind' => ['nullable', Rule::in(['prayer request', 'comment'])],
        ])->validate();

        $rewritten = $ai->rewrite($validated['text'], $validated['kind'] ?? 'prayer request');
        CommunityPrayerAiLog::create([
            'mobile_user_id' => $user->id,
            'action' => 'rewrite',
            'input_hash' => hash('sha256', $validated['text']),
            'output' => ['text' => $rewritten],
        ]);

        return response()->json(['status' => 'ok', 'text' => $rewritten]);
    }

    public function aiSuggestions(Request $request, PrayerAiService $ai)
    {
        $user = $this->verifiedUser($request);
        if (! $user) {
            return $this->authError();
        }
        if ($this->limited($request, "prayer-ai:{$user->id}", 20)) {
            return $this->rateError();
        }

        $validated = Validator::make($this->payload($request), [
            'text' => ['required', 'string', 'max:3000'],
            'prayer_request_id' => ['nullable', 'integer', 'exists:community_prayer_requests,id'],
        ])->validate();

        $suggestions = $ai->suggestions($validated['text']);
        CommunityPrayerAiLog::create([
            'mobile_user_id' => $user->id,
            'community_prayer_request_id' => $validated['prayer_request_id'] ?? null,
            'action' => 'suggestions',
            'input_hash' => hash('sha256', $validated['text']),
            'output' => ['suggestions' => $suggestions],
        ]);

        return response()->json(['status' => 'ok', 'suggestions' => $suggestions]);
    }

    public function aiBibleExplain(Request $request, PrayerAiService $ai)
    {
        $user = $this->verifiedUser($request);
        if (! $user) {
            return $this->authError();
        }
        if ($this->limited($request, "bible-ai:{$user->id}", 20)) {
            return $this->rateError();
        }

        $validated = Validator::make($this->payload($request), [
            'reference' => ['required', 'string', 'max:200'],
            'text' => ['required', 'string', 'max:2000'],
        ])->validate();

        $result = $ai->explainVerse($validated['reference'], $validated['text']);
        CommunityPrayerAiLog::create([
            'mobile_user_id' => $user->id,
            'action' => 'bible_explain',
            'input_hash' => hash('sha256', $validated['reference'].':'.$validated['text']),
            'output' => ['explanation' => $result['explanation']],
        ]);

        return response()->json(['status' => 'ok', 'explanation' => $result['explanation']]);
    }

    public function aiBibleSearch(Request $request, PrayerAiService $ai)
    {
        $user = $this->verifiedUser($request);
        if (! $user) {
            return $this->authError();
        }
        if ($this->limited($request, "bible-ai:{$user->id}", 20)) {
            return $this->rateError();
        }

        $validated = Validator::make($this->payload($request), [
            'topic' => ['required', 'string', 'max:500'],
        ])->validate();

        $result = $ai->searchBibleByTopic($validated['topic']);
        CommunityPrayerAiLog::create([
            'mobile_user_id' => $user->id,
            'action' => 'bible_search',
            'input_hash' => hash('sha256', $validated['topic']),
            'output' => ['results' => $result['results']],
        ]);

        return response()->json(['status' => 'ok', 'results' => $result['results']]);
    }

    public function updateProfileImage(Request $request)
    {
        $user = $this->verifiedUser($request);
        if (! $user) {
            return $this->authError();
        }

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->forceFill([
            'avatar' => $request->file('avatar')->store('mobile-users/avatars', 'public'),
        ])->save();

        return response()->json(['status' => 'ok', 'user' => $this->mobileUserPayload($user)]);
    }

    private function verifiedUser(Request $request): ?MobileUser
    {
        $authenticated = $request->user('mobile') ?? $request->user();
        if ($authenticated instanceof MobileUser) {
            return $authenticated->canUseCommunity() ? $authenticated : null;
        }

        $data = $this->payload($request);
        $token = $data['api_token'] ?? $request->bearerToken();
        $query = MobileUser::query();

        if ($token) {
            $query->where('api_token_hash', hash('sha256', $token));
        } elseif (! empty($data['email'])) {
            $query->where('email', $data['email']);
        } else {
            return null;
        }

        $user = $query->first();

        if (! $user && $token && ! empty($data['email'])) {
            // Legacy mobile installs may keep an old token after an in-place APK
            // upgrade. Fall back to the saved account email so verified users are
            // not locked out of community actions until their next login refresh.
            $user = MobileUser::where('email', $data['email'])->first();
        }

        if (! $user?->canUseCommunity()) {
            return null;
        }

        $user->markApiSeen();

        return $user;
    }

    private function activePropheticDecreePayload(Request $request): ?PropheticDecreeResource
    {
        $decree = PropheticDecree::with('goUser')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();

        return $decree ? new PropheticDecreeResource($decree) : null;
    }

    private function payload(Request $request): array
    {
        $data = $request->input('data');
        if (is_string($data)) {
            $decoded = json_decode($data, true);

            return is_array($decoded) ? $decoded : [];
        }
        if (is_array($data)) {
            return $data;
        }
        $decoded = json_decode($request->getContent(), true);

        return is_array($decoded['data'] ?? null) ? $decoded['data'] : (is_array($decoded) ? $decoded : $request->all());
    }

    private function isPubliclyOpen(CommunityPrayerRequest $prayer): bool
    {
        return ! $prayer->hidden_at && $prayer->expires_at->isFuture();
    }

    private function saveSuggestions(CommunityPrayerRequest $prayer, array $suggestions): void
    {
        foreach (array_merge(self::PRESETS, $suggestions) as $index => $suggestion) {
            CommunityPrayerCommentSuggestion::create([
                'community_prayer_request_id' => $prayer->id,
                'source' => $index < count(self::PRESETS) ? 'preset' : 'ai',
                'preset_key' => str($suggestion)->slug()->toString(),
                'text' => $suggestion,
            ]);
        }
    }

    private function limited(Request $request, string $key, int $max): bool
    {
        $key .= ':'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, $max)) {
            return true;
        }

        RateLimiter::hit($key, 60);

        return false;
    }

    private function authError()
    {
        return response()->json(['status' => 'error', 'msg' => 'Verified account required.'], 403);
    }

    private function rateError()
    {
        return response()->json(['status' => 'error', 'msg' => 'Too many attempts. Please try again shortly.'], 429);
    }

    private function mobileUserPayload(MobileUser $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar ? MediaUrl::resolve($user->avatar) : '',
            'cover_photo' => $user->cover_photo ? MediaUrl::resolve($user->cover_photo) : '',
            'roles' => $user->roles()->pluck('name')->values(),
            'is_go' => $user->hasGeneralOverseerRole(),
            'can_manage_prophetic_decree' => $user->canManagePropheticDecree(),
            'activated' => $user->canUseCommunity() ? 0 : 1,
        ];
    }

    private function audioResponse(?string $path, string $name)
    {
        abort_if(blank($path) || ! Storage::disk('public')->exists($path), 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'mp3' => 'audio/mpeg',
            'm4a', 'aac' => 'audio/mp4',
            'ogg' => 'audio/ogg',
            'webm' => 'audio/webm',
            'wav' => 'audio/wav',
            default => 'application/octet-stream',
        };

        return response()->file(Storage::disk('public')->path($path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.str($name)->slug()->toString().'.'.$extension.'"',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
