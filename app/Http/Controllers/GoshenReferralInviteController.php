<?php

namespace App\Http\Controllers;

use App\Models\GoshenReferralCode;
use App\Support\MediaUrl;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Personal\EventInstallments\Models\Event;

class GoshenReferralInviteController extends Controller
{
    public function __invoke(string $code): View
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?: '');
        $referral = GoshenReferralCode::query()->where('code', $code)->firstOrFail();
        $event = $this->publishedGoshenEvent();

        return view('member.referral-invite', [
            'code' => $referral->code,
            'event' => $event,
            'eventDate' => $this->eventDate($event),
            'venue' => $this->eventVenue($event),
            'featureImageUrl' => $this->featureImageUrl($event),
        ]);
    }

    private function publishedGoshenEvent(): ?Event
    {
        return Event::query()
            ->with('schedules')
            ->where('status', 'published')
            ->where(function ($query): void {
                $query
                    ->where('settings->module', 'goshen_retreat')
                    ->orWhere('settings->module', 'goshen-retreat')
                    ->orWhere('settings->app_module', 'goshen_retreat')
                    ->orWhere('slug', 'like', 'goshen-retreat%')
                    ->orWhere('slug', 'like', 'goshen-%')
                    ->orWhere('name', 'like', '%Goshen Retreat%');
            })
            ->orderBy('sales_start_at')
            ->orderBy('name')
            ->first();
    }

    private function eventDate(?Event $event): string
    {
        if (! $event) {
            return 'Dates will be shared by the church.';
        }

        $start = $event->start_date ?: $event->schedules->min('starts_at');
        $end = $event->end_date ?: $event->schedules->max('ends_at');
        if (! $start instanceof CarbonInterface) {
            return 'Dates will be shared by the church.';
        }

        return $end instanceof CarbonInterface && ! $start->isSameDay($end)
            ? $start->format('j M Y').' - '.$end->format('j M Y')
            : $start->format('j M Y');
    }

    private function eventVenue(?Event $event): string
    {
        return collect([$event?->venue_name, $event?->venue_address])
            ->filter()
            ->implode(' - ') ?: 'Venue details will be shared by the church.';
    }

    private function featureImageUrl(?Event $event): ?string
    {
        $settings = is_array($event?->settings) ? $event->settings : [];
        $featureBanner = is_array($settings['feature_banner'] ?? null) ? $settings['feature_banner'] : [];
        $path = $featureBanner['image_path']
            ?? $settings['feature_image_path']
            ?? $settings['feature_banner_image_path']
            ?? $settings['banner_image_path']
            ?? null;
        if (is_array($path)) {
            $path = $path['path'] ?? $path['image_path'] ?? reset($path);
        }

        return MediaUrl::resolve(is_string($path) ? $path : null);
    }
}
