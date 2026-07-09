<?php

namespace App\Services;

use App\Models\GoshenQuiz;
use App\Models\InboxMessage;
use App\Models\MobileUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Personal\EventInstallments\Models\Event;
use Sunny\Fundraising\Models\Campaign;

class MessageRecipientResolver
{
    public const GOSHEN_RECIPIENT_MODES = [
        'goshen_paid',
        'goshen_unpaid',
        'goshen_paid_between',
        'goshen_paid_recent_days',
        'goshen_paid_week',
        'goshen_paid_month',
    ];

    public static function isGoshenMode(?string $mode): bool
    {
        return in_array((string) $mode, self::GOSHEN_RECIPIENT_MODES, true);
    }

    public static function paymentFilterForMode(?string $mode): ?string
    {
        return match ((string) $mode) {
            'goshen_paid' => 'paid',
            'goshen_unpaid' => 'unpaid',
            'goshen_paid_between' => 'paid_between',
            'goshen_paid_recent_days' => 'paid_recent_days',
            'goshen_paid_week' => 'paid_week',
            'goshen_paid_month' => 'paid_month',
            default => null,
        };
    }

    public function usersFor(object $message, bool $respectNotificationCategory = false): Collection
    {
        $users = $this->queryFor($message)
            ->with('churchGroup')
            ->get();

        if (! $respectNotificationCategory) {
            return $users->values();
        }

        $category = (string) ($message->notification_category ?? 'general');

        return $users
            ->filter(fn (MobileUser $user): bool => $user->wantsNotificationCategory($category))
            ->values();
    }

    public function queryFor(object $message): Builder
    {
        $users = MobileUser::query()
            ->where('is_blocked', false)
            ->where('is_deleted', false);

        $mode = (string) ($message->recipient_mode ?? 'all');

        if ($mode === 'selected') {
            $users->whereIn('id', $message->selected_mobile_user_ids ?? []);
        } elseif ($mode === 'groups') {
            $users->whereIn('group_id', $message->selected_church_group_ids ?? []);
        } elseif ($mode === 'countries') {
            $users->whereIn('country_of_residence', $message->selected_country_of_residences ?? []);
        } elseif ($mode === 'states') {
            if (! empty($message->selected_country_of_residences)) {
                $users->whereIn('country_of_residence', $message->selected_country_of_residences);
            }

            $users->whereIn('state_county_province', $message->selected_states_counties_provinces ?? []);
        } elseif ($mode === 'genders') {
            $users->whereIn('gender', $message->selected_genders ?? []);
        } elseif ($mode === 'roles') {
            $users->whereHas('roles', fn ($roles) => $roles->whereIn('roles.id', $message->selected_role_ids ?? []));
        } elseif ($mode === 'fundraising_participants') {
            $this->applyFundraisingAudience($users, $message);
        } elseif ($mode === 'quiz_participants') {
            $this->applyQuizAudience($users, $message);
        } elseif (self::isGoshenMode($mode)) {
            $this->applyGoshenAudience($users, $message);
        }

        return $users;
    }

    public function snapshotInboxRecipients(InboxMessage $message): array
    {
        if (! $this->shouldSnapshot($message)) {
            return [];
        }

        $ids = $this->usersFor($message)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $message->forceFill([
            'delivered_mobile_user_ids' => $ids,
        ])->saveQuietly();

        return $ids;
    }

