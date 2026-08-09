<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Goshen Experience feedback
        </x-slot>

        <x-slot name="description">
            Checked-in attendee response progress, demographics, and survey coverage.
        </x-slot>

        @php($overview = $this->getOverview())

        <div class="goshen-dashboard-grid">
            <article class="goshen-dashboard-metric">
                <div class="goshen-dashboard-label">Active surveys</div>
                <div class="goshen-dashboard-value">{{ number_format($overview['active_surveys']) }}</div>
                <div class="goshen-dashboard-note">Open Goshen Experience forms</div>
            </article>

            <article class="goshen-dashboard-metric goshen-dashboard-metric--primary">
                <div class="goshen-dashboard-label">Total responses</div>
                <div class="goshen-dashboard-value">{{ number_format($overview['total_responses']) }}</div>
                <div class="goshen-dashboard-note">Submitted attendee feedback and survey answers</div>
            </article>

            <article class="goshen-dashboard-metric">
                <div class="goshen-dashboard-label">Country coverage</div>
                <div class="goshen-dashboard-value">{{ number_format(count($overview['country'])) }}</div>
                <div class="goshen-dashboard-note">Countries represented in responses</div>
            </article>
        </div>

        <div class="goshen-dashboard-grid goshen-dashboard-grid--feature">
            <article class="goshen-dashboard-panel">
                <h3 class="goshen-dashboard-panel-title">Survey response rate</h3>

                <div class="goshen-dashboard-list">
                    @forelse ($overview['surveys'] as $survey)
                        <div class="goshen-dashboard-row">
                            <div class="goshen-dashboard-row-heading">
                                <div>
                                    <strong class="goshen-dashboard-panel-title">{{ $survey['title'] }}</strong>
                                    <div class="goshen-dashboard-meta">{{ $survey['event'] }}</div>
                                </div>
                                <strong class="goshen-dashboard-row-value">{{ $survey['rate'] }}%</strong>
                            </div>
                            <div class="goshen-dashboard-meta">
                                {{ number_format($survey['responses']) }} responses from {{ number_format($survey['checked_in']) }} checked-in attendees
                            </div>
                            <div
                                class="goshen-dashboard-progress"
                                role="progressbar"
                                aria-label="Response rate for {{ $survey['title'] }}"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                aria-valuenow="{{ min(100, $survey['rate']) }}"
                            >
                                <span style="width: {{ min(100, $survey['rate']) }}%;"></span>
                            </div>
                        </div>
                    @empty
                        <div class="goshen-dashboard-empty">
                            <x-filament::icon icon="heroicon-o-chat-bubble-bottom-center-text" />
                            <strong>No active survey</strong>
                            <span>Response tracking will appear when a Goshen Experience survey is opened.</span>
                        </div>
                    @endforelse
                </div>
            </article>

            @foreach (['gender' => 'Gender', 'country' => 'Country'] as $key => $label)
                <article class="goshen-dashboard-panel">
                    <h3 class="goshen-dashboard-panel-title">{{ $label }} breakdown</h3>
                    @php($total = max(1, array_sum($overview[$key])))

                    <div class="goshen-dashboard-list">
                        @forelse ($overview[$key] as $name => $count)
                            @php($share = round(($count / $total) * 100))
                            <div class="goshen-dashboard-row">
                                <div class="goshen-dashboard-row-heading">
                                    <span class="goshen-dashboard-meta">{{ $name }}</span>
                                    <strong class="goshen-dashboard-row-value">{{ number_format($count) }}</strong>
                                </div>
                                <div
                                    class="goshen-dashboard-progress"
                                    role="progressbar"
                                    aria-label="{{ $name }} share of {{ strtolower($label) }} responses"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    aria-valuenow="{{ $share }}"
                                >
                                    <span style="width: {{ $share }}%;"></span>
                                </div>
                            </div>
                        @empty
                            <div class="goshen-dashboard-empty">
                                <x-filament::icon icon="heroicon-o-chart-bar" />
                                <strong>No response data yet</strong>
                                <span>This breakdown will populate after attendees submit feedback.</span>
                            </div>
                        @endforelse
                    </div>
                </article>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
