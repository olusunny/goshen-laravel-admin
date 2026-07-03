<x-filament-panels::page>
    <style>
        .pg-page { --pg-primary:#0c2230; --pg-accent:#f59e0b; --pg-card:#fff; --pg-soft:#f8fafc; --pg-line:#e5e7eb; --pg-muted:#667085; --pg-text:#111827; --pg-shadow:0 16px 40px rgba(15,23,42,.08); display:grid; gap:24px; color:var(--pg-text); }
        .dark .pg-page { --pg-card:#111827; --pg-soft:rgba(15,23,42,.56); --pg-line:rgba(148,163,184,.2); --pg-muted:#a8b0bd; --pg-text:#f8fafc; --pg-shadow:0 18px 46px rgba(0,0,0,.28); }
        .pg-card { background:var(--pg-card); border:1px solid var(--pg-line); border-radius:22px; box-shadow:var(--pg-shadow); overflow:hidden; }
        .pg-pad { padding:24px; }
        .pg-hero { position:relative; isolation:isolate; color:#fff; background:radial-gradient(circle at 88% 12%, rgba(245,158,11,.28), transparent 30%), linear-gradient(135deg,#0c2230,#12384a 55%,#0f513c); border:0; }
        .pg-hero:after { content:""; position:absolute; right:-90px; bottom:-150px; width:320px; height:320px; border:1px solid rgba(255,255,255,.16); border-radius:999px; z-index:-1; }
        .pg-eyebrow { margin:0 0 8px; color:#facc15; font-size:12px; font-weight:900; letter-spacing:.13em; text-transform:uppercase; }
        .pg-title { margin:0; font-size:clamp(26px,3vw,38px); line-height:1.08; font-weight:950; letter-spacing:-.03em; }
        .pg-copy { margin:10px 0 0; max-width:760px; color:rgba(255,255,255,.82); font-size:15px; line-height:1.65; }
        .pg-section-head { display:flex; justify-content:space-between; gap:18px; align-items:flex-start; padding-bottom:18px; border-bottom:1px solid var(--pg-line); }
        .pg-h2 { margin:0; font-size:20px; font-weight:900; line-height:1.2; }
        .pg-muted { margin:6px 0 0; color:var(--pg-muted); font-size:14px; line-height:1.6; }
        .pg-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; }
        .pg-grid-3 { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
        .pg-mode { display:block; cursor:pointer; }
        .pg-mode input { position:absolute; opacity:0; pointer-events:none; }
        .pg-mode-card { min-height:118px; padding:18px; border:1px solid var(--pg-line); border-radius:18px; background:var(--pg-soft); transition:.18s ease; }
        .pg-mode input:checked + .pg-mode-card { border-color:var(--pg-accent); box-shadow:0 0 0 3px rgba(245,158,11,.15), var(--pg-shadow); background:linear-gradient(135deg,rgba(245,158,11,.12),rgba(12,34,48,.03)); }
        .dark .pg-mode input:checked + .pg-mode-card { background:linear-gradient(135deg,rgba(245,158,11,.14),rgba(15,81,60,.12)); }
        .pg-mode-title { display:flex; align-items:center; justify-content:space-between; gap:12px; font-size:18px; font-weight:950; }
        .pg-badge { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; font-size:11px; font-weight:900; white-space:nowrap; }
        .pg-badge-ok { background:#dcfae6; color:#067647; }
        .pg-badge-warn { background:#fef0c7; color:#b54708; }
        .dark .pg-badge-ok { background:rgba(22,163,74,.18); color:#86efac; }
        .dark .pg-badge-warn { background:rgba(245,158,11,.18); color:#fcd34d; }
        .pg-field { display:grid; gap:7px; }
        .pg-label { color:var(--pg-text); font-size:13px; font-weight:850; }
        .pg-input { width:100%; min-height:46px; box-sizing:border-box; border:1px solid #d0d5dd; border-radius:13px; padding:10px 12px; background:#fff; color:#111827; font:inherit; outline:none; transition:.18s ease; }
        .pg-input:focus { border-color:var(--pg-accent); box-shadow:0 0 0 3px rgba(245,158,11,.15); }
        .dark .pg-input { background:#0b1220; border-color:rgba(148,163,184,.32); color:#f8fafc; }
        .pg-help { color:var(--pg-muted); font-size:12px; line-height:1.45; }
        .pg-code { display:block; margin-top:6px; overflow-wrap:anywhere; border:1px solid var(--pg-line); border-radius:12px; padding:10px 12px; background:rgba(12,34,48,.04); color:var(--pg-text); font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace; font-size:12px; }
        .dark .pg-code { background:rgba(255,255,255,.04); }
        .pg-panel { padding:18px; border:1px solid var(--pg-line); border-radius:18px; background:var(--pg-soft); }
        .pg-panel-title { margin:0 0 14px; font-size:16px; font-weight:950; }
        .pg-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:20px; }
        .pg-button { display:inline-flex; align-items:center; justify-content:center; min-height:44px; border:0; border-radius:13px; padding:10px 16px; background:var(--pg-accent); color:#111827; font:inherit; font-size:14px; font-weight:950; cursor:pointer; box-shadow:0 10px 22px rgba(245,158,11,.22); }
        .pg-button-dark { background:var(--pg-primary); color:#fff; box-shadow:0 10px 22px rgba(12,34,48,.16); }
        .pg-button-danger { background:#dc2626; color:#fff; box-shadow:none; }
        .pg-note { padding:14px 16px; border-radius:16px; background:#eff6ff; border:1px solid #bfdbfe; color:#155eef; font-size:13px; font-weight:750; line-height:1.55; }
        .dark .pg-note { background:rgba(37,99,235,.13); border-color:rgba(147,197,253,.22); color:#93c5fd; }
        @media (max-width:900px) { .pg-grid, .pg-grid-3 { grid-template-columns:1fr; } .pg-section-head { flex-direction:column; } .pg-pad { padding:18px; } }
    </style>

    <div class="pg-page">
        <section class="pg-card pg-pad pg-hero">
            <p class="pg-eyebrow">Secure payments</p>
            <h2 class="pg-title">Stripe gateway settings</h2>
            <p class="pg-copy">
                Configure Stripe test or live mode for Giving and Goshen Retreat checkout. Secret keys are encrypted before storage and never shown again after saving.
            </p>
        </section>

        <form wire:submit.prevent="save" class="pg-card pg-pad">
            <div class="pg-section-head">
                <div>
                    <h2 class="pg-h2">Active payment mode</h2>
                    <p class="pg-muted">Use test mode while verifying checkout. Switch to live only when your Stripe account and webhooks are ready.</p>
                </div>
                <button type="button" wire:click="testConnection" class="pg-button pg-button-dark">Test active Stripe connection</button>
            </div>

            <div class="pg-grid" style="margin-top:18px;">
                <label class="pg-mode">
                    <input type="radio" wire:model="mode" value="test">
                    <span class="pg-mode-card">
                        <span class="pg-mode-title">Test mode <span class="pg-badge {{ str_contains($this->stripeStatus('test'), 'saved') ? 'pg-badge-ok' : 'pg-badge-warn' }}">{{ $this->stripeStatus('test') }}</span></span>
                        <span class="pg-muted">Use Stripe `sk_test_...` keys for safe test payments.</span>
                    </span>
                </label>
                <label class="pg-mode">
                    <input type="radio" wire:model="mode" value="live">
                    <span class="pg-mode-card">
                        <span class="pg-mode-title">Live mode <span class="pg-badge {{ str_contains($this->stripeStatus('live'), 'saved') ? 'pg-badge-ok' : 'pg-badge-warn' }}">{{ $this->stripeStatus('live') }}</span></span>
                        <span class="pg-muted">Use Stripe `sk_live_...` keys only when production payments should be accepted.</span>
                    </span>
                </label>
            </div>

            <div class="pg-grid" style="margin-top:18px;">
                <section class="pg-panel">
                    <h3 class="pg-panel-title">Test credentials</h3>
                    <div class="pg-field">
                        <label class="pg-label">Test publishable key</label>
                        <input class="pg-input" wire:model.defer="testPublishableKey" placeholder="pk_test_...">
                    </div>
                    <div class="pg-field" style="margin-top:12px;">
                        <label class="pg-label">Test secret key</label>
                        <input type="password" class="pg-input" wire:model.defer="testSecretKey" placeholder="Leave blank to keep saved secret">
                    </div>
                    <div class="pg-field" style="margin-top:12px;">
                        <label class="pg-label">Test Giving webhook signing secret</label>
                        <span class="pg-badge {{ str_contains($this->webhookStatus('test', 'giving'), 'saved') ? 'pg-badge-ok' : 'pg-badge-warn' }}">{{ $this->webhookStatus('test', 'giving') }}</span>
                        <code class="pg-code">{{ $this->givingWebhookEndpoint() }}</code>
                        <input type="password" class="pg-input" wire:model.defer="testGivingWebhookSecret" placeholder="whsec_..." style="margin-top:10px;">
                    </div>
                    <div class="pg-field" style="margin-top:12px;">
                        <label class="pg-label">Test Goshen Retreat webhook signing secret</label>
                        <span class="pg-badge {{ str_contains($this->webhookStatus('test', 'event'), 'saved') ? 'pg-badge-ok' : 'pg-badge-warn' }}">{{ $this->webhookStatus('test', 'event') }}</span>
                        <code class="pg-code">{{ $this->eventWebhookEndpoint() }}</code>
                        <input type="password" class="pg-input" wire:model.defer="testEventWebhookSecret" placeholder="whsec_..." style="margin-top:10px;">
                    </div>
                    <div class="pg-field" style="margin-top:12px;">
                        <label class="pg-label">Test Goshen Wallet webhook signing secret</label>
                        <span class="pg-badge {{ str_contains($this->webhookStatus('test', 'wallet'), 'saved') ? 'pg-badge-ok' : 'pg-badge-warn' }}">{{ $this->webhookStatus('test', 'wallet') }}</span>
                        <code class="pg-code">{{ $this->walletWebhookEndpoint() }}</code>
                        <input type="password" class="pg-input" wire:model.defer="testWalletWebhookSecret" placeholder="whsec_..." style="margin-top:10px;">
                    </div>
                    <div class="pg-actions">
                        <button type="button" wire:click="resetTestCredentials" class="pg-button pg-button-danger">Reset test keys</button>
                    </div>
                </section>

                <section class="pg-panel">
                    <h3 class="pg-panel-title">Live credentials</h3>
                    <div class="pg-field">
                        <label class="pg-label">Live publishable key</label>
                        <input class="pg-input" wire:model.defer="livePublishableKey" placeholder="pk_live_...">
                    </div>
                    <div class="pg-field" style="margin-top:12px;">
                        <label class="pg-label">Live secret key</label>
                        <input type="password" class="pg-input" wire:model.defer="liveSecretKey" placeholder="Leave blank to keep saved secret">
                    </div>
                    <div class="pg-field" style="margin-top:12px;">
                        <label class="pg-label">Live Giving webhook signing secret</label>
                        <span class="pg-badge {{ str_contains($this->webhookStatus('live', 'giving'), 'saved') ? 'pg-badge-ok' : 'pg-badge-warn' }}">{{ $this->webhookStatus('live', 'giving') }}</span>
                        <code class="pg-code">{{ $this->givingWebhookEndpoint() }}</code>
                        <input type="password" class="pg-input" wire:model.defer="liveGivingWebhookSecret" placeholder="whsec_..." style="margin-top:10px;">
                    </div>
                    <div class="pg-field" style="margin-top:12px;">
                        <label class="pg-label">Live Goshen Retreat webhook signing secret</label>
                        <span class="pg-badge {{ str_contains($this->webhookStatus('live', 'event'), 'saved') ? 'pg-badge-ok' : 'pg-badge-warn' }}">{{ $this->webhookStatus('live', 'event') }}</span>
                        <code class="pg-code">{{ $this->eventWebhookEndpoint() }}</code>
                        <input type="password" class="pg-input" wire:model.defer="liveEventWebhookSecret" placeholder="whsec_..." style="margin-top:10px;">
                    </div>
                    <div class="pg-field" style="margin-top:12px;">
                        <label class="pg-label">Live Goshen Wallet webhook signing secret</label>
                        <span class="pg-badge {{ str_contains($this->webhookStatus('live', 'wallet'), 'saved') ? 'pg-badge-ok' : 'pg-badge-warn' }}">{{ $this->webhookStatus('live', 'wallet') }}</span>
                        <code class="pg-code">{{ $this->walletWebhookEndpoint() }}</code>
                        <input type="password" class="pg-input" wire:model.defer="liveWalletWebhookSecret" placeholder="whsec_..." style="margin-top:10px;">
                    </div>
                    <div class="pg-actions">
                        <button type="button" wire:click="resetLiveCredentials" class="pg-button pg-button-danger">Reset live keys</button>
                    </div>
                </section>
            </div>

            <section class="pg-panel" style="margin-top:18px;">
                <h3 class="pg-panel-title">Checkout return URLs</h3>
                <div class="pg-grid">
                    <div class="pg-field">
                        <label class="pg-label">Giving success URL</label>
                        <input class="pg-input" wire:model.defer="givingSuccessUrl">
                    </div>
                    <div class="pg-field">
                        <label class="pg-label">Giving cancel URL</label>
                        <input class="pg-input" wire:model.defer="givingCancelUrl">
                    </div>
                    <div class="pg-field">
                        <label class="pg-label">Goshen event success URL</label>
                        <input class="pg-input" wire:model.defer="eventSuccessUrl">
                    </div>
                    <div class="pg-field">
                        <label class="pg-label">Goshen event cancel URL</label>
                        <input class="pg-input" wire:model.defer="eventCancelUrl">
                    </div>
                    <div class="pg-field">
                        <label class="pg-label">Goshen wallet success URL</label>
                        <input class="pg-input" wire:model.defer="walletSuccessUrl">
                    </div>
                    <div class="pg-field">
                        <label class="pg-label">Goshen wallet cancel URL</label>
                        <input class="pg-input" wire:model.defer="walletCancelUrl">
                    </div>
                    <div class="pg-field">
                        <label class="pg-label">Stripe API version</label>
                        <input class="pg-input" wire:model.defer="apiVersion">
                    </div>
                </div>
            </section>

            <div class="pg-actions">
                <button type="submit" class="pg-button">Save Stripe settings</button>
            </div>
        </form>

        <section class="pg-note">
            Create Stripe test keys from your Stripe dashboard, paste the `pk_test_...`, `sk_test_...`, and each endpoint's webhook `whsec_...` value here, then click “Test active Stripe connection”. Stripe creates a different signing secret for each webhook endpoint, so Giving, Goshen Retreat, and Goshen Wallet need their own webhook secrets.
        </section>
    </div>
</x-filament-panels::page>
