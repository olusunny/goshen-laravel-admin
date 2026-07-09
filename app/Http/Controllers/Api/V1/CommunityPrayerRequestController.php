<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityPrayerCommentResource;
use App\Http\Resources\CommunityPrayerCommentSuggestionResource;
use App\Http\Resources\CommunityPrayerRequestResource;
use App\Models\CommunityPrayerCommentSuggestion;
use App\Models\CommunityPrayerRequest;
use App\Models\CommunityPrayerRequestFlag;
use App\Models\MobileUser;
use App\Services\CommunityPrayerQuotaService;
use App\Services\PrayerModerationNotifier;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommunityPrayerRequestController extends Controller
{
    public function index(Request $request)
    {
        $requests = CommunityPrayerRequest::query()
            ->visible()
            ->with(['comments' => fn ($query) => $query->visible()->latest()->limit(25), 'suggestions'])
            ->latest()
            ->paginate((int) $request->integer('per_page', 15));

        return CommunityPrayerRequestResource::collection($requests);
    }

    public function store(Request $request)
    {
        $mobileUser = $this->verifiedMobileUser($request);
        $availability = app(CommunityPrayerQuotaService::class)->availability($mobileUser);
        if (! $availability['can_submit_prayer']) {
            return response()->json([
                'message' => $availability['message'],
                ...$availability,
            ], 429);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(['text', 'audio'])],
            'text' => ['nullable', 'required_if:type,text', 'string', 'max:2000'],
            'audio' => ['nullable', 'required_if:type,audio', 'file', 'mimetypes:audio/mpeg,audio/mp4,audio/aac,audio/wav,audio/x-wav,audio/webm,audio/ogg', 'max:8192'],
            'audio_duration_seconds' => ['nullable', 'required_if:type,audio', 'integer', 'min:1', 'max:60'],
        ]);

        $audioPath = null;
        if ($request->hasFile('audio')) {
            $audioPath = $request->file('audio')->store('prayer-community/audio', 'public');
        }

        $prayerRequest = DB::transaction(function () use ($data, $audioPath, $mobileUser) {
            $prayerRequest = CommunityPrayerRequest::create([
                'mobile_user_id' => $mobileUser->id,
                'type' => $data['type'],
                'text' => $data['text'] ?? null,
                'audio_path' => $audioPath,
                'audio_duration_seconds' => $data['audio_duration_seconds'] ?? null,
                'is_anonymous' => true,
                'expires_at' => now()->addDay(),
            ]);

            $this->seedSuggestions($prayerRequest);

            return $prayerRequest->load(['comments', 'suggestions']);
        });

        return (new CommunityPrayerRequestResource($prayerRequest))
            ->response()
            ->setStatusCode(201);
    }

    public function comment(Request $request, CommunityPrayerRequest $communityPrayerRequest)
    {
        $mobileUser = $this->verifiedMobileUser($request);
        $this->abortUnlessVisible($communityPrayerRequest);
        abort_if(
            (int) $communityPrayerRequest->mobile_user_id === (int) $mobileUser->id,
            403,
            'You cannot respond to your own prayer request.'
        );

        $type = $request->input('type', 'text');

        $data = $request->validate([
            'type' => ['nullable', Rule::in(['text', 'audio'])],
            'text' => [$type === 'audio' ? 'nullable' : 'required', 'string', 'max:1000'],
            'audio' => [$type === 'audio' ? 'required' : 'nullable', 'file', 'mimetypes:audio/mpeg,audio/mp4,audio/aac,audio/wav,audio/x-wav,audio/webm,audio/ogg', 'max:8192'],
            'audio_duration_seconds' => [$type === 'audio' ? 'required' : 'nullable', 'integer', 'min:1', 'max:10'],
            'source' => ['nullable', Rule::in(['manual', 'preset', 'ai'])],
            'preset_key' => ['nullable', 'string', 'max:80'],
        ]);

        $audioPath = null;
        if ($request->hasFile('audio')) {
            $audioPath = $request->file('audio')->store('prayer-community/comments', 'public');
        }

        $comment = DB::transaction(function () use ($communityPrayerRequest, $data, $audioPath, $mobileUser) {
            $comment = $communityPrayerRequest->comments()->create([
                'mobile_user_id' => $mobileUser->id,
                'text' => $data['text'] ?? null,
                'audio_path' => $audioPath,
                'audio_duration_seconds' => $data['audio_duration_seconds'] ?? null,
                'source' => $data['source'] ?? 'manual',
                'preset_key' => $data['preset_key'] ?? null,
                'is_anonymous' => true,
            ]);

            $communityPrayerRequest->increment('comments_count');

            return $comment;
        });

        return (new CommunityPrayerCommentResource($comment))
            ->response()
            ->setStatusCode(201);
    }

    public function suggestions(Request $request, CommunityPrayerRequest $communityPrayerRequest)
    {
        $this->verifiedMobileUser($request);
        $this->abortUnlessVisible($communityPrayerRequest);
        $this->seedSuggestions($communityPrayerRequest);

        return CommunityPrayerCommentSuggestionResource::collection($communityPrayerRequest->suggestions()->get());
    }

    public function flag(Request $request, CommunityPrayerRequest $communityPrayerRequest)
    {
        $mobileUser = $this->verifiedMobileUser($request);
        $this->abortUnlessVisible($communityPrayerRequest);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:80'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $wasAutoHidden = false;
        try {
            DB::transaction(function () use ($communityPrayerRequest, $data, $mobileUser) {
                CommunityPrayerRequestFlag::create([
                    'community_prayer_request_id' => $communityPrayerRequest->id,
                    'mobile_user_id' => $mobileUser->id,
                    'reason' => $data['reason'],
                    'details' => $data['details'] ?? null,
                ]);

                $communityPrayerRequest->increment('flags_count');
                $communityPrayerRequest->refresh();

                if ($communityPrayerRequest->flags_count >= CommunityPrayerRequest::AUTO_HIDE_FLAG_THRESHOLD) {
                    $communityPrayerRequest->hide('auto_hidden_after_flags');
                }
            });
            $freshRequest = $communityPrayerRequest->fresh();
            $wasAutoHidden = $freshRequest->hidden_reason === 'auto_hidden_after_flags'
                && $freshRequest->flags_count >= CommunityPrayerRequest::AUTO_HIDE_FLAG_THRESHOLD;
        } catch (QueryException $exception) {
            return response()->json(['message' => 'You have already flagged this prayer request.'], 409);
        }

        if ($wasAutoHidden) {
            app(PrayerModerationNotifier::class)->notifyAutoHidden($communityPrayerRequest->fresh(['mobileUser']));
        }

        return response()->json([
            'status' => 'ok',
            'flags_count' => $communityPrayerRequest->fresh()->flags_count,
            'hidden' => $communityPrayerRequest->fresh()->hidden_at !== null,
        ]);
    }

    private function verifiedMobileUser(Request $request): MobileUser
    {
        $user = $request->user('mobile') ?? $request->user();

        abort_unless(
            $user instanceof MobileUser && $user->is_verified && ! $user->is_blocked && ! $user->is_deleted,
            403,
            'Only verified mobile users can use interactive prayer requests.'
        );

        return $user;
    }

    private function abortUnlessVisible(CommunityPrayerRequest $prayerRequest): void
    {
        abort_if($prayerRequest->hidden_at || $prayerRequest->expires_at->isPast(), 404);
    }

    private function seedSuggestions(CommunityPrayerRequest $prayerRequest): void
    {
        if ($prayerRequest->suggestions()->exists()) {
            return;
        }

        $suggestions = [
            ['source' => 'preset', 'preset_key' => 'praying', 'text' => 'I am praying with you. May God strengthen and comfort you.'],
            ['source' => 'preset', 'preset_key' => 'peace', 'text' => 'May the peace of God guard your heart through this season.'],
            ['source' => 'ai', 'preset_key' => 'encouragement', 'text' => $this->aiStyleSuggestion($prayerRequest)],
        ];

        foreach ($suggestions as $suggestion) {
            CommunityPrayerCommentSuggestion::create([
                'community_prayer_request_id' => $prayerRequest->id,
                ...$suggestion,
            ]);
        }
    }

    private function aiStyleSuggestion(CommunityPrayerRequest $prayerRequest): string
    {
        $topic = str($prayerRequest->text ?: 'this request')->lower()->limit(80, '');

        return "Lord, please meet this need around {$topic} with mercy, wisdom, and a testimony of Your goodness.";
    }
}
