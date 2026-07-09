<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestimonyResource;
use App\Models\AppSetting;
use App\Models\MobileUser;
use App\Models\Testimony;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TestimonyController extends Controller
{
    public function status()
    {
        return response()->json([
            'status' => 'ok',
            'enabled' => $this->enabled(),
        ]);
    }

    public function index(Request $request)
    {
        if (! $this->enabled()) {
            return $this->disabledResponse();
        }

        $perPage = min(max((int) ($request->input('per_page') ?? 20), 1), 50);
        $page = max((int) ($request->input('page') ?? 1), 1);
        $paginator = Testimony::query()
            ->with('mobileUser')
            ->approved()
            ->orderByRaw('COALESCE(approved_at, created_at) DESC')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 'ok',
            'enabled' => true,
            'testimonies' => TestimonyResource::collection(collect($paginator->items())),
            'isLastPage' => ! $paginator->hasMorePages(),
        ]);
    }

    public function store(Request $request)
    {
        if (! $this->enabled()) {
            return $this->disabledResponse();
        }

        $user = $this->verifiedUser($request);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Please sign in with a verified account before submitting a testimony.',
                'message' => 'Please sign in with a verified account before submitting a testimony.',
            ], 403);
        }

        if ($this->limited($request, "testimony-submit:{$user->id}", 4)) {
            return response()->json(['status' => 'error', 'msg' => 'Too many attempts. Please try again shortly.'], 429);
        }

        $data = $this->payload($request);
        $validated = Validator::make($data + ['audio' => $request->file('audio')], [
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'audio' => ['nullable', 'file', 'extensions:mp3,m4a,aac,wav,ogg,webm', 'max:16384'],
            'audio_duration_seconds' => ['nullable', 'integer', 'min:1', 'max:120'],
            'is_anonymous' => ['nullable', 'boolean'],
        ])->validate();

        if ($request->hasFile('audio') && empty($validated['audio_duration_seconds'])) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Audio duration is required for audio testimonies.',
                'message' => 'Audio duration is required for audio testimonies.',
            ], 422);
        }

        $audioPath = $request->hasFile('audio')
            ? $request->file('audio')->store('testimonies/audio', 'public')
            : null;

        $testimony = Testimony::create([
            'mobile_user_id' => $user->id,
            'title' => str($validated['title'])->squish()->toString(),
            'body' => str($validated['body'])->squish()->toString(),
            'audio_path' => $audioPath,
            'audio_duration_seconds' => $validated['audio_duration_seconds'] ?? null,
            'is_anonymous' => (bool) ($validated['is_anonymous'] ?? false),
            'status' => Testimony::STATUS_PENDING,
        ])->load('mobileUser');

        return response()->json([
            'status' => 'ok',
            'message' => 'Thank you for sharing. Your testimony has been submitted and is awaiting admin approval.',
            'msg' => 'Thank you for sharing. Your testimony has been submitted and is awaiting admin approval.',
            'testimony' => new TestimonyResource($testimony),
        ], 201);
    }

    public function audio(Testimony $testimony)
    {
        if (! $this->enabled() || $testimony->status !== Testimony::STATUS_APPROVED || ! $testimony->audio_path) {
            abort(404);
        }

        return $this->audioResponse($testimony->audio_path, 'testimony-'.$testimony->id);
    }

    private function enabled(): bool
    {
        return filter_var(AppSetting::value('testimonies_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function disabledResponse()
    {
        return response()->json([
            'status' => 'disabled',
            'enabled' => false,
            'msg' => 'The Testimonies & Thanksgiving Wall is not available right now.',
            'message' => 'The Testimonies & Thanksgiving Wall is not available right now.',
        ], 403);
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
            $user = MobileUser::where('email', $data['email'])->first();
        }

        if (! $user?->canUseCommunity()) {
            return null;
        }

        $user->markApiSeen();

        return $user;
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

    private function limited(Request $request, string $key, int $max): bool
    {
        $key .= ':'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, $max)) {
            return true;
        }

        RateLimiter::hit($key, 60);

        return false;
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
