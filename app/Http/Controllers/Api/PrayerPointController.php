<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileUser;
use App\Models\PrayerPoint;
use App\Support\MediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PrayerPointController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $this->payload($request);
        $perPage = min(max((int) ($data['per_page'] ?? 50), 1), 100);
        $wallOnly = filter_var($data['wall'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $points = PrayerPoint::query()
            ->where('is_published', true)
            ->when($wallOnly, fn ($query) => $query->where('show_on_prayer_wall', true))
            ->orderByRaw('date IS NULL')
            ->orderByDesc('date')
            ->latest()
            ->limit($perPage)
            ->get();

        return response()->json([
            'status' => 'ok',
            'data' => $points->map(fn (PrayerPoint $point): array => $this->pointPayload($point))->values(),
            'prayer_points' => $points->map(fn (PrayerPoint $point): array => $this->pointPayload($point))->values(),
        ]);
    }

    public function managementIndex(Request $request): JsonResponse
    {
        if ($response = $this->authorizeManager($request)) {
            return $response;
        }

        $data = $this->payload($request);
        $perPage = min(max((int) ($data['per_page'] ?? 100), 1), 100);

        $points = PrayerPoint::query()
            ->orderByDesc('is_published')
            ->orderByRaw('date IS NULL')
            ->orderByDesc('date')
            ->latest()
            ->limit($perPage)
            ->get();

        return response()->json([
            'status' => 'ok',
            'data' => [
                'prayer_points' => $points->map(fn (PrayerPoint $point): array => $this->pointPayload($point))->values(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($this->isReadOnlyListPayload($request)) {
            return $this->managementIndex($request);
        }

        if ($response = $this->authorizeManager($request)) {
            return $response;
        }

        $validator = Validator::make($this->payload($request), $this->rules());

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $point = PrayerPoint::query()->create($this->attributes($validator->validated()));

        return response()->json([
            'status' => 'ok',
            'message' => 'Prayer point created.',
            'data' => $this->pointPayload($point),
        ], 201);
    }

    public function update(Request $request, PrayerPoint $prayerPoint): JsonResponse
    {
        if ($response = $this->authorizeManager($request)) {
            return $response;
        }

        $validator = Validator::make($this->payload($request), $this->rules());

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $prayerPoint->forceFill($this->attributes($validator->validated()))->save();

        return response()->json([
            'status' => 'ok',
            'message' => 'Prayer point updated.',
            'data' => $this->pointPayload($prayerPoint->fresh()),
        ]);
    }

    public function status(Request $request, PrayerPoint $prayerPoint): JsonResponse
    {
        if ($response = $this->authorizeManager($request)) {
            return $response;
        }

        $validator = Validator::make($this->payload($request), [
            'is_published' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $prayerPoint->forceFill([
            'is_published' => (bool) $validator->validated()['is_published'],
        ])->save();

        return response()->json([
            'status' => 'ok',
            'message' => $prayerPoint->is_published ? 'Prayer point published.' : 'Prayer point unpublished.',
            'data' => $this->pointPayload($prayerPoint->fresh()),
        ]);
    }

    public function destroy(Request $request, PrayerPoint $prayerPoint): JsonResponse
    {
        if ($response = $this->authorizeManager($request)) {
            return $response;
        }

        $prayerPoint->delete();

        return response()->json([
            'status' => 'ok',
            'message' => 'Prayer point deleted.',
        ]);
    }

    private function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'title' => ['required', 'string', 'max:180'],
            'author' => ['nullable', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:20000'],
            'thumbnail' => ['nullable', 'string', 'max:1000'],
            'is_published' => ['nullable', 'boolean'],
            'show_on_prayer_wall' => ['nullable', 'boolean'],
        ];
    }

    private function attributes(array $validated): array
    {
        return [
            'date' => $validated['date'] ?? null,
            'title' => str($validated['title'])->squish()->toString(),
            'author' => isset($validated['author']) ? str($validated['author'])->squish()->toString() : null,
            'content' => trim((string) $validated['content']),
            'thumbnail' => $validated['thumbnail'] ?? null,
            'is_published' => (bool) ($validated['is_published'] ?? false),
            'show_on_prayer_wall' => (bool) ($validated['show_on_prayer_wall'] ?? true),
        ];
    }

    private function pointPayload(PrayerPoint $point): array
    {
        return [
            'id' => $point->id,
            'date' => $point->date?->toDateString(),
            'title' => $point->title,
            'author' => $point->author,
            'content' => $point->content,
            'thumbnail' => $point->thumbnail,
            'thumbnail_url' => MediaUrl::resolve($point->thumbnail),
            'is_published' => (bool) $point->is_published,
            'show_on_prayer_wall' => (bool) $point->show_on_prayer_wall,
            'created_at' => $point->created_at?->toIso8601String(),
            'updated_at' => $point->updated_at?->toIso8601String(),
        ];
    }

    private function authorizeManager(Request $request): ?JsonResponse
    {
        $user = $this->mobileUserFromRequest($request);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in before managing prayer points.',
            ], 401);
        }

        if (! $this->canManagePrayerPoints($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is not authorized to manage prayer points.',
            ], 403);
        }

        return null;
    }

    private function canManagePrayerPoints(MobileUser $user): bool
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

        if ($user->can('manage_prayer_points')
            || $user->can('manage_prayer_point')
            || $user->can('manage_prayers')
            || $user->can('manage_prayer')
            || $user->can('manage_content')) {
            return true;
        }

        return $user->roles()
            ->pluck('name')
            ->contains(fn ($role): bool => in_array(
                str($role)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                [
                    'admin',
                    'superadmin',
                    'contentmanager',
                    'prayermanager',
                    'prayerpointsmanager',
                    'prayerpointmanager',
                    'eventmanager',
                    'goshenmanager',
                    'go',
                    'generaloverseer',
                    'triumphantitmanager',
                ],
                true,
            ));
    }

    private function mobileUserFromRequest(Request $request): ?MobileUser
    {
        $data = $this->payload($request);
        $token = $request->bearerToken() ?: ($data['api_token'] ?? $request->input('api_token'));

        if (! is_string($token) || $token === '') {
            return null;
        }

        $user = MobileUser::query()
            ->where('api_token_hash', hash('sha256', $token))
            ->first();

        $user?->markApiSeen();

        return $user;
    }

    private function isReadOnlyListPayload(Request $request): bool
    {
        $payload = $this->payload($request);

        if ($payload === []) {
            return false;
        }

        $readOnlyKeys = ['api_token', 'email', 'page', 'per_page'];

        return collect(array_keys($payload))
            ->every(fn (string $key): bool => in_array($key, $readOnlyKeys, true));
    }

    private function validationError($validator): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422);
    }

    private function payload(Request $request): array
    {
        $payload = $request->input('data', $request->all());
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($payload) ? $payload : [];
    }
}
