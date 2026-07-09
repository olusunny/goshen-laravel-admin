<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Goshen Experience feedback
        </x-slot>

        <x-slot name="description">
            Checked-in attendee response progress, demographics, and survey coverage.
        </x-slot>

        @php($overview = $this->getOverview())

        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-bottom:18px;">
            <div style="border-radius:20px;padding:22px;background:linear-gradient(135deg,#0c2230,#10493f);color:#fff;box-shadow:0 18px 38px rgba(12,34,48,.18);">
                <div style="font-size:13px;opacity:.72;font-weight:800;">Active surveys</div>
                <div style="margin-top:8px;font-size:38px;line-height:1;font-weight:900;">{{ number_format($overview['active_surveys']) }}</div>
                <div style="margin-top:8px;font-size:12px;color:#f8c857;font-weight:800;">Open Goshen Experience forms</div>
            </div>

            <div style="border-radius:20px;padding:22px;background:linear-gradient(135deg,#ffb522,#ffd36e);color:#0c2230;box-shadow:0 18px 38px rgba(255,181,34,.16);">
                <div style="font-size:13px;opacity:.74;font-weight:900;">Total responses</div>
                <div style="margin-top:8px;font-size:38px;line-height:1;font-weight:900;">{{ number_format($overview['total_responses']) }}</div>
                <div style="margin-top:8px;font-size:12px;font-weight:800;opacity:.82;">Submitted feedback, audio, video, and survey answers</div>
            </div>

            <div style="border-radius:20px;padding:22px;background:#111827;color:#fff;border:1px solid rgba(255,255,255,.08);box-shadow:0 18px 38px rgba(0,0,0,.12);">
                <div style="font-size:13px;opacity:.72;font-weight:800;">Coverage</div>
                <div style="margin-top:8px;font-size:38px;line-height:1;font-weight:900;">{{ count($overview['country']) }}</div>
                <div style="margin-top:8px;font-size:12px;color:#a7f3d0;font-weight:800;">Countries represented in responses</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1.3fr .85fr .85fr;gap:16px;">
            <div style="border-radius:22px;border:1px solid rgba(148,163,184,.24);padding:18px;background:rgba(248,250,252,.65);">
                <div style="font-size:16px;font-weight:900;color:#0f172a;margin-bottom:12px;">Survey response rate</div>
                @forelse ($overview['surveys'] as $survey)
                    <div style="margin-bottom:14px;">
                        <div style="display:flex;gap:12px;align-items:flex-start;justify-content:space-between;">
                            <div>
                                <div style="font-weight:900;color:#0f172a;">{{ $survey['title'] }}</div>
                                <div style="font-size:12px;color:#64748b;">{{ $survey['event'] }}</div>
                            </div>
                            <div style="font-weight:900;color:#0c2230;">{{ $survey['rate'] }}%</div>
                        </div>
                        <div style="font-size:12px;color:#64748b;margin:6px 0;">
                            {{ number_format($survey['responses']) }} responses · {{ number_format($survey['checked_in']) }} checked in
                        </div>
                        <div style="height:9px;border-radius:999px;overflow:hidden;background:#e2e8f0;">
                            <div style="height:100%;width:{{ min(100, $survey['rate']) }}%;background:#ffb522;border-radius:999px;"></div>
                        </div>
                    </div>
                @empty
                    <div style="color:#64748b;">No active Goshen Experience survey is open yet.</div>
                @endforelse
            </div>

            @foreach (['gender' => 'Gender', 'country' => 'Country'] as $key => $label)
                <div style="border-radius:22px;border:1px solid rgba(148,163,184,.24);padding:18px;background:#fff;">
                    <div style="font-size:16px;font-weight:900;color:#0f172a;margin-bottom:12px;">{{ $label }} breakdown</div>
                    @php($total = max(1, array_sum($overview[$key])))
                    @forelse ($overview[$key] as $name => $count)
                        @php($share = round(($count / $total) * 100))
                        <div style="margin-bottom:12px;">
                            <div style="display:flex;justify-content:space-between;gap:10px;font-size:13px;">
                                <span style="font-weight:800;color:#334155;">{{ $name }}</span>
                                <span style="font-weight:900;color:#0c2230;">{{ number_format($count) }}</span>
                            </div>
                            <div style="height:8px;margin-top:6px;border-radius:999px;overflow:hidden;background:#e2e8f0;">
                                <div style="height:100%;width:{{ $share }}%;background:#0ea5e9;border-radius:999px;"></div>
                            </div>
                        </div>
                    @empty
                        <div style="color:#64748b;">No response data yet.</div>
                    @endforelse
                </div>
            @endforeach
        </div>

        <style>
            @media (max-width: 1100px) {
                .fi-widget div[style*="grid-template-columns:repeat(3"],
                .fi-widget div[style*="grid-template-columns:1.3fr"] {
                    grid-template-columns: 1fr !important;
                }
            }

            .dark .fi-widget div[style*="background:rgba(248,250,252"],
            .dark .fi-widget div[style*="background:#fff"] {
                background: #111827 !important;
                border-color: rgba(255,255,255,.08) !important;
            }

            .dark .fi-widget div[style*="color:#0f172a"],
            .dark .fi-widget span[style*="color:#334155"],
            .dark .fi-widget span[style*="color:#0c2230"] {
                color: #f8fafc !important;
            }
        </style>
    </x-filament::section>
</x-filament-widgets::widget>
