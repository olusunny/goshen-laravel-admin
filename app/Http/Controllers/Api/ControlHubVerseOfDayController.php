<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileUser;
use App\Models\VerseOfDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ControlHubVerseOfDayController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeManager($request)) {
            return $response;
        }

        $validator = Validator::make($this->payload($request), [
            'query' => ['nullable', 'string', 'max:160'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $validated = $validator->validated();
        $queryText = trim((string) ($validated['query'] ?? ''));
        $perPage = min(max((int) ($validated['per_page'] ?? 100), 1), 100);

        $verses = VerseOfDay::query()
            ->when($queryText !== '', function ($query) use ($queryText): void {
                $query->where(function ($search) use ($queryText): void {
                    $search
                        ->where('reference', 'like', "%{$queryText}%")
                        ->orWhere('version', 'like', "%{$queryText}%")
                        ->orWhere('text', 'like', "%{$queryText}%");
                });
            })
            ->orderByDesc('is_published')
            ->orderByDesc('date')
            ->latest()
            ->limit($perPage)
            ->get();

        return response()->json([
            'status' => 'ok',
            'data' => [
                'verses' => $verses->map(fn (VerseOfDay $verse): array => $this->versePayload($verse))->values(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($this->isReadOnlyListPayload($request)) {
            return $this->index($request);
        }

        if ($response = $this->authorizeManager($request)) {
            return $response;
        }

        $validator = Validator::make($this->payload($request), $this->rules());

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $verse = VerseOfDay::query()->create($this->attributes($validator->validated()));

        return response()->json([
            'status' => 'ok',
            'message' => 'Verse of the Day created.',
            'data' => $this->versePayload($verse->fresh()),
        ], 201);
    }

    public function update(Request $request, VerseOfDay $verseOfDay): JsonResponse
    {
        if ($response = $this->authorizeManager($request)) {
            return $response;
        }

        $validator = Validator::make($this->payload($request), $this->rules($verseOfDay));

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $verseOfDay->forceFill($this->attributes($validator->validated(), $verseOfDay))->save();

        return response()->json([
            'status' => 'ok',
            'message' => 'Verse of the Day updated.',
            'data' => $this->versePayload($verseOfDay->fresh()),
        ]);
    }

    public function status(Request $request, VerseOfDay $verseOfDay): JsonResponse
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

        $isPublished = (bool) $validator->validated()['is_published'];

        $verseOfDay->forceFill([
            'is_published' => $isPublished,
            'published_at' => $isPublished ? ($verseOfDay->published_at ?: now()) : $verseOfDay->published_at,
        ])->save();

        return response()->json([
            'status' => 'ok',
            'message' => $verseOfDay->is_published ? 'Verse published.' : 'Verse unpublished.',
            'data' => $this->versePayload($verseOfDay->fresh()),
        ]);
    }

    public function destroy(Request $request, VerseOfDay $verseOfDay): JsonResponse
    {
        if ($response = $this->authorizeManager($request)) {
            return $response;
        }

        $verseOfDay->delete();

        return response()->json([
            'status' => 'ok',
            'message' => 'Verse of the Day deleted.',
        ]);
    }

    private function rules(?VerseOfDay $verse = null): array
    {
        return [
            'date' => [
                'required',
                'date',
                Rule::unique('verse_of_days', 'date')->ignore($verse?->id),
            ],
            'reference' => ['required', 'string', 'max:120'],
            'version' => ['nullable', 'string', 'max:40'],
            'text' => ['required', 'string', 'max:20000'],
            'reflection' => ['nullable', 'string', 'max:20000'],
            'prayer' => ['nullable', 'string', 'max:20000'],
            'is_published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    private function attributes(array $validated, ?VerseOfDay $verse = null): array
    {
        $isPublished = (bool) ($validated['is_published'] ?? $verse?->is_published ?? false);

        return [
            'date' => $validated['date'],
            'reference' => str($validated['reference'])->squish()->toString(),
            'version' => str($validated['version'] ?? 'KJV')->squish()->toString() ?: 'KJV',
            'text' => trim((string) $validated['text']),
            'reflection' => isset($validated['reflection']) ? trim((string) $validated['reflection']) : null,
            'prayer' => isset($validated['prayer']) ? trim((string) $validated['prayer']) : null,
            'is_published' => $isPublished,
            'published_at' => $validated['published_at'] ?? ($isPublished ? ($verse?->published_at ?: now()) : null),
        ];
    }

    private function versePayload(VerseOfDay $verse): array
    {
        return [
            'id' => $verse->id,
            'date' => $verse->date?->toDateString(),
            'reference' => $verse->reference,
            'version' => $verse->version,
            'text' => $verse->text,
            'reflection' => $verse->reflection,
            'prayer' => $verse->prayer,
            'is_published' => (bool) $verse->is_published,
            'published_at' => $verse->published_at?->toIso8601String(),
            'created_at' => $verse->created_at?->toIso8601String(),
            'updated_at' => $verse->updated_at?->toIso8601String(),
        ];
    }

    private function authorizeManager(Request $request): ?JsonResponse
    {
        $user = $this->mobileUserFromRequest($request);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in before managing Verse of the Day.',
            ], 401);
        }

        if (! $this->canManageVerseOfDay($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is not authorized to manage Verse of the Day.',
            ], 403);
        }

        return null;
    }

    private function canManageVerseOfDay(MobileUser $user): bool
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

        if ($user->can('manage_verse_of_day')
            || $user->can('manage_verses')
            || $user->can('manage_devotionals')
            || $user->can('manage_devotional')
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
                    'devotionalmanager',
                    'versemanager',
                    'verseofdaymanager',
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

        $readOnlyKeys = ['api_token', 'email', 'query', 'page', 'per_page'];

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