    public function goshenEventOptions(): array
    {
        return $this->goshenEventsQuery()
            ->orderByDesc('sales_start_at')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'public_id', 'name', 'status'])
            ->mapWithKeys(function (Event $event): array {
                $status = $event->status ? " ({$event->status})" : '';

                return [$event->id => "{$event->name}{$status}"];
            })
            ->all();
    }

    public function fundraisingCampaignOptions(): array
    {
        return Campaign::query()
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'title', 'status'])
            ->mapWithKeys(function (Campaign $campaign): array {
                $status = $campaign->status ? " ({$campaign->status})" : '';

                return [$campaign->id => "{$campaign->title}{$status}"];
            })
            ->all();
    }

    public function quizOptions(): array
    {
        return GoshenQuiz::query()
            ->orderByDesc('opens_at')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'title', 'is_active'])
            ->mapWithKeys(function (GoshenQuiz $quiz): array {
                $status = $quiz->is_active ? ' (active)' : ' (inactive)';

                return [$quiz->id => "{$quiz->title}{$status}"];
            })
            ->all();
    }

    public function goshenEventsQuery(): Builder
    {
        return Event::query()->where(function ($query): void {
            $query
                ->where('settings->module', 'goshen_retreat')
                ->orWhere('settings->module', 'goshen-retreat')
                ->orWhere('settings->app_module', 'goshen_retreat')
                ->orWhere('slug', 'like', 'goshen-retreat%')
                ->orWhere('slug', 'like', 'goshen-%')
                ->orWhere('name', 'like', '%Goshen Retreat%');
        });
    }

    private function shouldSnapshot(InboxMessage $message): bool
    {
        $mode = (string) ($message->recipient_mode ?? 'all');

        return filled($mode) && ! in_array($mode, ['all', ''], true);
    }

    private function applyGoshenAudience(Builder $users, object $message): void
    {
        $eventId = (int) ($message->goshen_event_id ?? 0);
        if ($eventId <= 0) {
            $users->whereRaw('1 = 0');

            return;
        }

        $filter = (string) (($message->goshen_payment_filter ?? null) ?: self::paymentFilterForMode($message->recipient_mode ?? null));

        $users->whereExists(function ($booking) use ($eventId, $filter, $message): void {
            $booking
                ->selectRaw('1')
                ->from('ei_bookings')
                ->whereNull('ei_bookings.deleted_at')
                ->where('ei_bookings.event_id', $eventId)
                ->where(function ($recipient): void {
                    $recipient
                        ->whereColumn('ei_bookings.customer_id', 'mobile_users.id')
                        ->orWhere(function ($email): void {
                            $email
                                ->whereNotNull('mobile_users.email')
                                ->whereColumn('ei_bookings.customer_email', 'mobile_users.email');
                        });
                });

            if ($filter === 'paid') {
                $this->applyPaidBookingFilter($booking);
            } elseif ($filter === 'unpaid') {
                $this->applyUnpaidBookingFilter($booking);
            } elseif (str_starts_with($filter, 'paid_')) {
                [$from, $until] = $this->paidWindow($message, $filter);

                if (! $from && ! $until) {
                    $booking->whereRaw('1 = 0');

                    return;
                }

                $this->applyPaidWindowFilter($booking, $from, $until);
            }
        });
    }

    private function applyFundraisingAudience(Builder $users, object $message): void
    {
        $campaignId = (int) ($message->fundraising_campaign_id ?? 0);
        if ($campaignId <= 0) {
            $users->whereRaw('1 = 0');

            return;
        }

        $users->whereExists(function ($contributions) use ($campaignId): void {
            $contributions
                ->selectRaw('1')
                ->from('fundraising_campaign_contributions')
                ->where('fundraising_campaign_contributions.campaign_id', $campaignId)
                ->where(function ($succeeded): void {
                    $succeeded
                        ->where('fundraising_campaign_contributions.status', 'succeeded')
                        ->orWhereNotNull('fundraising_campaign_contributions.succeeded_at');
                })
                ->where(function ($recipient): void {
                    $recipient
                        ->whereColumn('fundraising_campaign_contributions.user_id', 'mobile_users.id')
                        ->where('fundraising_campaign_contributions.user_type', MobileUser::class);
                });
        });
    }

    private function applyQuizAudience(Builder $users, object $message): void
    {
        $quizId = (int) ($message->goshen_quiz_id ?? 0);
        if ($quizId <= 0) {
            $users->whereRaw('1 = 0');

            return;
        }

        $users->whereExists(function ($attempts) use ($quizId): void {
            $attempts
                ->selectRaw('1')
                ->from('goshen_quiz_attempts')
                ->where('goshen_quiz_attempts.quiz_id', $quizId)
                ->whereColumn('goshen_quiz_attempts.mobile_user_id', 'mobile_users.id');
        });
    }

    private function applyPaidBookingFilter($booking): void
    {
        $booking
            ->whereNotIn('ei_bookings.status', ['cancelled', 'refunded'])
            ->where(function ($paid): void {
                $paid
                    ->where('ei_bookings.status', 'paid')
                    ->orWhereRaw('(COALESCE(ei_bookings.total, 0) <= 0 OR COALESCE(ei_bookings.paid_total, 0) + 0.01 >= COALESCE(ei_bookings.total, 0))');
            });
    }

    private function applyUnpaidBookingFilter($booking): void
    {
        $booking
            ->whereNotIn('ei_bookings.status', ['cancelled', 'refunded', 'paid'])
            ->whereRaw('COALESCE(ei_bookings.total, 0) > 0')
            ->whereRaw('COALESCE(ei_bookings.paid_total, 0) + 0.01 < COALESCE(ei_bookings.total, 0)');
    }

    private function applyPaidWindowFilter($booking, ?Carbon $from, ?Carbon $until): void
    {
        $booking
            ->whereNotIn('ei_bookings.status', ['cancelled', 'refunded'])
            ->where(function ($payments) use ($from, $until): void {
                $payments
                    ->whereExists(function ($transactions) use ($from, $until): void {
                        $transactions
                            ->selectRaw('1')
                            ->from('ei_payment_transactions')
                            ->whereColumn('ei_payment_transactions.booking_id', 'ei_bookings.id')
                            ->whereIn(DB::raw('LOWER(ei_payment_transactions.status)'), ['paid', 'succeeded', 'completed'])
                            ->whereNotNull('ei_payment_transactions.paid_at');

                        $this->applyDateWindow($transactions, 'ei_payment_transactions.paid_at', $from, $until);
                    })
                    ->orWhereExists(function ($installments) use ($from, $until): void {
                        $installments
                            ->selectRaw('1')
                            ->from('ei_payment_installments')
                            ->whereColumn('ei_payment_installments.booking_id', 'ei_bookings.id')
                            ->where('ei_payment_installments.status', 'paid')
                            ->whereNotNull('ei_payment_installments.paid_at');

                        $this->applyDateWindow($installments, 'ei_payment_installments.paid_at', $from, $until);
                    });
            });
    }

    private function applyDateWindow($query, string $column, ?Carbon $from, ?Carbon $until): void
    {
        if ($from) {
            $query->where($column, '>=', $from);
        }

        if ($until) {
            $query->where($column, '<=', $until);
        }
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function paidWindow(object $message, string $filter): array
    {
        if ($filter === 'paid_between') {
            return [
                $this->carbonOrNull($message->goshen_paid_from ?? null),
                $this->carbonOrNull($message->goshen_paid_until ?? null),
            ];
        }

        if ($filter === 'paid_recent_days') {
            $days = max(1, (int) ($message->goshen_recent_days ?? 0));

            return [now()->subDays($days)->startOfDay(), now()->endOfDay()];
        }

        if ($filter === 'paid_week') {
            $week = $this->carbonOrNull($message->goshen_paid_week ?? null);
            if (! $week) {
                return [null, null];
            }

            return [$week->copy()->startOfWeek(), $week->copy()->endOfWeek()];
        }

        if ($filter === 'paid_month') {
            $month = trim((string) ($message->goshen_paid_month ?? ''));
            if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
                return [null, null];
            }

            $start = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfMonth();

            return [$start, $start->copy()->endOfMonth()];
        }

        return [null, null];
    }

    private function carbonOrNull(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
