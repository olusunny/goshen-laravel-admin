<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Visitor locations and content consumption
        </x-slot>

        <x-slot name="description">
            Authenticated mobile app traffic from real mobile users in the last 30 days.
        </x-slot>

        @php
            $locations = $this->getLocations();
            $totalVisits = $this->getTotalVisits();
        @endphp

        <div class="goshen-dashboard-grid">
            @forelse ($locations as $location)
                @php
                    $share = $totalVisits > 0 ? min(100, round(($location->visits / $totalVisits) * 100)) : 0;
                    $place = collect([$location->city, $location->region, $location->country])->filter()->join(', ') ?: 'Unknown location';
                @endphp

                <article class="goshen-dashboard-panel">
                    <div class="goshen-dashboard-panel-heading">
                        <div>
                            <h3 class="goshen-dashboard-panel-title">{{ $place }}</h3>
                            <div class="goshen-dashboard-meta">Authenticated app traffic</div>
                        </div>
                        <div class="goshen-dashboard-share">{{ $share }}%</div>
                    </div>

                    <div class="goshen-dashboard-value">{{ number_format($location->visits) }}</div>
                    <div class="goshen-dashboard-note">Visits in the last 30 days</div>

                    <div
                        class="goshen-dashboard-progress"
                        role="progressbar"
                        aria-label="Share of visits from {{ $place }}"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="{{ $share }}"
                    >
                        <span style="width: {{ $share }}%;"></span>
                    </div>

                    <div class="goshen-dashboard-row-heading goshen-dashboard-row-summary">
                        <span class="goshen-dashboard-meta">Content consumption</span>
                        <strong class="goshen-dashboard-row-value">{{ number_format($location->consumptions) }}</strong>
                    </div>
                </article>
            @empty
                <div class="goshen-dashboard-empty">
                    <x-filament::icon icon="heroicon-o-map-pin" />
                    <strong>No authenticated app traffic yet</strong>
                    <span>Location insights will appear after members use signed-in mobile features.</span>
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
