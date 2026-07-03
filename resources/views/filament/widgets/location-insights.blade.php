<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Visitor locations and content consumption
        </x-slot>

        <x-slot name="description">
            Authenticated Flutter app traffic from real mobile users in the last 30 days.
        </x-slot>

        <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px;">
            @forelse ($this->getLocations() as $location)
                @php
                    $share = $this->getTotalVisits() > 0 ? min(100, round(($location->visits / $this->getTotalVisits()) * 100)) : 0;
                    $place = collect([$location->city, $location->region, $location->country])->filter()->join(', ') ?: 'Unknown';
                    $accent = match (true) {
                        $loop->iteration % 3 === 1 => '#f97316',
                        $loop->iteration % 3 === 2 => '#0ea5e9',
                        default => '#10b981',
                    };
                @endphp

                <div style="position: relative; overflow: hidden; min-height: 190px; border-radius: 16px; background: #fff; border: 1px solid rgba(148, 163, 184, .22); box-shadow: 0 10px 22px rgba(15, 23, 42, .08); padding: 24px 28px;">
                    <div style="position: absolute; right: -36px; top: -44px; width: 132px; height: 132px; border-radius: 999px; background: {{ $accent }}; opacity: .10;"></div>

                    <div style="position: relative; display: flex; align-items: flex-start; justify-content: space-between; gap: 18px;">
                        <div style="min-width: 0;">
                            <div style="font-size: 15px; color: #6b7280; font-weight: 700; line-height: 1.3;">{{ $place }}</div>
                            <div style="margin-top: 18px; font-size: 34px; line-height: 1; color: #050507; font-weight: 900;">{{ number_format($location->visits) }}</div>
                            <div style="margin-top: 9px; font-size: 14px; line-height: 1.35; color: {{ $accent }}; font-weight: 600;">visits in 30 days</div>
                        </div>

                        <div style="width: 62px; height: 62px; flex: 0 0 62px; border-radius: 18px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: color-mix(in srgb, {{ $accent }} 12%, white); color: {{ $accent }};">
                            <div style="font-size: 22px; font-weight: 900; line-height: 1;">{{ $share }}%</div>
                            <div style="font-size: 10px; font-weight: 800; opacity: .78;">share</div>
                        </div>
                    </div>

                    <div style="position: relative; margin-top: 20px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                            <div style="font-size: 13px; color: #6b7280; font-weight: 600;">Content consumption</div>
                            <div style="font-size: 15px; color: #050507; font-weight: 800;">{{ number_format($location->consumptions) }}</div>
                        </div>
                        <div style="margin-top: 12px; height: 5px; overflow: hidden; border-radius: 999px; background: #eef2f7;">
                            <div style="height: 100%; width: {{ $share }}%; border-radius: 999px; background: {{ $accent }};"></div>
                        </div>
                    </div>
                </div>
            @empty
                <div style="grid-column: 1 / -1; min-height: 150px; border-radius: 16px; background: #fff; border: 1px dashed rgba(148, 163, 184, .5); box-shadow: 0 10px 22px rgba(15, 23, 42, .05); padding: 28px; color: #6b7280;">
                    No authenticated Flutter app user traffic has been recorded yet. Once real mobile users open the app and make API requests, this dashboard card will populate automatically.
                </div>
            @endforelse
        </div>

        <style>
            @media (prefers-color-scheme: dark) {
                .fi-widget div[style*="background: #fff"] {
                    background: #18181b !important;
                    border-color: rgba(255,255,255,.10) !important;
                }

                .fi-widget div[style*="color: #050507"] {
                    color: #fff !important;
                }
            }

            @media (max-width: 1100px) {
                .fi-widget div[style*="grid-template-columns: repeat(3"] {
                    grid-template-columns: 1fr !important;
                }
            }
        </style>
    </x-filament::section>
</x-filament-widgets::widget>
