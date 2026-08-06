<x-filament-widgets::widget>
    <div class="goshen-dashboard-summary">
        @foreach ($this->getCards() as $card)
            <article class="goshen-dashboard-summary-card">
                <div class="goshen-dashboard-summary-content">
                    <div class="goshen-dashboard-summary-icon" style="--goshen-dashboard-accent: {{ $card['accent'] }};">
                        <x-filament::icon :icon="$card['icon']" />
                    </div>
                    <div>
                        <div class="goshen-dashboard-summary-label">{{ $card['label'] }}</div>
                        <div class="goshen-dashboard-summary-copy">{{ $card['description'] }}</div>
                    </div>
                </div>
                <div class="goshen-dashboard-summary-metric" style="--goshen-dashboard-accent: {{ $card['accent'] }};">
                    <div>{{ $card['value'] }}</div>
                    <span>items</span>
                </div>
            </article>
        @endforeach
    </div>
</x-filament-widgets::widget>
