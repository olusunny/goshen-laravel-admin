<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Active mobile users by country
        </x-slot>

        <x-slot name="description">
            Users seen in the app within the last 30 minutes, grouped by country of residence.
        </x-slot>

        @php
            $countries = $this->getCountries();
            $activeTotal = $this->getActiveTotal();
            $registeredTotal = $this->getRegisteredTotal();
        @endphp

        <div class="goshen-dashboard-grid">
            <article class="goshen-dashboard-metric goshen-dashboard-metric--primary">
                <div class="goshen-dashboard-label">Currently active</div>
                <div class="goshen-dashboard-value">{{ number_format($activeTotal) }}</div>
                <div class="goshen-dashboard-note">Seen in the app within the last 30 minutes</div>
            </article>

            <article class="goshen-dashboard-metric">
                <div class="goshen-dashboard-label">Registered users</div>
                <div class="goshen-dashboard-value">{{ number_format($registeredTotal) }}</div>
                <div class="goshen-dashboard-note">Active, non-deleted mobile accounts</div>
            </article>

            <article class="goshen-dashboard-metric">
                <div class="goshen-dashboard-label">Country coverage</div>
                <div class="goshen-dashboard-value">{{ number_format($countries->count()) }}</div>
                <div class="goshen-dashboard-note">Countries represented in member profiles</div>
            </article>
        </div>

        <div class="goshen-dashboard-grid goshen-dashboard-grid--2">
            @forelse ($countries as $country)
                @php
                    $total = max(1, $country['total_users']);
                    $activeShare = min(100, round(($country['active_users'] / $total) * 100));
                @endphp

                <article class="goshen-dashboard-panel">
                    <div class="goshen-dashboard-panel-heading">
                        <div class="goshen-dashboard-country-heading">
                            <span class="goshen-dashboard-country-flag" aria-hidden="true">{{ $country['flag'] }}</span>
                            <div>
                                <h3 class="goshen-dashboard-panel-title">{{ $country['country'] }}</h3>
                                <div class="goshen-dashboard-meta">
                                    {{ number_format($country['active_users']) }} active of {{ number_format($country['total_users']) }} registered
                                </div>
                            </div>
                        </div>
                        <div class="goshen-dashboard-share">{{ $activeShare }}%</div>
                    </div>

                    <div
                        class="goshen-dashboard-progress"
                        role="progressbar"
                        aria-label="Active users in {{ $country['country'] }}"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="{{ $activeShare }}"
                    >
                        <span style="width: {{ $activeShare }}%;"></span>
                    </div>

                    <div class="goshen-dashboard-breakdown">
                        <div>
                            <span class="goshen-dashboard-meta">Male</span>
                            <strong>{{ number_format($country['male']) }}</strong>
                        </div>
                        <div>
                            <span class="goshen-dashboard-meta">Female</span>
                            <strong>{{ number_format($country['female']) }}</strong>
                        </div>
                        <div>
                            <span class="goshen-dashboard-meta">Unspecified</span>
                            <strong>{{ number_format($country['other']) }}</strong>
                        </div>
                    </div>
                </article>
            @empty
                <div class="goshen-dashboard-empty">
                    <x-filament::icon icon="heroicon-o-globe-alt" />
                    <strong>No country data yet</strong>
                    <span>Country insights will appear as members complete their profiles.</span>
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
