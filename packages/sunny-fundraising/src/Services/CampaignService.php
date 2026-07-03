<?php

namespace Sunny\Fundraising\Services;

use App\Models\AppSetting;
use App\Services\StripePaymentSettings;
use Illuminate\Database\Eloquent\Builder;
use Throwable;
use Sunny\Fundraising\Models\Campaign;
use Sunny\Fundraising\Models\CampaignContribution;
use Sunny\Fundraising\Models\CampaignMedia;

class CampaignService
{
    public function activeCampaign(): ?Campaign
    {
        return Campaign::query()
            ->with(['media'])
            ->where('status', Campaign::STATUS_ACTIVE)
            ->where(function (Builder $query): void {
                $query->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('end_at')->orWhere('end_at', '>', now());
            })
            ->orderByDesc('start_at')
            ->first();
    }

    public function payload(?Campaign $campaign, bool $includeRecent = true): array
    {
        if (! $campaign) {
            return [
                'has_active_campaign' => false,
                'campaign' => null,
                'message' => 'No active campaign at the moment.',
            ];
        }

        $campaign->loadMissing(['media']);

        return [
            'has_active_campaign' => true,
            'campaign' => [
                'id' => $campaign->id,
                'slug' => $campaign->slug,
                'title' => $campaign->title,
                'cause' => $campaign->cause,
                'short_description' => $campaign->short_description,
                'description' => $campaign->description,
                'goal_amount' => (float) $campaign->goal_amount,
                'raised_amount' => (float) $campaign->raised_amount,
                'currency' => $campaign->currency,
                'cta_label' => $campaign->ctaLabel(),
                'donor_count' => (int) $campaign->donor_count,
                'progress_percentage' => $campaign->progressPercentage(),
                'start_at' => $campaign->start_at?->toIso8601String(),
                'end_at' => $campaign->end_at?->toIso8601String(),
                'server_time' => now()->toIso8601String(),
                'status' => $campaign->status,
                'auto_stop_when_goal_reached' => (bool) $campaign->auto_stop_when_goal_reached,
                'can_contribute' => $campaign->canContribute(),
                'remaining_seconds' => $campaign->remainingSeconds(),
                'goal_reached' => $campaign->goalReached(),
                'payment_options' => $this->paymentOptions(),
                'media' => $campaign->media->map(fn (CampaignMedia $media): array => $this->mediaPayload($media))->values(),
                'recent_contributions' => $includeRecent && $campaign->show_recent_contributors
                    ? $this->recentContributionPayload($campaign)
                    : [],
            ],
            'message' => 'Active fundraising campaign loaded.',
        ];
    }

    public function refreshTotals(Campaign $campaign): Campaign
    {
        $successful = $campaign->successfulContributions();
        $raised = (float) $successful->sum('amount');
        $donors = (clone $successful->getQuery())
            ->selectRaw("COALESCE(user_type, '') as user_type, COALESCE(user_id, 0) as user_id")
            ->distinct()
            ->count();

        $updates = [
            'raised_amount' => round($raised, 2),
            'donor_count' => $donors,
        ];

        if ($campaign->auto_stop_when_goal_reached && $raised + 0.01 >= (float) $campaign->goal_amount) {
            $updates['status'] = Campaign::STATUS_COMPLETED;
        }

        $campaign->forceFill($updates)->save();

        return $campaign->fresh(['media']);
    }

    private function mediaPayload(CampaignMedia $media): array
    {
        return [
            'id' => $media->id,
            'type' => $media->type,
            'url' => $media->publicUrl(),
            'youtube_video_id' => $media->youtube_video_id,
            'title' => $media->title,
            'caption' => $media->caption,
            'sort_order' => (int) $media->sort_order,
        ];
    }

    private function recentContributionPayload(Campaign $campaign): array
    {
        return $campaign->successfulContributions()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (CampaignContribution $contribution): array => [
                'id' => $contribution->id,
                'amount' => (float) $contribution->amount,
                'currency' => $contribution->currency,
                'display_name' => $contribution->is_anonymous ? 'Anonymous supporter' : ($contribution->display_name ?: 'Supporter'),
                'message' => $contribution->message,
                'created_at' => $contribution->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function paymentOptions(): array
    {
        $walletEnabled = filter_var(config('fundraising.wallet.enabled', true), FILTER_VALIDATE_BOOLEAN);
        if (class_exists(AppSetting::class)) {
            $walletEnabled = $walletEnabled
                && filter_var(AppSetting::value('goshen_wallet_enabled', '1'), FILTER_VALIDATE_BOOLEAN);
        }

        $stripeEnabled = filter_var(config('fundraising.payments.stripe.enabled', true), FILTER_VALIDATE_BOOLEAN);
        $stripeConfigured = false;

        if ($stripeEnabled && class_exists(StripePaymentSettings::class)) {
            try {
                $settings = app(StripePaymentSettings::class);
                $settings->applyToConfig();
                $stripeConfigured = $settings->secretKey() !== ''
                    && $settings->givingWebhookSecret() !== ''
                    && $this->stripeSuccessUrl($settings) !== ''
                    && $this->stripeCancelUrl($settings) !== '';
            } catch (Throwable) {
                $stripeConfigured = false;
            }
        }

        return [
            'wallet_enabled' => $walletEnabled,
            'stripe_enabled' => $stripeEnabled,
            'stripe_configured' => $stripeConfigured,
            'gateways' => array_values(array_filter([
                $walletEnabled ? [
                    'key' => 'wallet',
                    'label' => 'Goshen wallet',
                    'configured' => true,
                ] : null,
                $stripeEnabled ? [
                    'key' => 'stripe',
                    'label' => 'Card checkout',
                    'configured' => $stripeConfigured,
                ] : null,
            ])),
        ];
    }

    private function stripeSuccessUrl(StripePaymentSettings $settings): string
    {
        $configured = trim((string) config('fundraising.payments.stripe.success_url', ''));

        return $configured !== '' ? $configured : $settings->givingSuccessUrl();
    }

    private function stripeCancelUrl(StripePaymentSettings $settings): string
    {
        $configured = trim((string) config('fundraising.payments.stripe.cancel_url', ''));

        return $configured !== '' ? $configured : $settings->givingCancelUrl();
    }
}
