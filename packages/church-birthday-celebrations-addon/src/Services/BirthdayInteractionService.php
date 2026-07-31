<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Services;

use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayCelebration;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayGreeting;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayPreference;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayReaction;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayReport;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdaySetting;
use ChurchTools\ChurchBirthdayCelebrations\Support\BirthdayApiError;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BirthdayInteractionService
{
    public function __construct(private readonly BirthdayGreetingGuard $guard) {}

    public function react(BirthdayCelebration $celebration, Model $member, ?string $reaction): void
    {
        $this->assertInteractive($celebration);
        if ($reaction === null || $reaction === '') { BirthdayReaction::query()->where('celebration_id', $celebration->id)->where('mobile_user_id', $member->id)->delete(); return; }
        if (! in_array($reaction, config('church-birthday-celebrations.reactions', []), true)) {
            BirthdayApiError::abort('INVALID_CONTENT', 'Invalid reaction.', 422);
        }
        BirthdayReaction::query()->updateOrCreate(['celebration_id' => $celebration->id, 'mobile_user_id' => $member->id], ['reaction' => $reaction]);
    }

    public function greet(BirthdayCelebration $celebration, Model $member, string $body, ?string $idempotencyKey = null): BirthdayGreeting
    {
        $this->assertInteractive($celebration);
        if (! BirthdayPreference::query()->firstOrCreate(['mobile_user_id' => $celebration->mobile_user_id])->greetings_enabled) {
            BirthdayApiError::abort('GREETINGS_DISABLED', 'Greetings are disabled for this celebration.', 409);
        }
        $inspection = $this->guard->inspect($body, (int) config('church-birthday-celebrations.greeting_max_length'));

        return DB::transaction(function () use ($celebration, $member, $inspection, $idempotencyKey): BirthdayGreeting {
            if ($idempotencyKey && $found = BirthdayGreeting::query()->where('celebration_id', $celebration->id)->where('idempotency_key', $idempotencyKey)->first()) return $found;
            return BirthdayGreeting::query()->updateOrCreate(
                ['celebration_id' => $celebration->id, 'mobile_user_id' => $member->id],
                ['body' => $inspection['body'], 'idempotency_key' => $idempotencyKey, 'status' => $inspection['status'], 'hidden_at' => null],
            );
        });
    }

    public function thank(BirthdayCelebration $celebration, Model $member, string $body): void
    {
        $this->assertInteractive($celebration);
        if ((int) $celebration->mobile_user_id !== (int) $member->id) {
            BirthdayApiError::abort('FORBIDDEN', 'Only the celebrant can post this thank-you.', 403);
        }
        $inspection = $this->guard->inspect($body, (int) config('church-birthday-celebrations.greeting_max_length'));
        if ($inspection['status'] !== 'visible') {
            BirthdayApiError::abort('INVALID_CONTENT', 'The thank-you message contains unsupported content.', 422);
        }
        $celebration->forceFill(['thank_you' => $inspection['body'], 'thank_you_at' => now()])->save();
    }

    public function report(BirthdayGreeting $greeting, Model $member, string $reason): void
    {
        $reason = trim(strip_tags($reason));
        if ($reason === '' || mb_strlen($reason) > 500) {
            BirthdayApiError::abort('INVALID_CONTENT', 'Invalid report.', 422);
        }
        $report = BirthdayReport::query()->firstOrCreate(
            ['greeting_id' => $greeting->id, 'reporter_mobile_user_id' => $member->id],
            ['reason' => $reason],
        );
        if (! $report->wasRecentlyCreated) {
            BirthdayApiError::abort('REPORT_ALREADY_SUBMITTED', 'You have already reported this greeting.', 409);
        }
        $updates = ['reported_at' => now()];
        if ($greeting->reports()->count() >= max(1, (int) BirthdaySetting::value('report_threshold', config('church-birthday-celebrations.report_threshold', 3)))) {
            $updates += ['status' => 'hidden', 'hidden_at' => now()];
        }
        $greeting->forceFill($updates)->save();
    }

    public function assertInteractive(BirthdayCelebration $celebration): void
    {
        if (! $celebration->isInteractive()) {
            BirthdayApiError::abort('CELEBRATION_CLOSED', 'The celebration is closed.', 409);
        }
    }
}
