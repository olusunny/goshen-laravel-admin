<?php

namespace Sunny\Fundraising\Http\Controllers\Api;

use App\Services\StripePaymentSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Sunny\Fundraising\Contracts\PermissionResolverContract;
use Sunny\Fundraising\Models\Campaign;
use Sunny\Fundraising\Models\CampaignContribution;
use Sunny\Fundraising\Services\CampaignService;
use Sunny\Fundraising\Services\ContributionService;
use Throwable;
use UnexpectedValueException;
use Illuminate\Validation\ValidationException;

class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignService $campaigns,
        private readonly ContributionService $contributions,
        private readonly PermissionResolverContract $permissions,
    ) {}

    public function active(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            ...$this->campaigns->payload($this->campaigns->activeCampaign()),
        ]);
    }

    public function show(string $campaign): JsonResponse
    {
        $record = $this->findCampaign($campaign);

        if (! $record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Fundraising campaign not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'ok',
            ...$this->campaigns->payload($record),
        ]);
    }

    public function managementSummary(Request $request): JsonResponse
    {
        $data = $this->payload($request);
        $user = $this->userFromRequest($request, $data);

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in before viewing fundraising management.',
            ], 401);
        }

        if (! $this->canManageFundraising($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is not authorized to manage fundraising.',
            ], 403);
        }

        $campaigns = Campaign::query()
            ->withCount(['media', 'contributions'])
            ->latest()
            ->get();
        $contributions = CampaignContribution::query()
            ->with('campaign')
            ->latest()
            ->get();
        $succeeded = $contributions
            ->filter(fn (CampaignContribution $contribution): bool => $contribution->isSucceeded())
            ->values();
        $activeCampaigns = $campaigns
            ->where('status', Campaign::STATUS_ACTIVE)
            ->values();

        return response()->json([
            'status' => 'ok',
            'data' => [
                'totals' => [
                    'campaigns' => $campaigns->count(),
                    'active_campaigns' => $activeCampaigns->count(),
                    'draft_campaigns' => $campaigns->where('status', Campaign::STATUS_DRAFT)->count(),
                    'paused_campaigns' => $campaigns->where('status', Campaign::STATUS_PAUSED)->count(),
                    'closed_campaigns' => $campaigns->filter(fn (Campaign $campaign): bool => in_array($campaign->status, [
                        Campaign::STATUS_CLOSED,
                        Campaign::STATUS_COMPLETED,
                        Campaign::STATUS_CANCELLED,
                    ], true))->count(),
                    'goal_amount' => round((float) $activeCampaigns->sum(fn (Campaign $campaign): float => (float) $campaign->goal_amount), 2),
                    'raised_amount' => round((float) $activeCampaigns->sum(fn (Campaign $campaign): float => (float) $campaign->raised_amount), 2),
                    'all_time_raised_amount' => round((float) $succeeded->sum(fn (CampaignContribution $contribution): float => (float) $contribution->amount), 2),
                    'pending_amount' => round((float) $contributions
                        ->where('status', CampaignContribution::STATUS_PENDING)
                        ->sum(fn (CampaignContribution $contribution): float => (float) $contribution->amount), 2),
                    'contributions' => $contributions->count(),
                    'succeeded_contributions' => $succeeded->count(),
                    'pending_contributions' => $contributions->where('status', CampaignContribution::STATUS_PENDING)->count(),
                    'failed_contributions' => $contributions->where('status', CampaignContribution::STATUS_FAILED)->count(),
                    'wallet_amount' => round((float) $succeeded
                        ->filter(fn (CampaignContribution $contribution): bool => $this->contributionProvider($contribution) === 'wallet')
                        ->sum(fn (CampaignContribution $contribution): float => (float) $contribution->amount), 2),
                    'stripe_amount' => round((float) $succeeded
                        ->filter(fn (CampaignContribution $contribution): bool => $this->contributionProvider($contribution) === 'stripe')
                        ->sum(fn (CampaignContribution $contribution): float => (float) $contribution->amount), 2),
                    'currency' => $activeCampaigns->first()?->currency
                        ?? $campaigns->first()?->currency
                        ?? 'GBP',
                ],
                'breakdowns' => [
                    'campaign_status' => $this->breakdownRows(
                        $campaigns,
                        fn (Campaign $campaign): string => (string) $campaign->status,
                        fn (string $status): string => Campaign::statusOptions()[$status] ?? $this->humanLabel($status),
                    ),
                    'contribution_status' => $this->breakdownRows(
                        $contributions,
                        fn (CampaignContribution $contribution): string => (string) $contribution->status,
                        fn (string $status): string => $this->humanLabel($status),
                        fn (CampaignContribution $contribution): float => (float) $contribution->amount,
                    ),
                    'payment_provider' => $this->breakdownRows(
                        $succeeded,
                        fn (CampaignContribution $contribution): string => $this->contributionProvider($contribution),
                        fn (string $provider): string => $this->paymentProviderLabel($provider),
                        fn (CampaignContribution $contribution): float => (float) $contribution->amount,
                    ),
                    'campaign_progress' => $this->breakdownRows(
                        $campaigns,
                        fn (Campaign $campaign): string => $this->campaignProgressBucket($campaign),
                        fn (string $bucket): string => $this->progressBucketLabel($bucket),
                    ),
                ],
                'campaigns' => $campaigns
                    ->take(100)
                    ->map(fn (Campaign $campaign): array => $this->managementCampaignRow($campaign))
                    ->values(),
                'recent_contributions' => $contributions
                    ->take(100)
                    ->map(fn (CampaignContribution $contribution): array => [
                        'id' => $contribution->id,
                        'campaign_id' => $contribution->campaign_id,
                        'campaign_title' => $contribution->campaign?->title ?: 'Fundraising campaign',
                        'amount' => (float) $contribution->amount,
                        'currency' => $contribution->currency,
                        'status' => $contribution->status,
                        'status_label' => $this->humanLabel((string) $contribution->status),
                        'payment_provider' => $this->contributionProvider($contribution),
                        'payment_provider_label' => $this->paymentProviderLabel($this->contributionProvider($contribution)),
                        'display_name' => $contribution->is_anonymous
                            ? 'Anonymous supporter'
                            : ($contribution->display_name ?: 'Supporter'),
                        'anonymous' => (bool) $contribution->is_anonymous,
                        'succeeded_at' => $contribution->succeeded_at?->toIso8601String(),
                        'created_at' => $contribution->created_at?->toIso8601String(),
                    ])
                    ->values(),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function updateManagementStatus(Request $request, string $campaign): JsonResponse
    {
        $record = $this->findCampaign($campaign);
        if (! $record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Fundraising campaign not found.',
            ], 404);
        }

        $data = $this->payload($request);
        $user = $this->userFromRequest($request, $data);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in before managing fundraising campaigns.',
            ], 401);
        }

        if (! $this->canManageFundraising($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is not authorized to manage fundraising.',
            ], 403);
        }

        $validator = validator($data, [
            'status' => ['required', 'string', 'in:active,paused,closed'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            match ($validated['status']) {
                Campaign::STATUS_ACTIVE => $record->publishNow(),
                Campaign::STATUS_PAUSED => $record->forceFill(['status' => Campaign::STATUS_PAUSED])->save(),
                Campaign::STATUS_CLOSED => $record->forceFill(['status' => Campaign::STATUS_CLOSED])->save(),
            };
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => collect($exception->errors())->flatten()->first()
                    ?: 'Unable to update this campaign status.',
                'errors' => $exception->errors(),
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to update this campaign status right now.',
            ], 500);
        }

        $record->refresh()->loadCount(['media', 'contributions']);

        return response()->json([
            'status' => 'ok',
            'message' => 'Fundraising campaign status has been updated.',
            'campaign' => $this->managementCampaignRow($record),
        ]);
    }

    public function contribute(Request $request, string $campaign): JsonResponse
    {
        $record = $this->findCampaign($campaign);
        if (! $record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Fundraising campaign not found.',
            ], 404);
        }

        $data = $this->payload($request);
        $validated = validator($data, [
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'message' => ['nullable', 'string', 'max:500'],
            'anonymous' => ['nullable', 'boolean'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'api_token' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $user = $this->userFromRequest($request, $validated);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in before contributing from your wallet.',
            ], 401);
        }

        try {
            $result = $this->contributions->contribute(
                $record,
                $user,
                (float) $validated['amount'],
                $validated['message'] ?? null,
                filter_var($validated['anonymous'] ?? false, FILTER_VALIDATE_BOOLEAN),
                (string) $validated['idempotency_key'],
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to record this contribution right now.',
            ], 500);
        }

        $contribution = $result['contribution'];

        return response()->json([
            'status' => 'ok',
            'message' => 'Thank you. Your fundraising contribution has been recorded.',
            'contribution' => [
                'id' => $contribution->id,
                'amount' => (float) $contribution->amount,
                'currency' => $contribution->currency,
                'status' => $contribution->status,
                'succeeded_at' => $contribution->succeeded_at?->toIso8601String(),
            ],
            'campaign' => $this->campaigns->payload($result['campaign'], false)['campaign'],
            'wallet' => $result['wallet'],
            'idempotent_replay' => (bool) ($result['idempotent_replay'] ?? false),
        ]);
    }

    public function checkout(Request $request, string $campaign): JsonResponse
    {
        $record = $this->findCampaign($campaign);
        if (! $record) {
            return response()->json([
                'status' => 'error',
                'message' => 'Fundraising campaign not found.',
            ], 404);
        }

        $data = $this->payload($request);
        $validated = validator($data, [
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'message' => ['nullable', 'string', 'max:500'],
            'anonymous' => ['nullable', 'boolean'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'api_token' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $user = $this->userFromRequest($request, $validated);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in before starting secure fundraising checkout.',
            ], 401);
        }

        try {
            $result = $this->contributions->createStripeCheckout(
                $record,
                $user,
                (float) $validated['amount'],
                $validated['message'] ?? null,
                filter_var($validated['anonymous'] ?? false, FILTER_VALIDATE_BOOLEAN),
                (string) $validated['idempotency_key'],
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to start fundraising checkout right now.',
            ], 500);
        }

        $contribution = $result['contribution'];

        return response()->json([
            'status' => 'ok',
            'message' => 'Secure fundraising checkout is ready.',
            'contribution' => [
                'id' => $contribution->id,
                'amount' => (float) $contribution->amount,
                'currency' => $contribution->currency,
                'status' => $contribution->status,
                'payment_provider' => $contribution->payment_provider,
                'provider_reference' => $contribution->provider_reference,
            ],
            'checkout' => $result['checkout'],
            'campaign' => $this->campaigns->payload($result['campaign'], false)['campaign'],
            'idempotent_replay' => (bool) ($result['idempotent_replay'] ?? false),
        ]);
    }

    public function stripeWebhook(Request $request, StripePaymentSettings $settings): JsonResponse
    {
        $settings->applyToConfig();
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature');
        $secret = $settings->givingWebhookSecret();

        if ($secret === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Fundraising Stripe webhook is not configured.',
            ], 503);
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret, 300);
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid Stripe webhook.',
            ], 400);
        }

        $this->contributions->settleStripeWebhook($event->toArray());

        return response()->json(['status' => 'ok']);
    }

    private function managementCampaignRow(Campaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'slug' => $campaign->slug,
            'title' => $campaign->title,
            'cause' => $campaign->cause,
            'status' => $campaign->status,
            'status_label' => Campaign::statusOptions()[$campaign->status] ?? $this->humanLabel((string) $campaign->status),
            'available_actions' => $this->managementCampaignActions($campaign),
            'currency' => $campaign->currency,
            'goal_amount' => (float) $campaign->goal_amount,
            'raised_amount' => (float) $campaign->raised_amount,
            'progress_percentage' => $campaign->progressPercentage(),
            'donor_count' => (int) $campaign->donor_count,
            'contributions_count' => (int) ($campaign->contributions_count ?? 0),
            'media_count' => (int) ($campaign->media_count ?? 0),
            'can_contribute' => $campaign->canContribute(),
            'start_at' => $campaign->start_at?->toIso8601String(),
            'end_at' => $campaign->end_at?->toIso8601String(),
        ];
    }

    private function managementCampaignActions(Campaign $campaign): array
    {
        if ($campaign->status === Campaign::STATUS_ACTIVE) {
            return ['paused', 'closed'];
        }

        if ($campaign->isClosed()) {
            return ['active'];
        }

        return ['active', 'closed'];
    }

    private function findCampaign(string $campaign): ?Campaign
    {
        return Campaign::query()
            ->with('media')
            ->where(function ($query) use ($campaign): void {
                $query->where('slug', $campaign);

                if (ctype_digit($campaign)) {
                    $query->orWhere('id', (int) $campaign);
                }
            })
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $payload = $request->input('data', $request->all());

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($payload) ? $payload : [];
    }

    private function userFromRequest(Request $request, array $data): mixed
    {
        $token = $data['api_token'] ?? $request->bearerToken();
        if (! is_string($token) || $token === '') {
            return null;
        }

        $model = config('fundraising.models.user');
        if (! is_string($model) || ! class_exists($model) || ! method_exists($model, 'query')) {
            return null;
        }

        return $model::query()
            ->where('api_token_hash', hash('sha256', $token))
            ->first();
    }

    private function canManageFundraising(mixed $user): bool
    {
        if (method_exists($user, 'canUseCommunity') && ! $user->canUseCommunity()) {
            return false;
        }

        if ($this->permissions->canManage($user)) {
            return true;
        }

        if (! method_exists($user, 'roles')) {
            return false;
        }

        return $user->roles()
            ->pluck('name')
            ->contains(fn ($role): bool => in_array(
                str($role)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                ['superadmin', 'fundraisingmanager', 'eventmanager', 'goshenmanager', 'retreatmanager'],
                true,
            ));
    }

    private function contributionProvider(CampaignContribution $contribution): string
    {
        $provider = strtolower(trim((string) ($contribution->payment_provider
            ?: data_get($contribution->metadata, 'payment_provider')
            ?: data_get($contribution->metadata, 'payment_gateway')
            ?: 'unknown')));

        return $provider !== '' ? $provider : 'unknown';
    }

    private function breakdownRows($items, callable $keyResolver, callable $labelResolver, ?callable $amountResolver = null): array
    {
        $items = collect($items)->values();
        $total = max(1, $items->count());

        return $items
            ->groupBy(fn ($item): string => (string) $keyResolver($item))
            ->map(function ($group, string $key) use ($labelResolver, $amountResolver, $total): array {
                $row = [
                    'key' => $key,
                    'label' => (string) $labelResolver($key),
                    'count' => $group->count(),
                    'percentage' => round(($group->count() / $total) * 100, 1),
                ];

                if ($amountResolver !== null) {
                    $row['amount'] = round((float) $group->sum(fn ($item): float => (float) $amountResolver($item)), 2);
                }

                return $row;
            })
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    private function campaignProgressBucket(Campaign $campaign): string
    {
        $progress = $campaign->progressPercentage();

        if ($progress >= 100) {
            return 'complete';
        }

        if ($progress >= 75) {
            return 'nearly_there';
        }

        if ($progress >= 50) {
            return 'halfway';
        }

        if ($progress > 0) {
            return 'started';
        }

        return 'not_started';
    }

    private function progressBucketLabel(string $bucket): string
    {
        return match ($bucket) {
            'complete' => 'Goal reached',
            'nearly_there' => '75% or more',
            'halfway' => '50% or more',
            'started' => 'Started',
            default => 'Not started',
        };
    }

    private function paymentProviderLabel(string $provider): string
    {
        return match ($provider) {
            'wallet' => 'Goshen wallet',
            'stripe' => 'Card checkout',
            default => $this->humanLabel($provider),
        };
    }

    private function humanLabel(string $value): string
    {
        $clean = trim(str_replace('_', ' ', $value));

        return $clean === '' ? 'Unknown' : str($clean)->title()->toString();
    }
}
