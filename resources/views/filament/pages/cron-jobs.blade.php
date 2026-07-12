<x-filament-panels::page>
    <style>
        .cron-page { --cron-primary: #0c2230; --cron-accent: #f59e0b; --cron-line: #e5e7eb; --cron-muted: #667085; --cron-soft: #f8fafc; --cron-card: #fff; --cron-text: #111827; display: grid; gap: 22px; color: var(--cron-text); }
        .dark .cron-page { --cron-line: rgba(148, 163, 184, .2); --cron-muted: #a8b0bd; --cron-soft: rgba(15, 23, 42, .55); --cron-card: #111827; --cron-text: #f8fafc; }
        .cron-card { overflow: hidden; border: 1px solid var(--cron-line); border-radius: 20px; background: var(--cron-card); box-shadow: 0 16px 40px rgba(15, 23, 42, .08); }
        .dark .cron-card { box-shadow: 0 18px 46px rgba(0, 0, 0, .28); }
        .cron-pad { padding: 22px; }
        .cron-hero { border: 0; background: radial-gradient(circle at 88% 18%, rgba(245, 158, 11, .28), transparent 28%), linear-gradient(135deg, #0c2230 0%, #12384a 54%, #0f513c 100%); color: #fff; }
        .cron-eyebrow { margin: 0 0 6px; color: #facc15; font-size: 12px; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; }
        .cron-title { margin: 0; font-size: clamp(25px, 3vw, 36px); line-height: 1.1; font-weight: 950; letter-spacing: -.03em; }
        .cron-copy { margin: 8px 0 0; color: var(--cron-muted); font-size: 14px; line-height: 1.65; }
        .cron-hero .cron-copy { color: rgba(255, 255, 255, .82); max-width: 820px; }
        .cron-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
        .cron-metric { padding: 16px; border-radius: 16px; border: 1px solid rgba(255, 255, 255, .16); background: rgba(255, 255, 255, .1); }
        .cron-metric-value { display: block; font-size: 28px; font-weight: 950; line-height: 1; }
        .cron-metric-label { display: block; margin-top: 6px; color: rgba(255, 255, 255, .78); font-size: 12px; font-weight: 850; text-transform: uppercase; letter-spacing: .06em; }
        .cron-section-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; margin-bottom: 16px; }
        .cron-h2 { margin: 0; font-size: 20px; font-weight: 900; letter-spacing: -.02em; }
        .cron-alert { padding: 14px 16px; border-radius: 15px; font-size: 14px; font-weight: 750; line-height: 1.55; }
        .cron-alert-ok { background: #ecfdf3; border: 1px solid #abefc6; color: #067647; }
        .cron-alert-warn { background: #fffaeb; border: 1px solid #fedf89; color: #93370d; }
        .cron-alert-danger { background: #fef3f2; border: 1px solid #fecdca; color: #b42318; }
        .dark .cron-alert-ok { background: rgba(6, 118, 71, .14); border-color: rgba(117, 224, 167, .22); color: #86efac; }
        .dark .cron-alert-warn { background: rgba(147, 83, 13, .18); border-color: rgba(253, 230, 138, .22); color: #fde68a; }
        .dark .cron-alert-danger { background: rgba(180, 35, 24, .14); border-color: rgba(253, 162, 155, .2); color: #fca5a5; }
        .cron-code { display: block; margin-top: 10px; padding: 12px 14px; border-radius: 12px; background: #eef2f7; color: #344054; font: 13px/1.55 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; overflow-x: auto; white-space: pre-wrap; word-break: break-word; }
        .dark .cron-code { background: rgba(2, 6, 23, .5); color: #d1d5db; }
        .cron-fields { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; margin-top: 14px; }
        .cron-field { padding: 13px; border: 1px solid var(--cron-line); border-radius: 14px; background: var(--cron-soft); }
        .cron-field-label { display: block; color: var(--cron-muted); font-size: 11px; font-weight: 900; text-transform: uppercase; letter-spacing: .06em; }
        .cron-field-value { display: block; margin-top: 5px; font-size: 20px; font-weight: 950; }
        .cron-table-wrap { overflow-x: auto; border: 1px solid var(--cron-line); border-radius: 16px; }
        .cron-table { width: 100%; min-width: 980px; border-collapse: collapse; font-size: 14px; }
        .cron-table th, .cron-table td { padding: 13px 14px; text-align: left; vertical-align: top; border-bottom: 1px solid var(--cron-line); }
        .cron-table tr:last-child td { border-bottom: 0; }
        .cron-table th { background: var(--cron-soft); color: var(--cron-muted); font-size: 12px; font-weight: 950; letter-spacing: .05em; text-transform: uppercase; }
        .cron-badge { display: inline-flex; align-items: center; border-radius: 999px; padding: 6px 9px; font-size: 11px; font-weight: 950; white-space: nowrap; }
        .cron-badge-healthy { background: #dcfae6; color: #067647; }
        .cron-badge-warning { background: #fef0c7; color: #b54708; }
        .cron-badge-failed { background: #fee4e2; color: #b42318; }
        .cron-badge-not_tracked { background: #e0f2fe; color: #026aa2; }
        .dark .cron-badge-healthy { background: rgba(22, 163, 74, .18); color: #86efac; }
        .dark .cron-badge-warning { background: rgba(245, 158, 11, .18); color: #fcd34d; }
        .dark .cron-badge-failed { background: rgba(220, 38, 38, .2); color: #fca5a5; }
        .dark .cron-badge-not_tracked { background: rgba(14, 165, 233, .16); color: #7dd3fc; }
        .cron-small { color: var(--cron-muted); font-size: 12px; line-height: 1.5; }
        .cron-button { display: inline-flex; align-items: center; justify-content: center; min-height: 38px; border: 0; border-radius: 11px; padding: 9px 13px; background: var(--cron-accent); color: #111827; font: inherit; font-size: 13px; font-weight: 950; cursor: pointer; }
        .cron-button-dark { background: var(--cron-primary); color: #fff; }
        @media (max-width: 900px) { .cron-grid, .cron-fields { grid-template-columns: 1fr; } .cron-pad { padding: 18px; } .cron-section-head { flex-direction: column; } }
    </style>

    @php
        $hasFailures = ($summary['failed'] ?? 0) > 0;
        $hasWarnings = ($summary['warning'] ?? 0) > 0;
    @endphp

    <div class="cron-page">
        <section class="cron-card cron-pad cron-hero">
            <p class="cron-eyebrow">Production operations</p>
            <h2 class="cron-title">Cron job health report</h2>
            <p class="cron-copy">
                Use this page to confirm that the cPanel scheduler is waking Laravel, and that the expected Laravel jobs are reporting successful runs.
                On cPanel you normally add only the master scheduler cron; Laravel runs the individual jobs internally.
            </p>

            <div class="cron-grid" style="margin-top: 20px;">
                <div class="cron-metric">
                    <span class="cron-metric-value">{{ $summary['healthy'] ?? 0 }}</span>
                    <span class="cron-metric-label">Healthy</span>
                </div>
                <div class="cron-metric">
                    <span class="cron-metric-value">{{ $summary['warning'] ?? 0 }}</span>
                    <span class="cron-metric-label">Warnings</span>
                </div>
                <div class="cron-metric">
                    <span class="cron-metric-value">{{ $summary['failed'] ?? 0 }}</span>
                    <span class="cron-metric-label">Failed</span>
                </div>
                <div class="cron-metric">
                    <span class="cron-metric-value">{{ $summary['not_tracked'] ?? 0 }}</span>
                    <span class="cron-metric-label">Package managed</span>
                </div>
            </div>
        </section>

        @if ($hasFailures)
            <div class="cron-alert cron-alert-danger">One or more cron jobs last reported a failed run. Check the table below and the Laravel log before relying on automated processing.</div>
        @elseif ($hasWarnings)
            <div class="cron-alert cron-alert-warn">Some cron jobs have not reported a recent successful run yet. If this was just deployed, wait a few minutes and refresh.</div>
        @else
            <div class="cron-alert cron-alert-ok">The tracked cron jobs are reporting within their expected windows.</div>
        @endif

        <section class="cron-card cron-pad">
            <div class="cron-section-head">
                <div>
                    <h2 class="cron-h2">cPanel manual setup</h2>
                    <p class="cron-copy">
                        In cPanel → Cron Jobs, set all timing fields to <strong>*</strong> and paste the command below.
                        Do not add each internal Laravel job separately.
                    </p>
                </div>
                <button type="button" class="cron-button" data-copy-cron="{{ e($commands['cpanel_command']) }}">Copy command</button>
            </div>

            <div class="cron-fields">
                <div class="cron-field"><span class="cron-field-label">Minute</span><span class="cron-field-value">{{ $commands['cpanel_minute'] }}</span></div>
                <div class="cron-field"><span class="cron-field-label">Hour</span><span class="cron-field-value">{{ $commands['cpanel_hour'] }}</span></div>
                <div class="cron-field"><span class="cron-field-label">Day</span><span class="cron-field-value">{{ $commands['cpanel_day'] }}</span></div>
                <div class="cron-field"><span class="cron-field-label">Month</span><span class="cron-field-value">{{ $commands['cpanel_month'] }}</span></div>
                <div class="cron-field"><span class="cron-field-label">Weekday</span><span class="cron-field-value">{{ $commands['cpanel_weekday'] }}</span></div>
            </div>

            <code class="cron-code">{{ $commands['cpanel_command'] }}</code>

            <p class="cron-copy">Alternative command if cPanel has trouble with <code>cd</code>:</p>
            <code class="cron-code">{{ $commands['alternative_command'] }}</code>
        </section>

        <section class="cron-card cron-pad">
            <div class="cron-section-head">
                <div>
                    <h2 class="cron-h2">Expected Laravel jobs</h2>
                    <p class="cron-copy">These are the jobs Laravel should run once the master cPanel scheduler is installed.</p>
                </div>
            </div>

            <div class="cron-table-wrap">
                <table class="cron-table">
                    <thead>
                    <tr>
                        <th>Job</th>
                        <th>Schedule</th>
                        <th>Status</th>
                        <th>Last success</th>
                        <th>Last run</th>
                        <th>Command</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $row)
                        @php
                            $state = $row['health']['state'];
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $row['label'] }}</strong>
                                <div class="cron-small">{{ $row['description'] }}</div>
                            </td>
                            <td>
                                <strong>{{ $row['frequency_label'] }}</strong>
                                <div class="cron-small">{{ $row['expression'] }}</div>
                            </td>
                            <td>
                                <span class="cron-badge cron-badge-{{ $state }}">{{ $row['health']['label'] }}</span>
                                <div class="cron-small" style="margin-top: 6px;">{{ $row['health']['detail'] }}</div>
                                @if ($row['last_message'])
                                    <div class="cron-small" style="margin-top: 6px;">{{ $row['last_message'] }}</div>
                                @endif
                            </td>
                            <td>
                                {{ optional($row['last_success_at'])->toDateTimeString() ?: 'Never' }}
                                @if ($row['last_runtime_ms'] !== null)
                                    <div class="cron-small">{{ number_format(((int) $row['last_runtime_ms']) / 1000, 2) }}s runtime</div>
                                @endif
                            </td>
                            <td>
                                {{ optional($row['last_finished_at'] ?? $row['last_started_at'])->toDateTimeString() ?: 'Never' }}
                                <div class="cron-small">
                                    Runs: {{ $row['run_count'] }} · Failures: {{ $row['failure_count'] }}
                                    @if ($row['last_exit_code'] !== null)
                                        · Exit: {{ $row['last_exit_code'] }}
                                    @endif
                                </div>
                            </td>
                            <td><code class="cron-code" style="margin-top: 0;">{{ $row['command'] }}</code></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
        document.querySelectorAll('[data-copy-cron]').forEach((button) => {
            button.addEventListener('click', async () => {
                await navigator.clipboard.writeText(button.dataset.copyCron || '');
                const original = button.textContent;
                button.textContent = 'Copied';
                window.setTimeout(() => button.textContent = original, 1200);
            });
        });
    </script>
</x-filament-panels::page>
