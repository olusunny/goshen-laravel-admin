<x-filament-panels::page>
    <style>
        .gfs-page { --gfs-primary:#0c2230; --gfs-accent:#f59e0b; --gfs-card:#fff; --gfs-soft:#f8fafc; --gfs-line:#e5e7eb; --gfs-muted:#667085; --gfs-text:#111827; --gfs-shadow:0 16px 40px rgba(15,23,42,.08); display:grid; gap:24px; color:var(--gfs-text); }
        .dark .gfs-page { --gfs-card:#111827; --gfs-soft:rgba(15,23,42,.56); --gfs-line:rgba(148,163,184,.2); --gfs-muted:#a8b0bd; --gfs-text:#f8fafc; --gfs-shadow:0 18px 46px rgba(0,0,0,.28); }
        .gfs-card { background:var(--gfs-card); border:1px solid var(--gfs-line); border-radius:22px; box-shadow:var(--gfs-shadow); overflow:hidden; }
        .gfs-pad { padding:24px; }
        .gfs-hero { position:relative; isolation:isolate; color:#fff; background:radial-gradient(circle at 88% 12%, rgba(245,158,11,.28), transparent 30%), linear-gradient(135deg,#0c2230,#12384a 55%,#0f513c); border:0; }
        .gfs-eyebrow { margin:0 0 8px; color:#facc15; font-size:12px; font-weight:900; letter-spacing:.13em; text-transform:uppercase; }
        .gfs-title { margin:0; font-size:clamp(26px,3vw,38px); line-height:1.08; font-weight:950; letter-spacing:-.03em; }
        .gfs-copy { margin:10px 0 0; max-width:760px; color:rgba(255,255,255,.82); font-size:15px; line-height:1.65; }
        .gfs-h2 { margin:0; font-size:20px; font-weight:900; line-height:1.2; }
        .gfs-muted { margin:6px 0 0; color:var(--gfs-muted); font-size:14px; line-height:1.6; }
        .gfs-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; }
        .gfs-field { display:grid; gap:7px; }
        .gfs-label { color:var(--gfs-text); font-size:13px; font-weight:850; }
        .gfs-input { width:100%; min-height:46px; box-sizing:border-box; border:1px solid #d0d5dd; border-radius:13px; padding:10px 12px; background:#fff; color:#111827; font:inherit; outline:none; transition:.18s ease; }
        .gfs-input:focus { border-color:var(--gfs-accent); box-shadow:0 0 0 3px rgba(245,158,11,.15); }
        .dark .gfs-input { background:#0b1220; border-color:rgba(148,163,184,.32); color:#f8fafc; }
        .gfs-help { color:var(--gfs-muted); font-size:12px; line-height:1.45; }
        .gfs-panel { padding:18px; border:1px solid var(--gfs-line); border-radius:18px; background:var(--gfs-soft); }
        .gfs-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:20px; }
        .gfs-button { display:inline-flex; align-items:center; justify-content:center; min-height:44px; border:0; border-radius:13px; padding:10px 16px; background:var(--gfs-accent); color:#111827; font:inherit; font-size:14px; font-weight:950; cursor:pointer; box-shadow:0 10px 22px rgba(245,158,11,.22); }
        .gfs-check { display:flex; gap:10px; align-items:center; min-height:46px; padding:10px 12px; border:1px solid var(--gfs-line); border-radius:13px; background:var(--gfs-soft); font-weight:800; }
        .gfs-code { display:block; margin-top:8px; overflow-wrap:anywhere; border:1px solid var(--gfs-line); border-radius:12px; padding:10px 12px; background:rgba(12,34,48,.04); color:var(--gfs-text); font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace; font-size:12px; }
        .dark .gfs-code { background:rgba(255,255,255,.04); }
        .gfs-status { display:inline-flex; align-items:center; gap:8px; width:max-content; max-width:100%; margin-top:12px; border-radius:999px; padding:8px 12px; font-size:12px; font-weight:950; }
        .gfs-status-ok { background:#dcfce7; color:#166534; border:1px solid #86efac; }
        .gfs-status-warn { background:#fff7ed; color:#9a3412; border:1px solid #fed7aa; }
        .dark .gfs-status-ok { background:rgba(22,101,52,.18); color:#86efac; border-color:rgba(134,239,172,.28); }
        .dark .gfs-status-warn { background:rgba(154,52,18,.2); color:#fed7aa; border-color:rgba(254,215,170,.24); }
        .gfs-note { padding:14px 16px; border-radius:16px; background:#eff6ff; border:1px solid #bfdbfe; color:#155eef; font-size:13px; font-weight:750; line-height:1.55; }
        .dark .gfs-note { background:rgba(37,99,235,.13); border-color:rgba(147,197,253,.22); color:#93c5fd; }
        @media (max-width:900px) { .gfs-grid { grid-template-columns:1fr; } .gfs-pad { padding:18px; } }
    </style>

    <div class="gfs-page">
        <section class="gfs-card gfs-pad gfs-hero">
            <p class="gfs-eyebrow">Mobile authentication</p>
            <h2 class="gfs-title">Google & Firebase settings</h2>
            <p class="gfs-copy">
                Configure the Google OAuth client IDs used by Flutter Google sign-in. Firebase Admin credentials for backend push notifications are shown below from the server environment.
            </p>
        </section>

        <form wire:submit.prevent="save" class="gfs-card gfs-pad">
            <div>
                <h2 class="gfs-h2">Google sign-in</h2>
                <p class="gfs-muted">These values are returned to the Flutter app through the backend discover endpoint and are used when the app requests a Google identity token.</p>
            </div>

            <div class="gfs-grid" style="margin-top:18px;">
                <label class="gfs-field">
                    <span class="gfs-label">Enable Google login</span>
                    <span class="gfs-check">
                        <input type="checkbox" wire:model.defer="googleLoginEnabled">
                        <span>Show Google login/register buttons in the Flutter app</span>
                    </span>
                </label>

                <label class="gfs-field">
                    <span class="gfs-label">Android package name</span>
                    <input class="gfs-input" value="{{ $androidPackageName }}" readonly>
                    <span class="gfs-help">Use this package name in Firebase/Google Cloud Android app settings.</span>
                </label>

                <label class="gfs-field">
                    <span class="gfs-label">Google Web client ID</span>
                    <input class="gfs-input" wire:model.defer="googleWebClientId" placeholder="000000000000-xxxxx.apps.googleusercontent.com">
                    <span class="gfs-help">Required. Flutter uses this as serverClientId so Google returns an ID token.</span>
                </label>

                <label class="gfs-field">
                    <span class="gfs-label">Google Android client ID</span>
                    <input class="gfs-input" wire:model.defer="googleAndroidClientId" placeholder="000000000000-xxxxx.apps.googleusercontent.com">
                    <span class="gfs-help">OAuth client for package {{ $androidPackageName }} and the app signing certificate.</span>
                </label>

                <label class="gfs-field">
                    <span class="gfs-label">Google iOS client ID</span>
                    <input class="gfs-input" wire:model.defer="googleIosClientId" placeholder="Optional for iOS">
                    <span class="gfs-help">Only needed when the iOS app is configured.</span>
                </label>

                <label class="gfs-field">
                    <span class="gfs-label">Google client secret</span>
                    <input type="password" class="gfs-input" wire:model.defer="googleClientSecret" placeholder="Leave blank to keep existing secret">
                    <span class="gfs-help">Optional. The current mobile login flow does not require exposing this to the app.</span>
                </label>
            </div>

            <section class="gfs-panel" style="margin-top:18px;">
                <h3 class="gfs-h2" style="font-size:16px;">Fingerprints to add in Firebase</h3>
                <p class="gfs-muted">Add these debug fingerprints for local APK testing. Add your Play App Signing fingerprints separately for production.</p>
                <code class="gfs-code">SHA-1: {{ $debugSha1 }}</code>
                <code class="gfs-code">SHA-256: {{ $debugSha256 }}</code>
            </section>

            <div class="gfs-actions">
                <button type="submit" class="gfs-button">Save Google settings</button>
            </div>
        </form>

        <section class="gfs-card gfs-pad">
            @php
                $firebaseAdminOk = $firebaseCredentialsFileExists && $firebaseCredentialsMatchesMobileProject && $firebaseStorageMatchesMobileProject;
            @endphp

            <h2 class="gfs-h2">Firebase Admin credentials</h2>
            <p class="gfs-muted">Backend push notifications use Firebase Admin SDK credentials from server environment variables. These are not the same as the mobile app's google-services.json.</p>
            <span class="gfs-status {{ $firebaseAdminOk ? 'gfs-status-ok' : 'gfs-status-warn' }}">
                {{ $firebaseAdminOk ? 'Connected to current Firebase project' : 'Firebase Admin project needs attention' }}
            </span>

            <div class="gfs-grid" style="margin-top:18px;">
                <div class="gfs-field">
                    <span class="gfs-label">Expected mobile Firebase project</span>
                    <code class="gfs-code">{{ $mobileFirebaseProjectId }}</code>
                </div>
                <div class="gfs-field">
                    <span class="gfs-label">Expected storage bucket</span>
                    <code class="gfs-code">{{ $mobileFirebaseStorageBucket }}</code>
                </div>
                <div class="gfs-field">
                    <span class="gfs-label">FIREBASE_CREDENTIALS</span>
                    <code class="gfs-code">{{ $firebaseCredentialsPath !== '' ? $firebaseCredentialsPath : 'Not configured' }}</code>
                </div>
                <div class="gfs-field">
                    <span class="gfs-label">GOOGLE_APPLICATION_CREDENTIALS</span>
                    <code class="gfs-code">{{ $googleApplicationCredentialsPath !== '' ? $googleApplicationCredentialsPath : 'Not configured' }}</code>
                </div>
                <div class="gfs-field">
                    <span class="gfs-label">Firebase storage bucket</span>
                    <code class="gfs-code">{{ $firebaseStorageBucket !== '' ? $firebaseStorageBucket : 'Not configured' }}</code>
                </div>
                <div class="gfs-field">
                    <span class="gfs-label">Credential file status</span>
                    <code class="gfs-code">{{ $firebaseCredentialsFileExists ? 'Found' : 'Missing' }}</code>
                </div>
                <div class="gfs-field">
                    <span class="gfs-label">Credential project ID</span>
                    <code class="gfs-code">{{ $firebaseCredentialsProjectId !== '' ? $firebaseCredentialsProjectId : 'Unavailable' }}</code>
                </div>
                <div class="gfs-field">
                    <span class="gfs-label">Credential service account</span>
                    <code class="gfs-code">{{ $firebaseCredentialsClientEmail !== '' ? $firebaseCredentialsClientEmail : 'Unavailable' }}</code>
                </div>
            </div>
        </section>

        <section class="gfs-note">
            After saving, clear the app cache if needed, then restart the Flutter app. If Firebase Console generated a new google-services.json after adding SHA fingerprints, replace the Flutter file and rebuild the APK.
        </section>
    </div>
</x-filament-panels::page>
