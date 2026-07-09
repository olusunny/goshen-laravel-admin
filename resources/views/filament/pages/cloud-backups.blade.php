<x-filament-panels::page>
    @php
        $providerLabels = [
            'google' => 'Google Drive',
            'onedrive' => 'OneDrive',
        ];
        $activeLabel = $providerLabels[$activeProvider] ?? 'Google Drive';
        $activeConfigured = (bool) ($oauthStatus[$activeProvider] ?? false);
    @endphp

    <style>
        [x-cloak] { display: none !important; }
        .cb-page { --cb-primary: #0c2230; --cb-accent: #f59e0b; --cb-line: #e5e7eb; --cb-muted: #667085; --cb-soft: #f8fafc; --cb-card: #ffffff; --cb-text: #111827; --cb-shadow: 0 16px 40px rgba(15, 23, 42, .08); display: grid; gap: 24px; color: var(--cb-text); }
        .dark .cb-page { --cb-line: rgba(148, 163, 184, .18); --cb-muted: #a8b0bd; --cb-soft: rgba(15, 23, 42, .55); --cb-card: #111827; --cb-text: #f8fafc; --cb-shadow: 0 18px 46px rgba(0, 0, 0, .28); }
        .cb-card { background: var(--cb-card); border: 1px solid var(--cb-line); border-radius: 20px; box-shadow: var(--cb-shadow); overflow: hidden; }
        .cb-card-pad { padding: 24px; }
        .cb-hero { position: relative; isolation: isolate; overflow: hidden; border: 0; background: radial-gradient(circle at 88% 18%, rgba(245, 158, 11, .26), transparent 28%), linear-gradient(135deg, #0c2230 0%, #12384a 54%, #0f513c 100%); color: #fff; }
        .cb-hero:after { content: ""; position: absolute; inset: auto -80px -130px auto; width: 300px; height: 300px; border: 1px solid rgba(255, 255, 255, .16); border-radius: 999px; z-index: -1; }
        .cb-eyebrow { margin: 0 0 6px; color: #facc15; font-size: 12px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        .cb-title { margin: 0; font-size: clamp(24px, 3vw, 34px); line-height: 1.1; font-weight: 900; letter-spacing: -.03em; }
        .cb-subtitle { margin: 10px 0 0; max-width: 780px; color: rgba(255, 255, 255, .82); font-size: 15px; line-height: 1.65; }
        .cb-status-pill { display: inline-flex; align-items: center; gap: 8px; margin-top: 18px; padding: 10px 14px; border-radius: 999px; background: rgba(255, 255, 255, .12); color: #fff; font-size: 13px; font-weight: 800; border: 1px solid rgba(255, 255, 255, .18); }
        .cb-status-dot { width: 9px; height: 9px; border-radius: 999px; background: #fbbf24; box-shadow: 0 0 0 5px rgba(251, 191, 36, .15); }
        .cb-section-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; padding-bottom: 18px; border-bottom: 1px solid var(--cb-line); }
        .cb-h2 { margin: 0; font-size: 20px; line-height: 1.25; font-weight: 850; letter-spacing: -.02em; }
        .cb-copy { margin: 6px 0 0; color: var(--cb-muted); font-size: 14px; line-height: 1.6; }
        .cb-links { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
        .cb-link { display: inline-flex; align-items: center; min-height: 32px; padding: 7px 10px; border-radius: 999px; background: #eff6ff; color: #155eef; font-size: 12px; font-weight: 800; text-decoration: none; border: 1px solid #bfdbfe; }
        .dark .cb-link { background: rgba(37, 99, 235, .13); color: #93c5fd; border-color: rgba(147, 197, 253, .22); }
        .cb-alert { padding: 13px 15px; border-radius: 14px; font-size: 14px; font-weight: 700; line-height: 1.5; }
        .cb-alert-success { background: #ecfdf3; border: 1px solid #abefc6; color: #067647; }
        .cb-alert-error { background: #fef3f2; border: 1px solid #fecdca; color: #b42318; }
        .cb-alert-warning { background: #fffaeb; border: 1px solid #fedf89; color: #93370d; }
        .dark .cb-alert-success { background: rgba(6, 118, 71, .14); border-color: rgba(117, 224, 167, .22); color: #86efac; }
        .dark .cb-alert-error { background: rgba(180, 35, 24, .14); border-color: rgba(253, 162, 155, .2); color: #fca5a5; }
        .dark .cb-alert-warning { background: rgba(147, 83, 13, .18); border-color: rgba(253, 230, 138, .22); color: #fde68a; }
        .cb-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .cb-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .cb-provider-option { display: block; cursor: pointer; }
        .cb-provider-option input { position: absolute; opacity: 0; pointer-events: none; }
        .cb-provider-card { display: grid; min-height: 132px; gap: 12px; padding: 18px; border-radius: 18px; border: 1px solid var(--cb-line); background: var(--cb-soft); transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease; }
        .cb-provider-option:hover .cb-provider-card { transform: translateY(-1px); border-color: rgba(245, 158, 11, .45); }
        .cb-provider-option input:checked + .cb-provider-card { border-color: var(--cb-accent); box-shadow: 0 0 0 3px rgba(245, 158, 11, .16), var(--cb-shadow); background: linear-gradient(135deg, rgba(245, 158, 11, .1), rgba(12, 34, 48, .03)); }
        .dark .cb-provider-option input:checked + .cb-provider-card { background: linear-gradient(135deg, rgba(245, 158, 11, .14), rgba(15, 81, 60, .12)); }
        .cb-provider-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .cb-provider-name { display: block; font-size: 18px; line-height: 1.2; font-weight: 900; }
        .cb-provider-desc { display: block; margin-top: 8px; color: var(--cb-muted); font-size: 13px; line-height: 1.55; }
        .cb-badge { display: inline-flex; white-space: nowrap; align-items: center; border-radius: 999px; padding: 6px 9px; font-size: 11px; font-weight: 900; }
        .cb-badge-ok { background: #dcfae6; color: #067647; }
        .cb-badge-warn { background: #fef0c7; color: #b54708; }
        .dark .cb-badge-ok { background: rgba(22, 163, 74, .18); color: #86efac; }
        .dark .cb-badge-warn { background: rgba(245, 158, 11, .18); color: #fcd34d; }
        .cb-form-panel { margin-top: 18px; padding: 18px; border-radius: 18px; border: 1px solid var(--cb-line); background: var(--cb-soft); }
        .cb-form-title { margin: 0; font-size: 16px; font-weight: 900; }
        .cb-code { display: block; margin-top: 10px; padding: 11px 12px; border-radius: 12px; background: #eef2f7; color: #344054; font: 13px/1.5 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; overflow-x: auto; }
        .dark .cb-code { background: rgba(2, 6, 23, .48); color: #d1d5db; }
        .cb-field { display: grid; gap: 7px; }
        .cb-label { color: var(--cb-text); font-size: 13px; font-weight: 800; }
        .cb-input, .cb-select, .cb-textarea { width: 100%; min-height: 44px; box-sizing: border-box; border: 1px solid #d0d5dd; border-radius: 12px; padding: 10px 12px; background: #fff; color: #111827; font: inherit; outline: none; transition: border-color .18s ease, box-shadow .18s ease; }
        .cb-input:focus, .cb-select:focus, .cb-textarea:focus { border-color: var(--cb-accent); box-shadow: 0 0 0 3px rgba(245, 158, 11, .16); }
        .cb-textarea { min-height: 110px; resize: vertical; }
        .dark .cb-input, .dark .cb-select, .dark .cb-textarea { background: #0b1220; border-color: rgba(148, 163, 184, .32); color: #f8fafc; }
        .cb-check { display: inline-flex; gap: 9px; align-items: center; color: var(--cb-muted); font-size: 13px; font-weight: 750; }
        .cb-check input { width: 16px; height: 16px; accent-color: var(--cb-accent); }
        .cb-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
        .cb-button { display: inline-flex; justify-content: center; align-items: center; min-height: 42px; border: 0; border-radius: 12px; padding: 10px 16px; background: var(--cb-accent); color: #111827; font: inherit; font-size: 14px; font-weight: 900; cursor: pointer; box-shadow: 0 10px 22px rgba(245, 158, 11, .22); }
        .cb-button:hover { filter: brightness(.98); }
        .cb-button-dark { background: var(--cb-primary); color: #fff; box-shadow: 0 10px 22px rgba(12, 34, 48, .16); }
        .cb-button-danger { background: #dc2626; color: #fff; box-shadow: none; }
        .cb-button:disabled { cursor: not-allowed; background: #98a2b3; color: #f8fafc; box-shadow: none; }
        .cb-table-wrap { margin-top: 16px; overflow-x: auto; border: 1px solid var(--cb-line); border-radius: 16px; }
        .cb-table { width: 100%; border-collapse: collapse; min-width: 760px; font-size: 14px; }
        .cb-table th, .cb-table td { padding: 13px 14px; text-align: left; border-bottom: 1px solid var(--cb-line); vertical-align: middle; }
        .cb-table th { color: var(--cb-muted); background: var(--cb-soft); font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: .05em; }
        .cb-table tr:last-child td { border-bottom: 0; }
        .cb-empty { padding: 20px; color: var(--cb-muted); font-size: 14px; }
        .cb-progress-list { display: grid; gap: 12px; margin-top: 18px; }
        .cb-progress-card { padding: 14px; border: 1px solid var(--cb-line); border-radius: 16px; background: var(--cb-soft); }
        .cb-progress-top { display: flex; justify-content: space-between; gap: 14px; align-items: center; margin-bottom: 10px; }
        .cb-progress-title { font-size: 14px; font-weight: 900; }
        .cb-progress-meta { color: var(--cb-muted); font-size: 12px; font-weight: 800; }
        .cb-progress-track { height: 12px; overflow: hidden; border-radius: 999px; background: rgba(148, 163, 184, .22); }
        .cb-progress-fill { height: 100%; width: 0; border-radius: inherit; background: linear-gradient(90deg, var(--cb-accent), #22c55e); transition: width .35s ease; }
        .cb-progress-step { margin-top: 9px; color: var(--cb-muted); font-size: 13px; font-weight: 750; }
        .cb-progress-card[data-status="failed"] .cb-progress-fill { background: #dc2626; }
        .cb-progress-card[data-status="succeeded"] .cb-progress-fill { background: #16a34a; }
        .cb-tabs { display: inline-flex; gap: 6px; padding: 6px; border: 1px solid var(--cb-line); border-radius: 16px; background: var(--cb-card); box-shadow: var(--cb-shadow); width: fit-content; max-width: 100%; }
        .cb-tab { border: 0; border-radius: 12px; padding: 10px 14px; background: transparent; color: var(--cb-muted); font: inherit; font-size: 14px; font-weight: 900; cursor: pointer; }
        .cb-tab[aria-selected="true"] { background: var(--cb-primary); color: #fff; box-shadow: 0 10px 22px rgba(12, 34, 48, .14); }
        .dark .cb-tab[aria-selected="true"] { background: var(--cb-accent); color: #111827; }
        .cb-tab-panel { display: grid; gap: 24px; }
        @media (max-width: 900px) { .cb-grid, .cb-grid-3 { grid-template-columns: 1fr; } .cb-section-head { flex-direction: column; } .cb-card-pad { padding: 18px; } }
    </style>

    <div class="cb-page" x-data="{ provider: @js($activeProvider), tab: 'operations' }">
        @if (session('status'))
            <div class="cb-alert cb-alert-success">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="cb-alert cb-alert-error">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="cb-alert cb-alert-error">{{ $errors->first() }}</div>
        @endif

        <section class="cb-card cb-card-pad cb-hero">
            <p class="cb-eyebrow">Secure backup storage</p>
            <h2 class="cb-title">Choose one cloud drive for backups</h2>
            <p class="cb-subtitle">
                Select the storage provider this admin should use. Only the selected provider is configured and connected here,
                keeping the backup flow clean and avoiding accidental setup of two drives at once.
            </p>
            <div class="cb-status-pill"><span class="cb-status-dot"></span> Active drive: {{ $activeLabel }}</div>
        </section>

        <div class="cb-tabs" role="tablist" aria-label="Cloud backup sections">
            <button type="button" class="cb-tab" role="tab" :aria-selected="tab === 'operations'" @click="tab = 'operations'">
                Backup operations
            </button>
            <button type="button" class="cb-tab" role="tab" :aria-selected="tab === 'settings'" @click="tab = 'settings'">
                Settings & testing
            </button>
        </div>

        <div class="cb-tab-panel" x-show="tab === 'settings'" x-cloak>
        <section class="cb-card cb-card-pad">
            <div class="cb-section-head">
                <div>
                    <h2 class="cb-h2">Cloud OAuth Credentials</h2>
                    <p class="cb-copy">Self-hosted cloud backups need an OAuth app from the provider you choose. Credentials are encrypted before storage.</p>
                    <div class="cb-links">
                        <a class="cb-link" href="{{ $oauthLinks['google_drive_api'] }}" target="_blank" rel="noopener">Enable Google Drive API</a>
                        <a class="cb-link" href="{{ $oauthLinks['google_console'] }}" target="_blank" rel="noopener">Create Google OAuth client</a>
                        <a class="cb-link" href="{{ $oauthLinks['google_docs'] }}" target="_blank" rel="noopener">Google setup guide</a>
                        <a class="cb-link" href="{{ $oauthLinks['microsoft_console'] }}" target="_blank" rel="noopener">Create Microsoft app</a>
                        <a class="cb-link" href="{{ $oauthLinks['microsoft_docs'] }}" target="_blank" rel="noopener">Microsoft app guide</a>
                        <a class="cb-link" href="{{ $oauthLinks['onedrive_docs'] }}" target="_blank" rel="noopener">OneDrive auth guide</a>
                    </div>
                </div>
            </div>

            <form method="post" action="{{ route('cloud-backup.oauth-settings.store') }}">
                @csrf

                <div class="cb-grid" style="margin-top: 20px;">
                    @foreach ($providerLabels as $provider => $label)
                        <label class="cb-provider-option">
                            <input type="radio" name="active_provider" value="{{ $provider }}" x-model="provider" @checked($activeProvider === $provider)>
                            <span class="cb-provider-card">
                                <span class="cb-provider-top">
                                    <span>
                                        <span class="cb-provider-name">{{ $label }}</span>
                                        <span class="cb-provider-desc">
                                            {{ $provider === 'google' ? 'Back up files and database archives to a Google Drive folder.' : 'Back up files and database archives to a OneDrive folder.' }}
                                        </span>
                                    </span>
                                    <span class="cb-badge {{ ($oauthStatus[$provider] ?? false) ? 'cb-badge-ok' : 'cb-badge-warn' }}">
                                        {{ ($oauthStatus[$provider] ?? false) ? 'Configured' : 'Needs setup' }}
                                    </span>
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div x-show="provider === 'google'" x-cloak class="cb-form-panel">
                    <h3 class="cb-form-title">Google Drive setup</h3>
                    <p class="cb-copy">Authorized redirect URI:</p>
                    <code class="cb-code">{{ route('cloud-backup.oauth.callback', ['provider' => 'google']) }}</code>

                    <div class="cb-grid" style="margin-top: 18px;">
                        <div class="cb-field">
                            <label for="google_client_id" class="cb-label">Google client ID</label>
                            <input id="google_client_id" name="google_client_id" value="{{ old('google_client_id', $oauthSettings['google']?->client_id) }}" autocomplete="off" class="cb-input">
                        </div>
                        <div class="cb-field">
                            <label for="google_client_secret" class="cb-label">Google client secret</label>
                            <input id="google_client_secret" name="google_client_secret" type="password" placeholder="{{ filled($oauthSettings['google']?->client_secret) || filled(config('cloud-backup.oauth.google.client_secret')) ? 'Saved; leave blank to keep current secret' : 'Paste Google client secret' }}" autocomplete="new-password" class="cb-input">
                        </div>
                        <div class="cb-field" style="grid-column: 1 / -1;">
                            <label for="google_redirect_uri" class="cb-label">Google redirect URI override</label>
                            <input id="google_redirect_uri" name="google_redirect_uri" value="{{ old('google_redirect_uri', $oauthSettings['google']?->redirect_uri ?: route('cloud-backup.oauth.callback', ['provider' => 'google'])) }}" class="cb-input">
                        </div>
                        <label class="cb-check"><input type="checkbox" name="google_clear_secret" value="1"> Clear saved Google secret</label>
                    </div>
                    <div class="cb-actions" style="margin-top: 14px;">
                        <button
                            type="submit"
                            formaction="{{ route('cloud-backup.oauth-settings.reset', ['provider' => 'google']) }}"
                            formnovalidate
                            onclick="return confirm('Reset saved Google Drive OAuth credentials? Existing connected accounts may stop refreshing until new credentials are saved.');"
                            class="cb-button cb-button-danger"
                        >
                            Reset Google credentials
                        </button>
                    </div>
                </div>

                <div x-show="provider === 'onedrive'" x-cloak class="cb-form-panel">
                    <h3 class="cb-form-title">OneDrive setup</h3>
                    <p class="cb-copy">Redirect URI:</p>
                    <code class="cb-code">{{ route('cloud-backup.oauth.callback', ['provider' => 'onedrive']) }}</code>

                    <div class="cb-grid" style="margin-top: 18px;">
                        <div class="cb-field">
                            <label for="onedrive_client_id" class="cb-label">OneDrive client ID</label>
                            <input id="onedrive_client_id" name="onedrive_client_id" value="{{ old('onedrive_client_id', $oauthSettings['onedrive']?->client_id) }}" autocomplete="off" class="cb-input">
                        </div>
                        <div class="cb-field">
                            <label for="onedrive_client_secret" class="cb-label">OneDrive client secret</label>
                            <input id="onedrive_client_secret" name="onedrive_client_secret" type="password" placeholder="{{ filled($oauthSettings['onedrive']?->client_secret) || filled(config('cloud-backup.oauth.onedrive.client_secret')) ? 'Saved; leave blank to keep current secret' : 'Paste OneDrive client secret' }}" autocomplete="new-password" class="cb-input">
                        </div>
                        <div class="cb-field">
                            <label for="onedrive_tenant" class="cb-label">Microsoft tenant</label>
                            <input id="onedrive_tenant" name="onedrive_tenant" value="{{ old('onedrive_tenant', $oauthSettings['onedrive']?->tenant ?: config('cloud-backup.oauth.onedrive.tenant', 'common')) }}" class="cb-input">
                        </div>
                        <div class="cb-field">
                            <label for="onedrive_redirect_uri" class="cb-label">OneDrive redirect URI override</label>
                            <input id="onedrive_redirect_uri" name="onedrive_redirect_uri" value="{{ old('onedrive_redirect_uri', $oauthSettings['onedrive']?->redirect_uri ?: route('cloud-backup.oauth.callback', ['provider' => 'onedrive'])) }}" class="cb-input">
                        </div>
                        <label class="cb-check"><input type="checkbox" name="onedrive_clear_secret" value="1"> Clear saved OneDrive secret</label>
                    </div>
                    <div class="cb-actions" style="margin-top: 14px;">
                        <button
                            type="submit"
                            formaction="{{ route('cloud-backup.oauth-settings.reset', ['provider' => 'onedrive']) }}"
                            formnovalidate
                            onclick="return confirm('Reset saved OneDrive OAuth credentials? Existing connected accounts may stop refreshing until new credentials are saved.');"
                            class="cb-button cb-button-danger"
                        >
                            Reset OneDrive credentials
                        </button>
                    </div>
                </div>

                <div class="cb-actions">
                    <button type="submit" class="cb-button">Save selected drive</button>
                    <button
                        type="submit"
                        formaction="{{ route('cloud-backup.oauth-settings.test') }}"
                        class="cb-button cb-button-dark"
                    >
                        Test selected credentials
                    </button>
                </div>
                <p class="cb-copy" style="margin-top: 10px;">
                    Testing validates the selected OAuth app credentials without saving them or connecting your cloud drive.
                </p>
            </form>
        </section>
        </div>

        @php
            $activeRuns = $runs->whereIn('status', ['queued', 'running'])->values();
        @endphp

        @if ($activeRuns->isNotEmpty())
            <section class="cb-card cb-card-pad" id="backup-progress-section" x-show="tab === 'operations'" x-cloak>
                <div class="cb-section-head">
                    <div>
                        <h2 class="cb-h2">Backup progress</h2>
                        <p class="cb-copy">Live progress for manual or scheduled backups that are currently queued or running.</p>
                    </div>
                </div>

                <div class="cb-progress-list">
                    @foreach ($activeRuns as $run)
                        <div
                            class="cb-progress-card"
                            data-backup-run="{{ $run->id }}"
                            data-progress-url="{{ route('cloud-backup.runs.progress', $run) }}"
                            data-status="{{ $run->status }}"
                        >
                            <div class="cb-progress-top">
                                <div>
                                    <div class="cb-progress-title">{{ $run->connection?->name ?: 'Cloud backup' }}</div>
                                    <div class="cb-progress-meta" data-backup-name>{{ $run->backup_name ?: ($run->schedule ? 'Scheduled backup' : 'On-demand backup') }}</div>
                                </div>
                                <div class="cb-progress-meta"><span data-progress-percent>{{ (int) ($run->progress_percent ?? 0) }}</span>%</div>
                            </div>
                            <div class="cb-progress-track">
                                <div class="cb-progress-fill" data-progress-fill style="width: {{ (int) ($run->progress_percent ?? 0) }}%;"></div>
                            </div>
                            <div class="cb-progress-step" data-progress-step>{{ $run->current_step ?: ucfirst($run->status) }}</div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="cb-card cb-card-pad" x-show="tab === 'settings'" x-cloak>
            <div class="cb-section-head">
                <div>
                    <h2 class="cb-h2">Connect {{ $activeLabel }}</h2>
                    <p class="cb-copy">Only the selected provider is shown here. Change and save the active drive above if you want to use another provider.</p>
                </div>
            </div>

            @unless ($activeConfigured)
                <div class="cb-alert cb-alert-warning" style="margin-top: 18px;">
                    {{ $activeLabel }} is not configured yet. Save the {{ $activeLabel }} OAuth credentials before connecting the drive account.
                </div>
            @endunless

            <div style="margin-top: 18px;">
                @foreach ($providerLabels as $provider => $label)
                    <form x-show="provider === @js($provider)" x-cloak method="post" action="{{ route('cloud-backup.oauth.redirect', ['provider' => $provider]) }}">
                        @csrf
                        <div class="cb-grid">
                            <div class="cb-field">
                                <label for="{{ $provider }}_name" class="cb-label">{{ $label }} connection name</label>
                                <input id="{{ $provider }}_name" name="name" value="{{ $provider === 'google' ? 'Google Drive backup' : 'OneDrive backup' }}" class="cb-input">
                            </div>
                            <div class="cb-field">
                                <label for="{{ $provider }}_folder" class="cb-label">Folder path</label>
                                <input id="{{ $provider }}_folder" name="folder_path" value="LaravelBackups" class="cb-input">
                            </div>
                        </div>
                        <div class="cb-actions">
                            <button type="submit" @disabled(! ($oauthStatus[$provider] ?? false)) class="cb-button cb-button-dark">Connect {{ $label }}</button>
                        </div>
                    </form>
                @endforeach
            </div>
        </section>

        <section class="cb-card cb-card-pad" x-show="tab === 'settings'" x-cloak>
            <h2 class="cb-h2">Connections</h2>
            <div class="cb-table-wrap">
                <table class="cb-table">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Provider</th>
                        <th>Account</th>
                        <th>Folder</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($connections as $connection)
                        <tr>
                            <td><strong>{{ $connection->name }}</strong></td>
                            <td>{{ $providerLabels[$connection->provider] ?? ucfirst($connection->provider) }}</td>
                            <td>{{ $connection->owner_email ?: $connection->owner_name ?: 'Connected' }}</td>
                            <td>{{ $connection->folder_path }}</td>
                            <td>
                                {{ $connection->last_error ? 'Needs attention' : 'Ready' }}
                                @if ($connection->last_error)
                                    <div class="cb-copy" style="margin-top: 4px;">{{ $connection->last_error }}</div>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <form method="post" action="{{ route('cloud-backup.connections.test', $connection) }}">
                                        @csrf
                                        <button class="cb-button cb-button-dark" type="submit">Test connection</button>
                                    </form>
                                    <form method="post" action="{{ route('cloud-backup.connections.destroy', $connection) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="cb-button cb-button-danger" type="submit">Remove</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="cb-empty">No cloud storage is connected.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="cb-card cb-card-pad" x-show="tab === 'operations'" x-cloak>
            <div class="cb-section-head">
                <div>
                    <h2 class="cb-h2">Backup now</h2>
                    <p class="cb-copy">
                        Run an immediate backup using a connected {{ $activeLabel }} account. This does not create or depend on a schedule.
                    </p>
                </div>
            </div>

            <form method="post" action="{{ route('cloud-backup.backups.run') }}" style="margin-top: 18px;">
                @csrf
                <div class="cb-grid">
                    <div class="cb-field">
                        <label for="ondemand_connection_id" class="cb-label">Connection</label>
                        <select id="ondemand_connection_id" name="connection_id" required class="cb-select">
                            @foreach ($activeConnections as $connection)
                                <option value="{{ $connection->id }}">{{ $connection->name }} ({{ $providerLabels[$connection->provider] ?? ucfirst($connection->provider) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="cb-field">
                        <label for="ondemand_retention_count" class="cb-label">Files to retain</label>
                        <input id="ondemand_retention_count" name="retention_count" type="number" min="1" max="100" value="7" class="cb-input">
                    </div>
                    <div class="cb-field">
                        <label for="ondemand_source_path" class="cb-label">Source path</label>
                        <input id="ondemand_source_path" name="source_path" value="{{ base_path() }}" class="cb-input">
                    </div>
                    <div class="cb-field">
                        <label for="ondemand_database_connection" class="cb-label">Database connection</label>
                        <input id="ondemand_database_connection" name="database_connection" value="{{ config('database.default') }}" class="cb-input">
                    </div>
                    <div class="cb-field">
                        <label class="cb-check"><input type="checkbox" name="include_files" value="1" checked> Include files</label>
                        <label class="cb-check"><input type="checkbox" name="include_database" value="1" checked> Include database</label>
                    </div>
                    <div class="cb-field">
                        <label for="ondemand_exclude_paths" class="cb-label">Extra excluded paths</label>
                        <textarea id="ondemand_exclude_paths" name="exclude_paths" class="cb-textarea"></textarea>
                    </div>
                </div>
                <div class="cb-actions">
                    <button type="submit" @disabled($activeConnections->isEmpty()) class="cb-button cb-button-dark">Backup now</button>
                </div>
                @if ($activeConnections->isEmpty())
                    <div class="cb-alert cb-alert-warning" style="margin-top: 14px;">Connect {{ $activeLabel }} before running an on-demand backup.</div>
                @endif
            </form>
        </section>

        <section class="cb-card cb-card-pad" x-show="tab === 'operations'" x-cloak>
            <div class="cb-section-head">
                <div>
                    <h2 class="cb-h2">Create Schedule</h2>
                    <p class="cb-copy">Schedules can only use connections from the currently selected drive: {{ $activeLabel }}.</p>
                </div>
            </div>

            <form method="post" action="{{ route('cloud-backup.schedules.store') }}" style="margin-top: 18px;">
                @csrf
                <div class="cb-grid">
                    <div class="cb-field">
                        <label for="connection_id" class="cb-label">Connection</label>
                        <select id="connection_id" name="connection_id" required class="cb-select">
                            @foreach ($activeConnections as $connection)
                                <option value="{{ $connection->id }}">{{ $connection->name }} ({{ $providerLabels[$connection->provider] ?? ucfirst($connection->provider) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="cb-field">
                        <label for="name" class="cb-label">Schedule name</label>
                        <input id="name" name="name" value="Daily site backup" required class="cb-input">
                    </div>
                    <div class="cb-field">
                        <label for="frequency" class="cb-label">Frequency</label>
                        <select id="frequency" name="frequency" class="cb-select">
                            <option value="hourly">Hourly</option>
                            <option value="daily" selected>Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    <div class="cb-field">
                        <label for="schedule_time" class="cb-label">Time of day</label>
                        <input id="schedule_time" name="schedule_time" type="time" value="00:30" class="cb-input">
                        <span class="cb-copy" style="margin: 0;">Used for daily, weekly, and monthly schedules.</span>
                    </div>
                    <div class="cb-field">
                        <label for="schedule_weekday" class="cb-label">Weekly day</label>
                        <select id="schedule_weekday" name="schedule_weekday" class="cb-select">
                            <option value="0" selected>Sunday</option>
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                            <option value="6">Saturday</option>
                        </select>
                        <span class="cb-copy" style="margin: 0;">Example: weekly, Sunday, 12:30 AM.</span>
                    </div>
                    <div class="cb-field">
                        <label for="schedule_month_day" class="cb-label">Monthly day</label>
                        <input id="schedule_month_day" name="schedule_month_day" type="number" min="1" max="28" value="1" class="cb-input">
                    </div>
                    <div class="cb-field">
                        <label for="timezone" class="cb-label">Schedule timezone</label>
                        <input id="timezone" name="timezone" value="Africa/Lagos" class="cb-input">
                    </div>
                    <div class="cb-field">
                        <label for="retention_count" class="cb-label">Files to retain</label>
                        <input id="retention_count" name="retention_count" type="number" min="1" max="100" value="7" class="cb-input">
                    </div>
                    <div class="cb-field">
                        <label for="source_path" class="cb-label">Source path</label>
                        <input id="source_path" name="source_path" value="{{ base_path() }}" class="cb-input">
                    </div>
                    <div class="cb-field">
                        <label for="database_connection" class="cb-label">Database connection</label>
                        <input id="database_connection" name="database_connection" value="{{ config('database.default') }}" class="cb-input">
                    </div>
                    <div class="cb-field">
                        <label class="cb-check"><input type="checkbox" name="include_files" value="1" checked> Include files</label>
                        <label class="cb-check"><input type="checkbox" name="include_database" value="1" checked> Include database</label>
                    </div>
                    <div class="cb-field">
                        <label for="exclude_paths" class="cb-label">Extra excluded paths</label>
                        <textarea id="exclude_paths" name="exclude_paths" class="cb-textarea"></textarea>
                    </div>
                </div>
                <div class="cb-actions">
                    <button type="submit" @disabled($activeConnections->isEmpty()) class="cb-button">Create Schedule</button>
                </div>
                @if ($activeConnections->isEmpty())
                    <div class="cb-alert cb-alert-warning" style="margin-top: 14px;">Connect {{ $activeLabel }} before creating a schedule.</div>
                @endif
            </form>
        </section>

        <section class="cb-card cb-card-pad" x-show="tab === 'operations'" x-cloak>
            <h2 class="cb-h2">Schedules</h2>
            <div class="cb-table-wrap">
                <table class="cb-table">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Connection</th>
                        <th>Frequency</th>
                        <th>Timing</th>
                        <th>Next Run</th>
                        <th>Last Run</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($schedules as $schedule)
                        <tr>
                            <td><strong>{{ $schedule->name }}</strong></td>
                            <td>{{ $schedule->connection?->name }}</td>
                            <td>{{ ucfirst($schedule->frequency) }}</td>
                            <td>
                                @php
                                    $weekdayLabels = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                @endphp
                                {{ $schedule->frequency === 'weekly' ? ($weekdayLabels[(int) ($schedule->schedule_weekday ?? 0)] ?? 'Sunday').' at ' : '' }}
                                {{ $schedule->frequency === 'monthly' ? 'Day '.(int) ($schedule->schedule_month_day ?? 1).' at ' : '' }}
                                {{ in_array($schedule->frequency, ['daily', 'weekly', 'monthly'], true) ? substr((string) ($schedule->schedule_time ?? '00:30'), 0, 5) : 'Every hour' }}
                                <div class="cb-copy" style="margin-top: 3px;">{{ $schedule->timezone ?: 'Africa/Lagos' }}</div>
                            </td>
                            <td>{{ optional($schedule->next_run_at)->toDateTimeString() ?: 'Due now' }}</td>
                            <td>{{ optional($schedule->last_run_at)->toDateTimeString() ?: 'Never' }}</td>
                            <td>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <form method="post" action="{{ route('cloud-backup.schedules.run', $schedule) }}">
                                        @csrf
                                        <button class="cb-button cb-button-dark" type="submit">Run Now</button>
                                    </form>
                                    <form method="post" action="{{ route('cloud-backup.schedules.destroy', $schedule) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="cb-button cb-button-danger" type="submit">Remove</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="cb-empty">No backup schedules exist.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="cb-card cb-card-pad">
            <h2 class="cb-h2">Recent backup runs</h2>
            <div class="cb-actions" style="margin: 12px 0 16px;">
                <form
                    id="bulk-delete-backup-runs"
                    method="post"
                    action="{{ route('cloud-backup.runs.destroy-bulk') }}"
                    onsubmit="return confirm('Delete the selected backup run(s) and their uploaded cloud files? This cannot be undone.');"
                >
                    @csrf
                    @method('DELETE')
                </form>
                <button class="cb-button cb-button-danger" type="submit" form="bulk-delete-backup-runs">Delete selected runs</button>
            </div>
            <div class="cb-table-wrap">
                <table class="cb-table">
                    <thead>
                    <tr>
                        <th style="width: 44px;">
                            <input type="checkbox" data-select-backup-runs aria-label="Select all deletable backup runs">
                        </th>
                        <th>Started</th>
                        <th>Backup name</th>
                        <th>Connection</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Uploaded</th>
                        <th>Summary</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($runs as $run)
                        @php
                            $isActiveRun = in_array($run->status, [
                                \ChurchTools\CloudBackup\Models\CloudBackupRun::STATUS_QUEUED,
                                \ChurchTools\CloudBackup\Models\CloudBackupRun::STATUS_RUNNING,
                            ], true);
                        @endphp
                        <tr>
                            <td>
                                <input
                                    type="checkbox"
                                    name="run_ids[]"
                                    value="{{ $run->id }}"
                                    form="bulk-delete-backup-runs"
                                    aria-label="Select backup run {{ $run->backup_name ?: $run->id }}"
                                    @disabled($isActiveRun)
                                >
                            </td>
                            <td>{{ optional($run->started_at)->toDateTimeString() ?: $run->created_at->toDateTimeString() }}</td>
                            <td>{{ $run->backup_name ?: 'Not generated yet' }}</td>
                            <td>{{ $run->connection?->name ?: 'Deleted connection' }}</td>
                            <td>{{ $run->schedule ? 'Scheduled' : 'On-demand' }}</td>
                            <td>{{ ucfirst($run->status) }}</td>
                            <td>{{ (int) ($run->progress_percent ?? 0) }}%</td>
                            <td>{{ number_format(((int) $run->bytes_uploaded) / 1048576, 2) }} MB</td>
                            <td>{{ $run->error_summary ?: 'No errors reported' }}</td>
                            <td>
                                @if ($isActiveRun)
                                    <span class="cb-copy">In progress</span>
                                @else
                                    <form
                                        method="post"
                                        action="{{ route('cloud-backup.runs.destroy', $run) }}"
                                        onsubmit="return confirm('Delete this backup run and its uploaded cloud files? This cannot be undone.');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button class="cb-button cb-button-danger" type="submit">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><div class="cb-empty">No backup runs have been recorded yet.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
        (() => {
            const selectAllRuns = document.querySelector('[data-select-backup-runs]');

            if (! selectAllRuns) {
                return;
            }

            selectAllRuns.addEventListener('change', () => {
                document
                    .querySelectorAll('input[name="run_ids[]"]:not(:disabled)')
                    .forEach((checkbox) => {
                        checkbox.checked = selectAllRuns.checked;
                    });
            });
        })();

        (() => {
            const cards = Array.from(document.querySelectorAll('[data-backup-run]'));

            if (!cards.length) {
                return;
            }

            const updateCard = async (card) => {
                const response = await fetch(card.dataset.progressUrl, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    return false;
                }

                const data = await response.json();
                const percent = Math.max(0, Math.min(100, Number(data.progress_percent || 0)));

                card.dataset.status = data.status || 'running';
                card.querySelector('[data-progress-percent]').textContent = String(percent);
                card.querySelector('[data-progress-fill]').style.width = `${percent}%`;
                card.querySelector('[data-progress-step]').textContent = data.error_summary || data.current_step || data.status || 'Running';
                if (data.backup_name && card.querySelector('[data-backup-name]')) {
                    card.querySelector('[data-backup-name]').textContent = data.backup_name;
                }

                return Boolean(data.finished);
            };

            const timer = window.setInterval(async () => {
                const results = await Promise.all(cards.map((card) => updateCard(card).catch(() => false)));

                if (results.every(Boolean)) {
                    window.clearInterval(timer);
                    window.setTimeout(() => window.location.reload(), 1500);
                }
            }, 2500);
        })();
    </script>
</x-filament-panels::page>
