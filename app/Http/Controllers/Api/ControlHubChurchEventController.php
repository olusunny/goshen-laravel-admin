<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChurchEvent;
use App\Models\MobileUser;
use App\Support\MediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ControlHubChurchEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeManager($request)) {
            return $response;
        }

        $data = $this->payload($request);
        $validator = Validator::make($data, [
            'query' => ['nullable', 'string', 'max:160'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $validated = $validator->validated();
        $queryText = trim((string) ($validated['query'] ?? ''));
        $perPage = min(max((int) ($validated['per_page'] ?? 100), 1), 100);

        $events = ChurchEvent::query()
            ->when($queryText !== '', function ($query) use ($queryText): void {
                $query->where(function ($search) use ($queryText): void {
                    $search
                        ->where('title', 'like', "%{$queryText}%")
                        ->orWhere('theme', 'like', "%{$queryText}%")
                        ->orWhere('venue', 'like', "%{$queryText}%")
                        ->orWhere('host', 'like', "%{$queryText}%");
                });
            })
            ->orderByDesc('is_published')
            ->orderByRaw('starts_at IS NULL')
            ->orderByDesc('starts_at')
            ->latest()
            ->limit($perPage)
            ->get();

        return response()->json([
            'status' => 'ok',
            'data' => [
                'events' => $events->map(fn (ChurchEvent $event): array => $this->eventPayload($event))->values(),
                'options' => $this->optionsPayload(),
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
        $this->attachFileValidation($validator, $request);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $event = ChurchEvent::query()->create($this->attributes($validator->validated(), $request));

        return response()->json([
            'status' => 'ok',
            'message' => 'Church event created.',
            'data' => $this->eventPayload($event->fresh()),
        ], 201);
    }

    public function update(Request $request, ChurchEvent $churchEvent): JsonResponse
    {
        if ($response = $this->authorizeManager($request)) {
            return $response;
        }

        $validator = Validator::make($this->payload($request), $this->rules());
        $this->attachFileValidation($validator, $request);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $churchEvent->forceFill($this->attributes($validator->validated(), $request, $churchEvent))->save();

        return response()->json([
            'status' => 'ok',
            'message' => 'Church event updated.',
            'data' => $this->eventPayload($churchEvent->fresh()),
        ]);
    }

    public function status(Request $request, ChurchEvent $churchEvent): JsonResponse
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

        $churchEvent->forceFill([
            'is_published' => (bool) $validator->validated()['is_published'],
        ])->save();

        return response()->json([
            'status' => 'ok',
            'message' => $churchEvent->is_published ? 'Church event published.' : 'Church event unpublished.',
            'data' => $this->eventPayload($churchEvent->fresh()),
        ]);
    }

    public function destroy(Request $request, ChurchEvent $churchEvent): JsonResponse
    {
        if ($response = $this->authorizeManager($request)) {
            return $response;
        }

        $this->deleteStoredImage($churchEvent->thumbnail);
        $this->deleteStoredImage($churchEvent->portrait_image);
        $churchEvent->delete();

        return response()->json([
            'status' => 'ok',
            'message' => 'Church event deleted.',
        ]);
    }

    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:20000'],
            'venue' => ['nullable', 'string', 'max:255'],
            'theme' => ['nullable', 'string', 'max:255'],
            'bible_verse' => ['nullable', 'string', 'max:255'],
            'host' => ['nullable', 'string', 'max:255'],
            'other_ministers' => ['nullable', 'string', 'max:4000'],
            'registration_url' => ['nullable', 'url', 'max:2048'],
            'registration_availability' => ['nullable', Rule::in(['nigeria', 'outside_nigeria', 'everywhere'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_published' => ['nullable', 'boolean'],
            'is_pilgrimage' => ['nullable', 'boolean'],
            'recurrence_type' => ['nullable', Rule::in(array_keys(ChurchEvent::recurrenceOptions()))],
            'recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:12'],
            'recurrence_weekday' => ['nullable', 'integer', 'min:0', 'max:6'],
            'recurrence_week_of_month' => ['nullable', 'integer', Rule::in(array_keys(ChurchEvent::weekOfMonthOptions()))],
            'recurrence_until' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'remove_thumbnail' => ['nullable', 'boolean'],
            'remove_portrait_image' => ['nullable', 'boolean'],
        ];
    }

    private function attachFileValidation($validator, Request $request): void
    {
        $validator->after(function ($validator) use ($request): void {
            foreach (['thumbnail', 'portrait_image'] as $field) {
                if (! $request->hasFile($field)) {
                    continue;
                }

                $fileValidator = Validator::make($request->only($field), [
                    $field => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                ]);

                if ($fileValidator->fails()) {
                    foreach ($fileValidator->errors()->get($field) as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }
        });
    }

    private function attributes(array $validated, Request $request, ?ChurchEvent $event = null): array
    {
        $recurrenceType = $validated['recurrence_type'] ?? ChurchEvent::RECURRENCE_NONE;
        $startsAt = $validated['starts_at'] ?? null;
        $weekday = array_key_exists('recurrence_weekday', $validated)
            ? $validated['recurrence_weekday']
            : $this->weekdayFromDate($startsAt);

        $attributes = [
            'title' => str($validated['title'])->squish()->toString(),
            'details' => isset($validated['details']) ? trim((string) $validated['details']) : null,
            'venue' => isset($validated['venue']) ? str($validated['venue'])->squish()->toString() : null,
            'theme' => isset($validated['theme']) ? str($validated['theme'])->squish()->toString() : null,
            'bible_verse' => isset($validated['bible_verse']) ? str($validated['bible_verse'])->squish()->toString() : null,
            'host' => isset($validated['host']) ? str($validated['host'])->squish()->toString() : null,
            'other_ministers' => isset($validated['other_ministers']) ? trim((string) $validated['other_ministers']) : null,
            'registration_url' => $validated['registration_url'] ?? null,
            'registration_availability' => $validated['registration_availability'] ?? 'everywhere',
            'starts_at' => $startsAt,
            'ends_at' => $validated['ends_at'] ?? null,
            'is_published' => (bool) ($validated['is_published'] ?? false),
            'is_pilgrimage' => (bool) ($validated['is_pilgrimage'] ?? false),
            'recurrence_type' => $recurrenceType,
            'recurrence_interval' => (int) ($validated['recurrence_interval'] ?? 1),
            'recurrence_weekday' => $recurrenceType === ChurchEvent::RECURRENCE_NONE ? null : $weekday,
            'recurrence_week_of_month' => $recurrenceType === ChurchEvent::RECURRENCE_MONTHLY_NTH_WEEKDAY
                ? (int) ($validated['recurrence_week_of_month'] ?? 1)
                : null,
            'recurrence_until' => $recurrenceType === ChurchEvent::RECURRENCE_NONE ? null : ($validated['recurrence_until'] ?? null),
        ];

        if ($request->hasFile('thumbnail')) {
            $this->deleteStoredImage($event?->thumbnail);
            $attributes['thumbnail'] = $request->file('thumbnail')->store('events', 'public');
        } elseif ((bool) ($validated['remove_thumbnail'] ?? false)) {
            $this->deleteStoredImage($event?->thumbnail);
            $attributes['thumbnail'] = null;
        }

        if ($request->hasFile('portrait_image')) {
            $this->deleteStoredImage($event?->portrait_image);
            $attributes['portrait_image'] = $request->file('portrait_image')->store('events/portrait', 'public');
        } elseif ((bool) ($validated['remove_portrait_image'] ?? false)) {
            $this->deleteStoredImage($event?->portrait_image);
            $attributes['portrait_image'] = null;
        }

        return $attributes;
    }

    private function weekdayFromDate(?string $date): ?int
    {
        if (blank($date)) {
            return 0;
        }

        return (int) date('w', strtotime($date));
    }

    private function deleteStoredImage(?string $path): void
    {
        if (blank($path) || str($path)->startsWith(['http://', 'https://', '//', '/uploads/', 'uploads/'])) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function eventPayload(ChurchEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'details' => $event->details ?? '',
            'venue' => $event->venue ?? '',
            'theme' => $event->theme ?? '',
            'bible_verse' => $event->bible_verse ?? '',
            'host' => $event->host ?? '',
            'other_ministers' => $event->other_ministers ?? '',
            'thumbnail' => $event->thumbnail,
            'thumbnail_url' => MediaUrl::resolve($event->thumbnail),
            'portrait_image' => $event->portrait_image,
            'portrait_image_url' => MediaUrl::resolve($event->portrait_image),
            'registration_url' => $event->registration_url ?? '',
            'registration_availability' => $event->registration_availability ?? 'everywhere',
            'starts_at' => $event->starts_at?->toDateTimeString(),
            'ends_at' => $event->ends_at?->toDateTimeString(),
            'is_published' => (bool) $event->is_published,
            'is_pilgrimage' => (bool) $event->is_pilgrimage,
            'recurrence_type' => $event->recurrence_type ?? ChurchEvent::RECURRENCE_NONE,
            'recurrence_interval' => (int) ($event->recurrence_interval ?? 1),
            'recurrence_weekday' => $event->recurrence_weekday,
            'recurrence_week_of_month' => $event->recurrence_week_of_month,
            'recurrence_until' => $event->recurrence_until?->toDateString(),
            'recurrence_label' => $event->recurrenceLabel(),
            'created_at' => $event->created_at?->toIso8601String(),
            'updated_at' => $event->updated_at?->toIso8601String(),
        ];
    }

    private function optionsPayload(): array
    {
        return [
            'registration_availability' => [
                'everywhere' => 'Everyone',
                'nigeria' => 'Nigeria only',
                'outside_nigeria' => 'Outside Nigeria only',
            ],
            'recurrence' => ChurchEvent::recurrenceOptions(),
            'weekdays' => ChurchEvent::weekdayOptions(),
            'week_of_month' => ChurchEvent::weekOfMonthOptions(),
        ];
    }

    private function authorizeManager(Request $request): ?JsonResponse
    {
        $user = $this->mobileUserFromRequest($request);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in before managing church events.',
            ], 401);
        }

        if (! $this->canManageChurchEvents($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is not authorized to manage church events.',
            ], 403);
        }

        return null;
    }

    private function canManageChurchEvents(MobileUser $user): bool
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

        if ($user->can('manage_church_events')
            || $user->can('manage_church_event')
            || $user->can('manage_events')
            || $user->can('manage_event')
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
                    'eventmanager',
                    'eventsmanager',
                    'church eventmanager',
                    'churcheventmanager',
                    'contentmanager',
                    'goshenmanager',
                    'retreatmanager',
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
