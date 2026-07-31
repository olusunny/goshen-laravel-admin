<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Http\Controllers\Api;

use Carbon\CarbonImmutable;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayCelebration;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayCorrectionRequest;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayGreeting;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayPreference;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayReaction;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdaySetting;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayTemplate;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayVerse;
use ChurchTools\ChurchBirthdayCelebrations\Services\BirthdayCardService;
use ChurchTools\ChurchBirthdayCelebrations\Services\BirthdayEligibilityService;
use ChurchTools\ChurchBirthdayCelebrations\Services\BirthdayInteractionService;
use ChurchTools\ChurchBirthdayCelebrations\Services\BirthdayLifecycleService;
use ChurchTools\ChurchBirthdayCelebrations\Support\BirthdayApiError;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BirthdayCelebrationController
{
    public function context(Request $request, BirthdayEligibilityService $eligibility): JsonResponse
    {
        $member = $request->user();
        $preference = BirthdayPreference::query()->firstOrCreate(['mobile_user_id' => $member->id]);

        return $this->ok($this->contextData($member, $preference, $eligibility));
    }

    public function updatePreferences(
        Request $request,
        BirthdayEligibilityService $eligibility,
        BirthdayLifecycleService $lifecycle,
    ): JsonResponse {
        $member = $request->user();
        $data = $request->validate([
            'visibility_enabled' => ['nullable', 'boolean'],
            'greetings_enabled' => ['nullable', 'boolean'],
            'use_profile_photo' => ['nullable', 'boolean'],
            'preferred_name' => ['nullable', 'string', 'max:120'],
            'template_id' => ['nullable', 'integer', Rule::exists('birthday_celebration_templates', 'id')->where('is_active', true)],
            'verse_id' => ['nullable', 'integer', Rule::exists('birthday_celebration_verses', 'id')->where('is_active', true)],
        ]);
        $preference = BirthdayPreference::query()->firstOrCreate(['mobile_user_id' => $member->id]);
        $cardFields = [
            'use_profile_photo' => $data['use_profile_photo'] ?? $preference->use_profile_photo,
            'preferred_name' => $data['preferred_name'] ?? $preference->preferred_name,
            'preferred_template_id' => array_key_exists('template_id', $data) ? $data['template_id'] : $preference->preferred_template_id,
            'preferred_verse_id' => array_key_exists('verse_id', $data) ? $data['verse_id'] : $preference->preferred_verse_id,
        ];
        $presentationChanged = collect($cardFields)->contains(
            fn ($value, $field): bool => (string) $preference->{$field} !== (string) $value,
        );
        $preference->fill(collect($data)->except(['template_id', 'verse_id'])->all() + [
            'preferred_template_id' => $cardFields['preferred_template_id'],
            'preferred_verse_id' => $cardFields['preferred_verse_id'],
        ])->save();
        $lifecycle->reconcileMember($member, $presentationChanged);

        return $this->ok($this->contextData($member, $preference->fresh(), $eligibility));
    }

    public function requestCorrection(Request $request, BirthdayEligibilityService $eligibility): JsonResponse
    {
        $member = $request->user();
        if (! $eligibility->baseEligible($member)) {
            BirthdayApiError::abort('MEMBER_INELIGIBLE', 'Only verified church members can request a birthday correction.', 403);
        }
        $data = $request->validate([
            'birthday_month' => ['required', 'integer', 'between:1,12'],
            'birthday_day' => ['required', 'integer', 'between:1,31'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        if (! checkdate((int) $data['birthday_month'], (int) $data['birthday_day'], 2000)) {
            BirthdayApiError::abort('INVALID_CONTENT', 'Enter a valid birthday month and day.', 422);
        }

        $correction = BirthdayCorrectionRequest::query()->updateOrCreate(
            ['mobile_user_id' => $member->id, 'status' => 'pending'],
            ['birthday_month' => $data['birthday_month'], 'birthday_day' => $data['birthday_day'], 'reason' => trim((string) ($data['reason'] ?? '')) ?: null],
        );

        return $this->ok(['correction_request' => ['id' => $correction->id, 'status' => $correction->status]]);
    }

    public function hub(
        Request $request,
        BirthdayEligibilityService $eligibility,
        BirthdayLifecycleService $lifecycle,
    ): JsonResponse {
        $this->assertEligible($request->user(), $eligibility);
        $timezone = (string) BirthdaySetting::value('timezone', config('church-birthday-celebrations.timezone'));
        $today = now($timezone)->toDateString();
        $upcoming = now($timezone)->addDays((int) BirthdaySetting::value('upcoming_days', config('church-birthday-celebrations.upcoming_days')))->toDateString();

        return $this->ok([
            'today' => BirthdayCelebration::query()->with('member')->where('status', BirthdayCelebration::PUBLISHED)->whereDate('birthday_date', $today)->get()
                ->filter(fn (BirthdayCelebration $celebration) => $lifecycle->reconcileCelebration($celebration))
                ->map(fn (BirthdayCelebration $celebration) => $this->summary($celebration))->values(),
            'upcoming' => BirthdayCelebration::query()->with('member')->where('status', BirthdayCelebration::PREVIEW_READY)->whereBetween('birthday_date', [$today, $upcoming])->orderBy('birthday_date')->get()
                ->filter(fn (BirthdayCelebration $celebration) => $lifecycle->reconcileCelebration($celebration))
                ->map(fn (BirthdayCelebration $celebration) => $this->summary($celebration))->values(),
        ]);
    }

    public function show(
        Request $request,
        string $publicId,
        BirthdayEligibilityService $eligibility,
        BirthdayLifecycleService $lifecycle,
    ): JsonResponse {
        $member = $request->user();
        $this->assertEligibleOrRecoveryStaff($member, $eligibility);
        $celebration = $this->authorizeAccess($publicId, $member, $eligibility, $lifecycle);

        return $this->ok($this->detail($celebration, $member));
    }

    public function card(
        Request $request,
        string $publicId,
        BirthdayEligibilityService $eligibility,
        BirthdayLifecycleService $lifecycle,
        BirthdayCardService $cards,
    ) {
        $member = $request->user();
        $this->assertEligibleOrRecoveryStaff($member, $eligibility);
        $celebration = $this->authorizeAccess($publicId, $member, $eligibility, $lifecycle);
        $variant = in_array($request->query('variant'), ['square', 'portrait'], true) ? $request->query('variant') : 'portrait';
        $path = $cards->pathFor($celebration, $variant) ?: ($variant === 'portrait' ? $celebration->card_path : null);
        if (! $path || ! Storage::disk($celebration->card_disk)->exists($path)) {
            BirthdayApiError::abort('MEDIA_UNAVAILABLE', 'This birthday card is unavailable.', 404);
        }

        return response(Storage::disk($celebration->card_disk)->get($path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function react(Request $request, string $publicId, BirthdayEligibilityService $eligibility, BirthdayLifecycleService $lifecycle, BirthdayInteractionService $interactions): JsonResponse
    {
        $member = $request->user();
        $this->assertEligible($member, $eligibility);
        $interactions->react($this->interactive($publicId, $member, $eligibility, $lifecycle), $member, $request->validate(['reaction' => ['nullable', 'string', 'max:30']])['reaction'] ?? null);

        return $this->ok();
    }

    public function greet(Request $request, string $publicId, BirthdayEligibilityService $eligibility, BirthdayLifecycleService $lifecycle, BirthdayInteractionService $interactions): JsonResponse
    {
        $member = $request->user();
        $this->assertEligible($member, $eligibility);
        $data = $request->validate(['body' => ['required', 'string'], 'idempotency_key' => ['nullable', 'string', 'max:120']]);
        $greeting = $interactions->greet($this->interactive($publicId, $member, $eligibility, $lifecycle), $member, $data['body'], $data['idempotency_key'] ?? null);

        return $this->ok(['greeting' => $this->greeting($greeting, $member)]);
    }

    public function deleteGreeting(Request $request, string $publicId, int $greetingId, BirthdayEligibilityService $eligibility, BirthdayLifecycleService $lifecycle, BirthdayInteractionService $interactions): JsonResponse
    {
        $member = $request->user();
        $this->assertEligible($member, $eligibility);
        $celebration = $this->interactive($publicId, $member, $eligibility, $lifecycle);
        $interactions->assertInteractive($celebration);
        $greeting = BirthdayGreeting::query()->where('celebration_id', $celebration->id)->findOrFail($greetingId);
        if ((int) $greeting->mobile_user_id !== (int) $member->id) {
            BirthdayApiError::abort('FORBIDDEN', 'You can only delete your own greeting.', 403);
        }
        $greeting->delete();

        return $this->ok();
    }

    public function thank(Request $request, string $publicId, BirthdayEligibilityService $eligibility, BirthdayLifecycleService $lifecycle, BirthdayInteractionService $interactions): JsonResponse
    {
        $member = $request->user();
        $this->assertEligible($member, $eligibility);
        $interactions->thank($this->interactive($publicId, $member, $eligibility, $lifecycle), $member, $request->validate(['body' => ['required', 'string']])['body']);

        return $this->ok();
    }

    public function report(Request $request, string $publicId, int $greetingId, BirthdayEligibilityService $eligibility, BirthdayLifecycleService $lifecycle, BirthdayInteractionService $interactions): JsonResponse
    {
        $member = $request->user();
        $this->assertEligible($member, $eligibility);
        $celebration = $this->interactive($publicId, $member, $eligibility, $lifecycle);
        $greeting = BirthdayGreeting::query()->where('celebration_id', $celebration->id)->findOrFail($greetingId);
        $interactions->report($greeting, $member, $request->validate(['reason' => ['required', 'string', 'max:500']])['reason']);

        return $this->ok();
    }

    private function interactive(string $publicId, Model $member, BirthdayEligibilityService $eligibility, BirthdayLifecycleService $lifecycle): BirthdayCelebration
    {
        $celebration = $this->authorizeAccess($publicId, $member, $eligibility, $lifecycle);
        if (! $celebration->isInteractive()) {
            BirthdayApiError::abort('CELEBRATION_CLOSED', 'The celebration is closed.', 409);
        }

        return $celebration;
    }

    private function authorizeAccess(string $publicId, Model $member, BirthdayEligibilityService $eligibility, BirthdayLifecycleService $lifecycle): BirthdayCelebration
    {
        $celebration = BirthdayCelebration::query()->with('member')->where('public_id', $publicId)->first();
        if (! $celebration) {
            BirthdayApiError::abort('CELEBRATION_NOT_PUBLISHED', 'The celebration is unavailable.', 404);
        }
        if ($celebration->status === BirthdayCelebration::PURGED) {
            BirthdayApiError::abort('CELEBRATION_PURGED', 'This celebration has been permanently removed.', 410);
        }

        $targetPreference = BirthdayPreference::query()->firstOrCreate(['mobile_user_id' => $celebration->mobile_user_id]);
        $targetReason = $eligibility->reason($celebration->member, $targetPreference);
        if ($targetReason !== BirthdayEligibilityService::ELIGIBLE) {
            $lifecycle->reconcileCelebration($celebration);
            BirthdayApiError::abort(
                $targetReason,
                $targetReason === BirthdayEligibilityService::OPTED_OUT ? 'This member has opted out of birthday celebrations.' : 'This member is no longer eligible for birthday celebrations.',
                403,
            );
        }
        if (! $lifecycle->reconcileCelebration($celebration)) {
            BirthdayApiError::abort('CELEBRATION_PURGED', 'This celebration has been permanently removed.', 410);
        }
        $celebration->refresh();
        $now = CarbonImmutable::now((string) BirthdaySetting::value('timezone', config('church-birthday-celebrations.timezone')));
        if ($celebration->status === BirthdayCelebration::PUBLISHED && $celebration->closes_at?->lte($now)) {
            $celebration->forceFill(['status' => BirthdayCelebration::CLOSED])->save();
        }
        if ($celebration->status === BirthdayCelebration::CLOSED && $celebration->purge_due_at?->lte($now)) {
            $lifecycle->purge($celebration);
            BirthdayApiError::abort('CELEBRATION_PURGED', 'This celebration has been permanently removed.', 410);
        }

        $owner = (int) $celebration->mobile_user_id === (int) $member->id;
        $staff = method_exists($member, 'can') && $member->can(config('church-birthday-celebrations.permissions.recover'));
        if ($celebration->status === BirthdayCelebration::PREVIEW_READY && ! $owner) {
            BirthdayApiError::abort('CELEBRATION_NOT_PUBLISHED', 'The celebration is not published.', 404);
        }
        if ($celebration->status === BirthdayCelebration::CLOSED && ! $owner && ! $staff) {
            BirthdayApiError::abort('CELEBRATION_CLOSED', 'The celebration is closed.', 409);
        }

        return $celebration;
    }

    private function assertEligible(Model $member, BirthdayEligibilityService $eligibility): void
    {
        $preference = BirthdayPreference::query()->firstOrCreate(['mobile_user_id' => $member->id]);
        $reason = $eligibility->reason($member, $preference);
        if ($reason !== BirthdayEligibilityService::ELIGIBLE) {
            BirthdayApiError::abort(
                $reason,
                $reason === BirthdayEligibilityService::OPTED_OUT ? 'You have opted out of birthday celebrations.' : 'Birthday Celebration is only available to verified church members.',
                403,
            );
        }
    }

    private function assertEligibleOrRecoveryStaff(Model $member, BirthdayEligibilityService $eligibility): void
    {
        if (method_exists($member, 'can') && $member->can(config('church-birthday-celebrations.permissions.recover'))) {
            return;
        }

        $this->assertEligible($member, $eligibility);
    }

    private function contextData(Model $member, BirthdayPreference $preference, BirthdayEligibilityService $eligibility): array
    {
        $reason = $eligibility->reason($member, $preference);

        return [
            'capability' => config('church-birthday-celebrations.capability'),
            'eligible' => $reason === BirthdayEligibilityService::ELIGIBLE,
            'eligibility_code' => $reason,
            'preferences' => $this->preferences($preference),
            'templates' => BirthdayTemplate::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('sort_order')->orderByDesc('version')
                ->get(['id', 'name', 'version', 'is_default'])->map->only(['id', 'name', 'version', 'is_default'])->values(),
            'verses' => BirthdayVerse::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')
                ->get(['id', 'reference', 'body'])->map->only(['id', 'reference', 'body'])->values(),
        ];
    }

    private function preferences(BirthdayPreference $preference): array
    {
        return [
            'visibility_enabled' => $preference->visibility_enabled,
            'greetings_enabled' => $preference->greetings_enabled,
            'use_profile_photo' => $preference->use_profile_photo,
            'preferred_name' => $preference->preferred_name,
            'template_id' => $preference->preferred_template_id,
            'verse_id' => $preference->preferred_verse_id,
        ];
    }

    private function summary(BirthdayCelebration $celebration): array
    {
        return [
            'id' => $celebration->public_id,
            'name' => $celebration->display_name,
            'birthday_month_day' => $celebration->birthday_date?->format('m-d'),
            'status' => $celebration->status,
        ];
    }

    private function detail(BirthdayCelebration $celebration, Model $member): array
    {
        $myGreeting = BirthdayGreeting::query()->where('celebration_id', $celebration->id)->where('mobile_user_id', $member->id)->first();

        return [
            ...$this->summary($celebration),
            'is_celebrant' => (int) $celebration->mobile_user_id === (int) $member->id,
            'is_interactive' => $celebration->isInteractive(),
            'closes_at' => $celebration->closes_at?->toIso8601String(),
            'thank_you' => $celebration->thank_you,
            'my_greeting_id' => $myGreeting?->id,
            'reactions' => BirthdayReaction::query()->where('celebration_id', $celebration->id)->selectRaw('reaction, count(*) as count')->groupBy('reaction')->pluck('count', 'reaction'),
            'greetings' => BirthdayGreeting::query()->where('celebration_id', $celebration->id)->where('status', 'visible')->get()
                ->map(fn (BirthdayGreeting $greeting) => $this->greeting($greeting, $member))->values(),
        ];
    }

    private function greeting(BirthdayGreeting $greeting, Model $member): array
    {
        return [
            'id' => $greeting->id,
            'body' => $greeting->body,
            'status' => $greeting->status,
            'is_mine' => (int) $greeting->mobile_user_id === (int) $member->id,
            'created_at' => $greeting->created_at?->toIso8601String(),
        ];
    }

    private function ok(array $data = []): JsonResponse
    {
        return response()->json(['status' => 'ok', 'data' => $data]);
    }
}
