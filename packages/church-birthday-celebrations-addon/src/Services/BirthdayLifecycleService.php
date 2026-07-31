<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Services;

use App\Models\InboxMessage;
use Carbon\CarbonImmutable;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayCelebration;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayDelivery;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayPreference;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdaySetting;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayTemplate;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class BirthdayLifecycleService
{
    public function __construct(
        private readonly AddonAvailability $availability,
        private readonly BirthdayEligibilityService $eligibility,
        private readonly BirthdayCardService $cards,
        private readonly BirthdayNotifier $notifier,
    ) {}

    public function run(): void
    {
        if (! $this->availability->isActive()) {
            return;
        }

        $now = CarbonImmutable::now($this->timezone());
        $this->revokeIneligibleCelebrations();
        $previewDays = max(1, (int) BirthdaySetting::value('preview_days', config('church-birthday-celebrations.preview_days')));
        for ($days = 1; $days <= $previewDays; $days++) {
            $this->prepare($now->addDays($days));
        }
        $this->publish($now);
        $this->close($now);
        $this->purgeDue($now);
        $this->notifier->retryFailed();
        BirthdaySetting::put('last_lifecycle_run_at', $now->toIso8601String());
    }

    public function purgeAll(): void
    {
        BirthdayCelebration::query()->whereNull('purged_at')->get()
            ->each(fn (BirthdayCelebration $celebration) => $this->purge($celebration));
    }

    public function reconcileMember(Model $member, bool $presentationChanged = false): void
    {
        $preference = BirthdayPreference::query()->firstOrCreate(['mobile_user_id' => $member->id]);
        foreach (BirthdayCelebration::query()->where('mobile_user_id', $member->id)->where('status', '!=', BirthdayCelebration::PURGED)->get() as $celebration) {
            if (! $this->eligibility->eligible($member, $preference) || ! $this->eligibility->occursOn($member, CarbonImmutable::parse($celebration->birthday_date, $this->timezone()))) {
                $this->purge($celebration);
                continue;
            }

            if ($presentationChanged) {
                $this->regenerateCard($celebration, $member, $preference);
            }
        }
    }

    public function purgeMember(Model $member): void
    {
        BirthdayDelivery::query()
            ->where('mobile_user_id', $member->getKey())
            ->get()
            ->each(fn (BirthdayDelivery $delivery) => $this->purgeDelivery($delivery));

        BirthdayCelebration::query()
            ->where('mobile_user_id', $member->getKey())
            ->get()
            ->each(fn (BirthdayCelebration $celebration) => $this->purge($celebration));
    }

    public function reconcileCelebration(BirthdayCelebration $celebration): bool
    {
        if ($celebration->status === BirthdayCelebration::PURGED) {
            return false;
        }

        $member = $celebration->member;
        $preference = $member ? BirthdayPreference::query()->firstOrCreate(['mobile_user_id' => $member->id]) : null;
        if (! $member || ! $this->eligibility->eligible($member, $preference)
            || ! $this->eligibility->occursOn($member, CarbonImmutable::parse($celebration->birthday_date, $this->timezone()))) {
            $this->purge($celebration);

            return false;
        }

        $birthdayDate = CarbonImmutable::parse($celebration->birthday_date, $this->timezone());
        if ($celebration->status === BirthdayCelebration::PUBLISHED
            && $birthdayDate->isSameDay(CarbonImmutable::now($this->timezone()))) {
            $closesAt = $this->closesAt($birthdayDate);
            if (! $celebration->closes_at?->equalTo($closesAt)) {
                $celebration->forceFill([
                    'closes_at' => $closesAt,
                    'purge_due_at' => $closesAt->addDays((int) BirthdaySetting::value('retention_days', config('church-birthday-celebrations.retention_days'))),
                ])->save();
            }
        }

        return true;
    }

    public function purge(BirthdayCelebration $celebration): void
    {
        $alreadyPurged = $celebration->status === BirthdayCelebration::PURGED;

        $this->cards->delete($celebration);
        BirthdayDelivery::query()
            ->where(function ($deliveries) use ($celebration): void {
                $deliveries->where('celebration_id', $celebration->id)
                    ->orWhereHas('celebrations', fn ($linked) => $linked->where('birthday_celebrations.id', $celebration->id));
            })
            ->get()
            ->filter(fn (BirthdayDelivery $delivery): bool => $delivery->kind === 'digest'
                || $delivery->celebration_id === $celebration->id
                || ! $delivery->celebrations()->where('birthday_celebrations.id', '!=', $celebration->id)->whereNull('purged_at')->exists())
            ->each(fn (BirthdayDelivery $delivery) => $this->purgeDelivery($delivery));
        $celebration->greetings()->delete();
        $celebration->reactions()->delete();
        if ($alreadyPurged) {
            return;
        }
        $celebration->forceFill([
            'status' => BirthdayCelebration::PURGED,
            'purged_at' => now(),
            'card_disk' => null,
            'card_path' => null,
            'card_mime' => null,
            'thank_you' => null,
            'thank_you_at' => null,
            'verse' => null,
            'metadata' => null,
        ])->save();
    }

    private function prepare(CarbonImmutable $date): void
    {
        foreach ($this->membersOccurringOn($date) as $member) {
            $preference = BirthdayPreference::query()->firstOrCreate(['mobile_user_id' => $member->id]);
            if (! $this->eligibility->eligible($member, $preference)) {
                continue;
            }
            $celebration = BirthdayCelebration::query()->firstOrCreate(
                ['mobile_user_id' => $member->id, 'birthday_year' => $date->year],
                ['birthday_date' => $date->toDateString(), 'status' => BirthdayCelebration::PREVIEW_READY, 'display_name' => $this->displayName($member, $preference)],
            );
            if ($celebration->status === BirthdayCelebration::PURGED) {
                continue;
            }
            $this->ensureCard($celebration, $member, $preference);
            if (! $celebration->previewed_at) {
                $celebration->forceFill(['previewed_at' => now()])->save();
            }
            $this->notifier->send(
                $member,
                $celebration,
                'preview',
                'preview:'.$celebration->id,
                'Your birthday card preview is ready',
                'Review your Birthday Celebration preferences before your birthday.',
            );
        }
    }

    private function publish(CarbonImmutable $date): void
    {
        $published = [];
        foreach ($this->membersOccurringOn($date) as $member) {
            $preference = BirthdayPreference::query()->firstOrCreate(['mobile_user_id' => $member->id]);
            if (! $this->eligibility->eligible($member, $preference)) {
                continue;
            }
            $celebration = BirthdayCelebration::query()->firstOrCreate(
                ['mobile_user_id' => $member->id, 'birthday_year' => $date->year],
                ['birthday_date' => $date->toDateString(), 'status' => BirthdayCelebration::PREVIEW_READY, 'display_name' => $this->displayName($member, $preference)],
            );
            if ($celebration->status === BirthdayCelebration::PURGED) {
                continue;
            }
            $this->ensureCard($celebration, $member, $preference);
            if ($celebration->status === BirthdayCelebration::PREVIEW_READY) {
                $publishedAt = $date->startOfDay()->utc();
                $closesAt = $this->closesAt($date);
                $celebration->forceFill([
                    'status' => BirthdayCelebration::PUBLISHED,
                    'published_at' => $publishedAt,
                    'closes_at' => $closesAt,
                    'purge_due_at' => $closesAt->addDays((int) BirthdaySetting::value('retention_days', config('church-birthday-celebrations.retention_days'))),
                ])->save();
            }
            if ($celebration->status === BirthdayCelebration::PUBLISHED) {
                $published[] = $celebration;
                $this->notifier->send(
                    $member,
                    $celebration,
                    'celebrant',
                    'celebrant:'.$celebration->id,
                    'Happy Birthday from your church family',
                    'Your private birthday celebration is now open for 24 hours.',
                );
            }
        }
        if ($published !== []) {
            $this->digest($date, $published);
        }
    }

    private function digest(CarbonImmutable $date, array $celebrations): void
    {
        $names = collect($celebrations)->pluck('display_name')->implode(', ');
        $this->eligibility->members()->each(function (Model $member) use ($date, $names, $celebrations): void {
            $preference = BirthdayPreference::query()->firstOrCreate(['mobile_user_id' => $member->id]);
            if ($this->eligibility->eligible($member, $preference)) {
                $this->notifier->send(
                    $member,
                    null,
                    'digest',
                    'digest:'.$date->toDateString().':'.$member->id,
                    "Today's birthdays",
                    "Celebrate {$names} with your church family today.",
                    $celebrations,
                );
            }
        });
    }

    private function membersOccurringOn(CarbonImmutable $date): iterable
    {
        foreach ($this->eligibility->members()->cursor() as $member) {
            if ($this->eligibility->occursOn($member, $date)) {
                yield $member;
            }
        }
    }

    private function revokeIneligibleCelebrations(): void
    {
        BirthdayCelebration::query()->where('status', '!=', BirthdayCelebration::PURGED)->with('member')->cursor()
            ->each(fn (BirthdayCelebration $celebration) => $this->reconcileCelebration($celebration));
    }

    private function ensureCard(BirthdayCelebration $celebration, Model $member, BirthdayPreference $preference): void
    {
        if ($celebration->card_path) {
            return;
        }

        try {
            $this->cards->generate($celebration, $member, $preference, $this->template($preference));
        } catch (Throwable $exception) {
            $metadata = is_array($celebration->metadata) ? $celebration->metadata : [];
            $metadata['card_generation_failed_at'] = now()->toIso8601String();
            $metadata['card_generation_error'] = class_basename($exception);
            $celebration->forceFill(['metadata' => $metadata])->save();
            report($exception);
        }
    }

    private function regenerateCard(BirthdayCelebration $celebration, Model $member, BirthdayPreference $preference): void
    {
        $this->cards->delete($celebration);
        $celebration->forceFill(['card_path' => null, 'card_mime' => null, 'metadata' => null])->save();
        $this->ensureCard($celebration, $member, $preference);
    }

    private function close(CarbonImmutable $now): void
    {
        BirthdayCelebration::query()->where('status', BirthdayCelebration::PUBLISHED)->where('closes_at', '<=', $now->utc())
            ->update(['status' => BirthdayCelebration::CLOSED, 'updated_at' => now()]);
    }

    private function purgeDue(CarbonImmutable $now): void
    {
        BirthdayCelebration::query()->where('status', BirthdayCelebration::CLOSED)->where('purge_due_at', '<=', $now->utc())->get()
            ->each(fn (BirthdayCelebration $celebration) => $this->purge($celebration));
    }

    private function purgeDelivery(BirthdayDelivery $delivery): void
    {
        if (class_exists(InboxMessage::class) && $delivery->inbox_message_id) {
            InboxMessage::query()->whereKey($delivery->inbox_message_id)->delete();
        }
        $delivery->celebrations()->detach();
        $delivery->delete();
    }

    private function template(BirthdayPreference $preference): ?BirthdayTemplate
    {
        $preferred = $preference->preferredTemplate;

        return $preferred?->is_active ? $preferred : BirthdayTemplate::selected();
    }

    private function displayName(Model $member, BirthdayPreference $preference): string
    {
        return trim((string) ($preference->preferred_name ?: $member->name ?: 'Church Member'));
    }

    private function timezone(): string
    {
        return (string) BirthdaySetting::value('timezone', config('church-birthday-celebrations.timezone', 'Africa/Lagos'));
    }

    private function closesAt(CarbonImmutable $date): CarbonImmutable
    {
        return $date->setTimezone($this->timezone())->endOfDay()->setMicrosecond(0)->utc();
    }
}
