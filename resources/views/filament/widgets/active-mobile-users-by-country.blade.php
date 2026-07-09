<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Active mobile users by country
        </x-slot>

        <x-slot name="description">
            Users seen in the app within the last 30 minutes, grouped by country of residence with gender stats.
        </x-slot>

        <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-bottom: 22px;">
            <div style="border-radius: 18px; padding: 22px; background: linear-gradient(135deg, #0c2230, #16455a); color: #fff; box-shadow: 0 18px 36px rgba(12, 34, 48, .22);">
                <div style="font-size: 13px; opacity: .72; font-weight: 700;">Currently active</div>
                <div style="margin-top: 8px; font-size: 38px; line-height: 1; font-weight: 800;">{{ number_format($this->getActiveTotal()) }}</div>
                <div style="margin-top: 8px; font-size: 12px; color: #f8c857;">Seen in the last 30 minutes</div>
            </div>

            <div style="border-radius: 18px; padding: 22px; background: linear-gradient(135deg, #f59e0b, #ffca57); color: #0c2230; box-shadow: 0 18px 36px rgba(245, 158, 11, .18);">
                <div style="font-size: 13px; opacity: .72; font-weight: 800;">Registered users</div>
                <div style="margin-top: 8px; font-size: 38px; line-height: 1; font-weight: 900;">{{ number_format($this->getRegisteredTotal()) }}</div>
                <div style="margin-top: 8px; font-size: 12px; opacity: .78; font-weight: 700;">Non-deleted mobile accounts</div>
            </div>

            <div style="border-radius: 18px; padding: 22px; background: linear-gradient(135deg, #10b981, #0f766e); color: #fff; box-shadow: 0 18px 36px rgba(16, 185, 129, .18);">
                <div style="font-size: 13px; opacity: .78; font-weight: 700;">Country coverage</div>
                <div style="margin-top: 8px; font-size: 38px; line-height: 1; font-weight: 900;">{{ number_format($this->getCountries()->count()) }}</div>
                <div style="margin-top: 8px; font-size: 12px; color: #d1fae5;">Countries represented in profiles</div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;">
            @forelse ($this->getCountries() as $country)
                @php
                    $total = max(1, $country['total_users']);
                    $activeShare = min(100, round(($country['active_users'] / $total) * 100));
                @endphp

                <div style="border-radius: 20px; overflow: hidden; background: #111827; color: #fff; border: 1px solid rgba(255,255,255,.08); box-shadow: 0 18px 34px rgba(0,0,0,.18);">
                    <div style="display: flex; gap: 14px; align-items: center; padding: 18px 18px 12px;">
                        <div style="width: 58px; height: 58px; display: flex; align-items: center; justify-content: center; border-radius: 18px; background: rgba(255,255,255,.08); font-size: 30px;">
                            {{ $country['flag'] }}
                        </div>
                        <div style="min-width: 0; flex: 1;">
                            <div style="font-size: 18px; line-height: 1.2; font-weight: 900;">{{ $country['country'] }}</div>
                            <div style="margin-top: 4px; font-size: 12px; color: rgba(255,255,255,.68);">
                                {{ number_format($country['active_users']) }} active · {{ number_format($country['total_users']) }} registered
                            </div>
                        </div>
                        <div style="min-width: 66px; text-align: center; border-radius: 14px; padding: 9px 10px; background: #ffb522; color: #0c2230; font-weight: 900;">
                            {{ $activeShare }}%
                        </div>
                    </div>

                    <div style="padding: 0 18px 18px;">
                        <div style="height: 8px; overflow: hidden; border-radius: 999px; background: rgba(255,255,255,.1);">
                            <div style="height: 100%; width: {{ $activeShare }}%; border-radius: 999px; background: #ffb522;"></div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-top: 14px;">
                            <div style="border-radius: 14px; padding: 12px; background: rgba(59,130,246,.14);">
                                <div style="font-size: 11px; color: #93c5fd; font-weight: 800;">Male</div>
                                <div style="margin-top: 4px; font-size: 22px; font-weight: 900;">{{ number_format($country['male']) }}</div>
                            </div>
                            <div style="border-radius: 14px; padding: 12px; background: rgba(236,72,153,.14);">
                                <div style="font-size: 11px; color: #f9a8d4; font-weight: 800;">Female</div>
                                <div style="margin-top: 4px; font-size: 22px; font-weight: 900;">{{ number_format($country['female']) }}</div>
                            </div>
                            <div style="border-radius: 14px; padding: 12px; background: rgba(255,255,255,.08);">
                                <div style="font-size: 11px; color: rgba(255,255,255,.62); font-weight: 800;">Other</div>
                                <div style="margin-top: 4px; font-size: 22px; font-weight: 900;">{{ number_format($country['other']) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div style="grid-column: 1 / -1; border-radius: 18px; border: 1px dashed rgba(148,163,184,.5); padding: 24px; color: #94a3b8;">
                    No mobile user country data is available yet. Once users complete their country of residence, this dashboard card will populate automatically.
                </div>
            @endforelse
        </div>

        <style>
            @media (max-width: 1100px) {
                .fi-widget div[style*="grid-template-columns: repeat(3"] {
                    grid-template-columns: 1fr !important;
                }

                .fi-widget div[style*="grid-template-columns: repeat(2"] {
                    grid-template-columns: 1fr !important;
                }
            }
        </style>
    </x-filament::section>
</x-filament-widgets::widget>
