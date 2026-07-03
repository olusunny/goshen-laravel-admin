<x-filament-widgets::widget>
    <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; width: 100%;">
        @foreach ($this->getCards() as $card)
            <div style="position: relative; overflow: hidden; min-height: 150px; border-radius: 22px; padding: 24px; background: linear-gradient(135deg, #0c2230, #123b4d); color: #fff; border: 1px solid rgba(255,255,255,.08); box-shadow: 0 18px 36px rgba(12, 34, 48, .22);">
                <div style="position: absolute; right: -34px; top: -42px; width: 156px; height: 156px; border-radius: 999px; background: {{ $card['accent'] }}; opacity: .16;"></div>
                <div style="position: absolute; right: 22px; bottom: -48px; width: 132px; height: 132px; border-radius: 28px; transform: rotate(18deg); background: {{ $card['accent'] }}; opacity: .10;"></div>

                <div style="position: relative; display: flex; align-items: center; justify-content: space-between; gap: 18px;">
                    <div style="display: flex; align-items: center; gap: 16px; min-width: 0;">
                        <div style="width: 64px; height: 64px; flex: 0 0 64px; display: flex; align-items: center; justify-content: center; border-radius: 20px; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.10);">
                            <x-filament::icon :icon="$card['icon']" style="width: 34px; height: 34px; color: {{ $card['accent'] }};" />
                        </div>
                        <div style="min-width: 0;">
                            <div style="font-size: 22px; line-height: 1.1; font-weight: 900;">Total {{ $card['label'] }}</div>
                            <div style="margin-top: 7px; max-width: 320px; font-size: 12px; line-height: 1.35; color: rgba(255,255,255,.68);">{{ $card['description'] }}</div>
                        </div>
                    </div>

                    <div style="min-width: 86px; text-align: center; border-radius: 18px; padding: 14px 16px; background: {{ $card['accent'] }}; color: #0c2230; box-shadow: 0 16px 28px rgba(0,0,0,.16);">
                        <div style="font-size: 34px; line-height: 1; font-weight: 900;">{{ $card['value'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <style>
        @media (max-width: 900px) {
            .fi-widget div[style*="grid-template-columns: repeat(2"] {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</x-filament-widgets::widget>
