<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cloud Backups</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; background: #f7f7f8; color: #171717; }
        main { max-width: 1120px; margin: 0 auto; padding: 32px 20px; }
        h1 { font-size: 28px; margin: 0 0 24px; }
        h2 { font-size: 18px; margin: 0 0 16px; }
        section { background: #fff; border: 1px solid #dedede; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 6px; }
        input, select, textarea { width: 100%; box-sizing: border-box; border: 1px solid #c9c9c9; border-radius: 6px; padding: 9px 10px; font: inherit; background: #fff; }
        textarea { min-height: 92px; resize: vertical; }
        button { border: 0; border-radius: 6px; padding: 10px 14px; font: inherit; font-weight: 700; cursor: pointer; background: #135e96; color: #fff; }
        button:disabled { cursor: not-allowed; background: #98a2b3; color: #f8fafc; }
        button.secondary { background: #303030; }
        button.danger { background: #b42318; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e6e6e6; padding: 10px 8px; text-align: left; vertical-align: top; }
        th { font-size: 13px; color: #555; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .notice { background: #ecfdf3; color: #085d3a; border: 1px solid #abefc6; padding: 10px 12px; border-radius: 6px; margin-bottom: 16px; }
        .error { background: #fef3f2; color: #912018; border: 1px solid #fecdca; padding: 10px 12px; border-radius: 6px; margin-bottom: 16px; }
        .warning { background: #fffaeb; color: #93370d; border: 1px solid #fedf89; padding: 10px 12px; border-radius: 6px; margin-bottom: 16px; }
        .info { background: #eff8ff; color: #1849a9; border: 1px solid #b2ddff; padding: 10px 12px; border-radius: 6px; margin-bottom: 16px; }
        .muted { color: #666; }
        .small { font-size: 13px; }
        .links { display: flex; gap: 10px; flex-wrap: wrap; margin: 8px 0 0; }
        .links a { color: #135e96; font-weight: 700; }
        code { background: #f2f4f7; border-radius: 4px; padding: 2px 4px; }
        @media (max-width: 760px) { .grid { grid-template-columns: 1fr; } table { display: block; overflow-x: auto; } }
    </style>
</head>
<body>
<main>
    <h1>Cloud Backups</h1>

    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <section>
        <h2>Cloud OAuth Credentials</h2>
        <div class="info">
            Self-hosted cloud backup integrations need an OAuth app from each provider. Updraft-style one-click setup works by routing
            authorization through a vendor-owned OAuth app/authentication server; this Laravel admin is private to your church, so the secure
            self-hosted setup is to save your own credentials here.
            <div class="links">
                <a href="{{ $oauthLinks['google_drive_api'] }}" target="_blank" rel="noopener">Enable Google Drive API</a>
                <a href="{{ $oauthLinks['google_console'] }}" target="_blank" rel="noopener">Create Google OAuth client</a>
                <a href="{{ $oauthLinks['google_docs'] }}" target="_blank" rel="noopener">Google setup guide</a>
                <a href="{{ $oauthLinks['microsoft_console'] }}" target="_blank" rel="noopener">Create Microsoft app</a>
                <a href="{{ $oauthLinks['microsoft_docs'] }}" target="_blank" rel="noopener">Microsoft app guide</a>
                <a href="{{ $oauthLinks['onedrive_docs'] }}" target="_blank" rel="noopener">OneDrive auth guide</a>
            </div>
        </div>
        <form method="post" action="{{ route('cloud-backup.oauth-settings.store') }}">
            @csrf
            <div class="grid">
                <div>
                    <h3>Google Drive</h3>
                    <p class="small muted">Use a Web application OAuth client and add this authorized redirect URI:</p>
                    <p><code>{{ route('cloud-backup.oauth.callback', ['provider' => 'google']) }}</code></p>
                    <label for="google_client_id">Google client ID</label>
                    <input id="google_client_id" name="google_client_id" value="{{ old('google_client_id', $oauthSettings['google']?->client_id) }}" autocomplete="off">
                    <label for="google_client_secret" style="margin-top:12px;">Google client secret</label>
                    <input id="google_client_secret" name="google_client_secret" type="password" placeholder="{{ filled($oauthSettings['google']?->client_secret) || filled(config('cloud-backup.oauth.google.client_secret')) ? 'Saved; leave blank to keep current secret' : 'Paste Google client secret' }}" autocomplete="new-password">
                    <label for="google_redirect_uri" style="margin-top:12px;">Google redirect URI override</label>
                    <input id="google_redirect_uri" name="google_redirect_uri" value="{{ old('google_redirect_uri', $oauthSettings['google']?->redirect_uri ?: route('cloud-backup.oauth.callback', ['provider' => 'google'])) }}">
                    <label style="margin-top:12px;"><input type="checkbox" name="google_clear_secret" value="1" style="width:auto;"> Clear saved Google secret</label>
                </div>

                <div>
                    <h3>OneDrive</h3>
                    <p class="small muted">Use a Microsoft Entra app registration and add this redirect URI:</p>
                    <p><code>{{ route('cloud-backup.oauth.callback', ['provider' => 'onedrive']) }}</code></p>
                    <label for="onedrive_client_id">OneDrive client ID</label>
                    <input id="onedrive_client_id" name="onedrive_client_id" value="{{ old('onedrive_client_id', $oauthSettings['onedrive']?->client_id) }}" autocomplete="off">
                    <label for="onedrive_client_secret" style="margin-top:12px;">OneDrive client secret</label>
                    <input id="onedrive_client_secret" name="onedrive_client_secret" type="password" placeholder="{{ filled($oauthSettings['onedrive']?->client_secret) || filled(config('cloud-backup.oauth.onedrive.client_secret')) ? 'Saved; leave blank to keep current secret' : 'Paste OneDrive client secret' }}" autocomplete="new-password">
                    <label for="onedrive_tenant" style="margin-top:12px;">Microsoft tenant</label>
                    <input id="onedrive_tenant" name="onedrive_tenant" value="{{ old('onedrive_tenant', $oauthSettings['onedrive']?->tenant ?: config('cloud-backup.oauth.onedrive.tenant', 'common')) }}">
                    <label for="onedrive_redirect_uri" style="margin-top:12px;">OneDrive redirect URI override</label>
                    <input id="onedrive_redirect_uri" name="onedrive_redirect_uri" value="{{ old('onedrive_redirect_uri', $oauthSettings['onedrive']?->redirect_uri ?: route('cloud-backup.oauth.callback', ['provider' => 'onedrive'])) }}">
                    <label style="margin-top:12px;"><input type="checkbox" name="onedrive_clear_secret" value="1" style="width:auto;"> Clear saved OneDrive secret</label>
                </div>
            </div>
            <div class="actions" style="margin-top:16px;">
                <button type="submit">Save OAuth Credentials</button>
            </div>
        </form>
    </section>

    <section>
        <h2>Connect Cloud Storage</h2>
        @if (! ($oauthStatus['google'] ?? false) || ! ($oauthStatus['onedrive'] ?? false))
            <div class="warning">
                Cloud backup OAuth credentials are required before a provider can be connected. Save them above, then connect the storage account.
                @unless ($oauthStatus['google'] ?? false)
                    Google Drive is not configured.
                @endunless
                @unless ($oauthStatus['onedrive'] ?? false)
                    OneDrive is not configured.
                @endunless
            </div>
        @endif
        <div class="grid">
            <form method="post" action="{{ route('cloud-backup.oauth.redirect', ['provider' => 'google']) }}">
                @csrf
                <label for="google_name">Google Drive connection name</label>
                <input id="google_name" name="name" value="Google Drive backup">
                <label for="google_folder" style="margin-top:12px;">Folder path</label>
                <input id="google_folder" name="folder_path" value="LaravelBackups">
                <div class="actions" style="margin-top:14px;">
                    <button type="submit" @disabled(! ($oauthStatus['google'] ?? false))>Connect Google Drive</button>
                </div>
            </form>

            <form method="post" action="{{ route('cloud-backup.oauth.redirect', ['provider' => 'onedrive']) }}">
                @csrf
                <label for="onedrive_name">OneDrive connection name</label>
                <input id="onedrive_name" name="name" value="OneDrive backup">
                <label for="onedrive_folder" style="margin-top:12px;">Folder path</label>
                <input id="onedrive_folder" name="folder_path" value="LaravelBackups">
                <div class="actions" style="margin-top:14px;">
                    <button type="submit" @disabled(! ($oauthStatus['onedrive'] ?? false))>Connect OneDrive</button>
                </div>
            </form>
        </div>
    </section>

    <section>
        <h2>Connections</h2>
        <table>
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
                    <td>{{ $connection->name }}</td>
                    <td>{{ ucfirst($connection->provider) }}</td>
                    <td>{{ $connection->owner_email ?: $connection->owner_name ?: 'Connected' }}</td>
                    <td>{{ $connection->folder_path }}</td>
                    <td>{{ $connection->last_error ? 'Needs attention' : 'Ready' }}</td>
                    <td>
                        <form method="post" action="{{ route('cloud-backup.connections.destroy', $connection) }}">
                            @csrf
                            @method('DELETE')
                            <button class="danger" type="submit">Remove</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No cloud storage is connected.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section>
        <h2>Create Schedule</h2>
        <form method="post" action="{{ route('cloud-backup.schedules.store') }}">
            @csrf
            <div class="grid">
                <div>
                    <label for="connection_id">Connection</label>
                    <select id="connection_id" name="connection_id" required>
                        @foreach ($connections as $connection)
                            <option value="{{ $connection->id }}">{{ $connection->name }} ({{ ucfirst($connection->provider) }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="name">Schedule name</label>
                    <input id="name" name="name" value="Daily site backup" required>
                </div>
                <div>
                    <label for="frequency">Frequency</label>
                    <select id="frequency" name="frequency">
                        <option value="hourly">Hourly</option>
                        <option value="daily" selected>Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div>
                    <label for="retention_count">Files to retain</label>
                    <input id="retention_count" name="retention_count" type="number" min="1" max="100" value="7">
                </div>
                <div>
                    <label for="source_path">Source path</label>
                    <input id="source_path" name="source_path" value="{{ base_path() }}">
                </div>
                <div>
                    <label for="database_connection">Database connection</label>
                    <input id="database_connection" name="database_connection" value="{{ config('database.default') }}">
                </div>
                <div>
                    <label><input type="checkbox" name="include_files" value="1" checked style="width:auto;"> Include files</label>
                    <label><input type="checkbox" name="include_database" value="1" checked style="width:auto;"> Include database</label>
                </div>
                <div>
                    <label for="exclude_paths">Extra excluded paths</label>
                    <textarea id="exclude_paths" name="exclude_paths"></textarea>
                </div>
            </div>
            <div class="actions" style="margin-top:16px;">
                <button type="submit">Create Schedule</button>
            </div>
        </form>
    </section>

    <section>
        <h2>Schedules</h2>
        <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>Connection</th>
                <th>Frequency</th>
                <th>Next Run</th>
                <th>Last Run</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($schedules as $schedule)
                <tr>
                    <td>{{ $schedule->name }}</td>
                    <td>{{ $schedule->connection?->name }}</td>
                    <td>{{ ucfirst($schedule->frequency) }}</td>
                    <td>{{ optional($schedule->next_run_at)->toDateTimeString() ?: 'Due now' }}</td>
                    <td>{{ optional($schedule->last_run_at)->toDateTimeString() ?: 'Never' }}</td>
                    <td>
                        <div class="actions">
                            <form method="post" action="{{ route('cloud-backup.schedules.run', $schedule) }}">
                                @csrf
                                <button class="secondary" type="submit">Run Now</button>
                            </form>
                            <form method="post" action="{{ route('cloud-backup.schedules.destroy', $schedule) }}">
                                @csrf
                                @method('DELETE')
                                <button class="danger" type="submit">Remove</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No backup schedules exist.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
