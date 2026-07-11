<x-filament-panels::page>
    <style>
        .ash-page { --ash-primary:#0f3f35; --ash-accent:#f59e0b; --ash-card:#fff; --ash-soft:#f8fafc; --ash-line:#e5e7eb; --ash-muted:#667085; --ash-text:#111827; display:grid; gap:24px; color:var(--ash-text); }
        .dark .ash-page { --ash-card:#111827; --ash-soft:rgba(15,23,42,.56); --ash-line:rgba(148,163,184,.2); --ash-muted:#a8b0bd; --ash-text:#f8fafc; }
        .ash-hero { padding:26px; border-radius:24px; color:#fff; background:radial-gradient(circle at 86% 12%, rgba(245,158,11,.25), transparent 30%), linear-gradient(135deg,#0c2230,#0f513c); overflow:hidden; }
        .ash-title { margin:0; font-size:clamp(28px,3vw,40px); font-weight:950; line-height:1.05; letter-spacing:-.02em; }
        .ash-copy { margin:10px 0 0; max-width:760px; color:rgba(255,255,255,.82); font-size:15px; line-height:1.65; }
        .ash-layout { display:grid; grid-template-columns:minmax(260px,.34fr) minmax(0,1fr); gap:32px; align-items:start; }
        .ash-tabs { display:grid; gap:14px; padding-right:28px; border-right:1px solid var(--ash-line); }
        .ash-tab { display:flex; align-items:center; gap:14px; min-height:76px; width:100%; padding:12px 14px; border:1px solid var(--ash-line); border-radius:8px; background:var(--ash-card); color:#6b7280; text-align:left; font:inherit; cursor:pointer; }
        .ash-tab-icon { display:grid; place-items:center; width:52px; height:52px; border-radius:8px; background:#f3f4f6; color:#6b7280; flex:none; }
        .dark .ash-tab-icon { background:rgba(255,255,255,.06); }
        .ash-tab-title { display:block; font-size:18px; font-weight:900; line-height:1.2; }
        .ash-tab-note { display:block; margin-top:3px; color:var(--ash-muted); font-size:12px; font-weight:700; line-height:1.35; }
        .ash-tab.active { background:#e7f4ef; color:#047857; border-color:#cceadd; box-shadow:inset 4px 0 0 #10b981; }
        .dark .ash-tab.active { background:rgba(16,185,129,.18); color:#6ee7b7; border-color:rgba(110,231,183,.22); }
        .ash-tab.active .ash-tab-icon { background:#fff; color:#047857; }
        .dark .ash-tab.active .ash-tab-icon { background:rgba(255,255,255,.08); color:#6ee7b7; }
        .ash-panel { border:1px solid var(--ash-line); border-radius:24px; background:var(--ash-card); box-shadow:0 16px 40px rgba(15,23,42,.08); overflow:hidden; }
        .dark .ash-panel { box-shadow:0 18px 46px rgba(0,0,0,.28); }
        .ash-panel-head { padding:24px; border-bottom:1px solid var(--ash-line); }
        .ash-h2 { margin:0; font-size:24px; font-weight:950; line-height:1.15; }
        .ash-muted { margin:7px 0 0; color:var(--ash-muted); font-size:14px; line-height:1.55; }
        .ash-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; padding:24px; }
        .ash-field { display:grid; gap:7px; }
        .ash-field-full { grid-column:1 / -1; }
        .ash-label { color:var(--ash-text); font-size:13px; font-weight:850; }
        .ash-input, .ash-textarea { width:100%; box-sizing:border-box; border:1px solid #d0d5dd; border-radius:13px; padding:10px 12px; background:#fff; color:#111827; font:inherit; outline:none; transition:.18s ease; }
        .ash-input { min-height:46px; }
        .ash-textarea { min-height:120px; resize:vertical; }
        .ash-input:focus, .ash-textarea:focus { border-color:var(--ash-accent); box-shadow:0 0 0 3px rgba(245,158,11,.15); }
        .dark .ash-input, .dark .ash-textarea { background:#0b1220; border-color:rgba(148,163,184,.32); color:#f8fafc; }
        .ash-checks { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; padding:24px; }
        .ash-check { display:flex; gap:12px; align-items:flex-start; min-height:78px; padding:14px; border:1px solid var(--ash-line); border-radius:15px; background:var(--ash-soft); }
        .ash-check input { margin-top:4px; width:18px; height:18px; accent-color:var(--ash-accent); }
        .ash-check strong { display:block; color:var(--ash-text); font-size:14px; font-weight:900; }
        .ash-check span { display:block; margin-top:3px; color:var(--ash-muted); font-size:12px; line-height:1.4; }
        .ash-links { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; padding:24px; }
        .ash-link { display:flex; justify-content:space-between; gap:16px; min-height:112px; padding:18px; border:1px solid var(--ash-line); border-radius:18px; background:var(--ash-soft); text-decoration:none; color:var(--ash-text); transition:.18s ease; }
        .ash-link:hover { border-color:#fbbf24; transform:translateY(-1px); }
        .ash-link h3 { margin:0; font-size:18px; font-weight:950; }
        .ash-link p { margin:7px 0 0; color:var(--ash-muted); font-size:13px; line-height:1.45; }
        .ash-actions { display:flex; justify-content:flex-end; gap:12px; padding:20px 24px; border-top:1px solid var(--ash-line); background:var(--ash-soft); }
        .ash-button { display:inline-flex; align-items:center; justify-content:center; min-height:44px; border:0; border-radius:13px; padding:10px 18px; background:var(--ash-accent); color:#111827; font:inherit; font-size:14px; font-weight:950; cursor:pointer; box-shadow:0 10px 22px rgba(245,158,11,.22); }
        @media (max-width:980px) { .ash-layout { grid-template-columns:1fr; } .ash-tabs { padding-right:0; border-right:0; } .ash-grid, .ash-checks, .ash-links { grid-template-columns:1fr; padding:18px; } .ash-panel-head, .ash-actions { padding:18px; } }
    </style>

    <form wire:submit.prevent="save" class="ash-page" x-data="{ tab: 'general' }">
        <section class="ash-hero">
            <h2 class="ash-title">App settings</h2>
            <p class="ash-copy">Manage the public app, social links, feature switches, support details, and integration shortcuts from one focused settings page.</p>
        </section>

        <div class="ash-layout">
            <aside class="ash-tabs" aria-label="Settings sections">
                @foreach ([
                    'general' => ['General', 'Name, website, currency', 'heroicon-o-cog-6-tooth'],
                    'branding' => ['Branding', 'Logo and app identity', 'heroicon-o-photo'],
                    'social' => ['Social links', 'Public contact channels', 'heroicon-o-share'],
                    'features' => ['Activation', 'Mobile app feature switches', 'heroicon-o-adjustments-horizontal'],
                    'payments' => ['Giving & payments', 'PayPal and Stripe setup', 'heroicon-o-credit-card'],
                    'support' => ['Support', 'Accommodation support contact', 'heroicon-o-lifebuoy'],
                    'integrations' => ['Integrations', 'Gateways and credentials', 'heroicon-o-squares-plus'],
                    'other' => ['Other settings', 'Additional app settings', 'heroicon-o-ellipsis-horizontal-circle'],
                ] as $key => [$label, $note, $icon])
                    <button type="button" class="ash-tab" :class="{ 'active': tab === '{{ $key }}' }" x-on:click="tab = '{{ $key }}'">
                        <span class="ash-tab-icon"><x-filament::icon :icon="$icon" /></span>
                        <span>
                            <span class="ash-tab-title">{{ $label }}</span>
                            <span class="ash-tab-note">{{ $note }}</span>
                        </span>
                    </button>
                @endforeach
            </aside>

            <div>
                <section class="ash-panel" x-show="tab === 'general'">
                    <div class="ash-panel-head">
                        <h2 class="ash-h2">General</h2>
                        <p class="ash-muted">Core app values used by public APIs and mobile screens.</p>
                    </div>
                    <div class="ash-grid">
                        <label class="ash-field ash-field-full">
                            <span class="ash-label">Website URL</span>
                            <input class="ash-input" wire:model.defer="websiteUrl" placeholder="https://example.com">
                        </label>
                        <label class="ash-field">
                            <span class="ash-label">Currency code or symbol</span>
                            <input class="ash-input" wire:model.defer="currency" maxlength="12" placeholder="£ or GBP">
                        </label>
                        <label class="ash-field">
                            <span class="ash-label">Ads interval seconds</span>
                            <input class="ash-input" type="number" min="0" wire:model.defer="adsInterval">
                        </label>
                    </div>
                </section>

                <section class="ash-panel" x-show="tab === 'branding'" x-cloak>
                    <div class="ash-panel-head">
                        <h2 class="ash-h2">Branding</h2>
                        <p class="ash-muted">App identity values used by the mobile and web experience. Use a storage path for the logo, or upload through media tools and paste the stored path here.</p>
                    </div>
                    <div class="ash-grid">
                        <label class="ash-field">
                            <span class="ash-label">App name</span>
                            <input class="ash-input" wire:model.defer="appName" placeholder="MFM Triumphant Church">
                        </label>
                        <label class="ash-field">
                            <span class="ash-label">App logo path</span>
                            <input class="ash-input" wire:model.defer="appLogo" placeholder="branding/logo.png">
                        </label>
                    </div>
                </section>

                <section class="ash-panel" x-show="tab === 'social'" x-cloak>
                    <div class="ash-panel-head">
                        <h2 class="ash-h2">Social Links</h2>
                        <p class="ash-muted">Links returned to the apps for social and media destinations.</p>
                    </div>
                    <div class="ash-grid">
                        @foreach ([
                            'facebookPage' => 'Facebook URL',
                            'youtubePage' => 'YouTube URL',
                            'tiktokPage' => 'TikTok URL',
                            'instagramPage' => 'Instagram URL',
                            'telegramPage' => 'Telegram URL',
                            'mixlrPage' => 'Mixlr URL',
                            'whatsappPage' => 'WhatsApp URL',
                            'twitterPage' => 'X/Twitter URL',
                        ] as $model => $label)
                            <label class="ash-field">
                                <span class="ash-label">{{ $label }}</span>
                                <input class="ash-input" wire:model.defer="{{ $model }}" placeholder="https://">
                            </label>
                        @endforeach
                    </div>
                </section>

                <section class="ash-panel" x-show="tab === 'features'" x-cloak>
                    <div class="ash-panel-head">
                        <h2 class="ash-h2">Activation</h2>
                        <p class="ash-muted">Turn major mobile and web app features on or off without redeploying the app.</p>
                    </div>
                    <div class="ash-checks">
                        @foreach ([
                            'googleLoginEnabled' => ['Google login', 'Show Google sign-in and registration buttons.'],
                            'testimoniesEnabled' => ['Testimonies & Thanksgiving', 'Enable the public testimony wall.'],
                            'goshenRetreatEnabled' => ['Goshen Retreat', 'Show Goshen Retreat in the app and web experience.'],
                            'goshenScannerEnabled' => ['Goshen scanner', 'Allow authorized check-in scanner access.'],
                            'goshenWalletEnabled' => ['Goshen wallet', 'Enable wallet balance, transfers, and wallet payments.'],
                            'goshenStripeGivingEnabled' => ['Stripe giving', 'Allow Giving payments through Stripe.'],
                            'goshenReferralsEnabled' => ['Goshen referrals', 'Allow referral codes and wallet conversion.'],
                            'fundraisingEnabled' => ['Project support', 'Show fundraising/project support campaigns.'],
                            'prayerPointsEnabled' => ['Prayer points', 'Show church prayer points content.'],
                            'interactivePrayerWallEnabled' => ['Interactive prayer wall', 'Allow prayer wall reading, posting, and responses.'],
                            'hymnsEnabled' => ['Hymns', 'Show hymns in the mobile app.'],
                            'devotionalsEnabled' => ['Devotional', 'Show devotional content and devotional notifications.'],
                            'verseOfDayEnabled' => ['Verse of the day', 'Show the daily Bible verse card.'],
                            'transportationArrangementsEnabled' => ['Transportation arrangement', 'Show transport arrangement information.'],
                            'churchGroupsEnabled' => ['Church groups', 'Show groups and group join workflows.'],
                            'dynamicFormsEnabled' => ['On-demand forms', 'Show dynamic forms created from the backend.'],
                            'goshenQuizEnabled' => ['Quiz', 'Show Goshen Quiz in the app.'],
                            'goshenWalletWithdrawalsEnabled' => ['Wallet withdrawal', 'Allow wallet withdrawal request screens.'],
                            'goshenWalletAutoTopupEnabled' => ['Wallet auto top-up', 'Allow recurring wallet auto top-up plans.'],
                            'goshenWalletAdminTopupEnabled' => ['Admin wallet top-up', 'Allow authorized admins to add funds directly to member wallets.'],
                            'branchesEnabled' => ['Branches', 'Show the branches module.'],
                            'mobilePhoneOtpLoginEnabled' => ['Mobile phone OTP login', 'Allow Firebase phone number sign-in.'],
                        ] as $model => [$label, $note])
                            <label class="ash-check">
                                <input type="checkbox" wire:model.live="{{ $model }}">
                                <span>
                                    <strong>{{ $label }}</strong>
                                    <span>{{ $note }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </section>

                <section class="ash-panel" x-show="tab === 'payments'" x-cloak>
                    <div class="ash-panel-head">
                        <h2 class="ash-h2">Giving & Payments</h2>
                        <p class="ash-muted">General giving links are editable here. Stripe keys, modes, and webhooks live in the focused Payment Gateways page below.</p>
                    </div>
                    <div class="ash-grid">
                        <label class="ash-field ash-field-full">
                            <span class="ash-label">PayPal link</span>
                            <input class="ash-input" wire:model.defer="paypalLink" placeholder="https://paypal.me/...">
                        </label>
                    </div>
                </section>

                <section class="ash-panel" x-show="tab === 'support'" x-cloak>
                    <div class="ash-panel-head">
                        <h2 class="ash-h2">Support</h2>
                        <p class="ash-muted">Contact details shown around accommodation booking and payment flows.</p>
                    </div>
                    <div class="ash-grid">
                        <label class="ash-field">
                            <span class="ash-label">Support name</span>
                            <input class="ash-input" wire:model.defer="accommodationSupportName">
                        </label>
                        <label class="ash-field">
                            <span class="ash-label">Support email</span>
                            <input class="ash-input" type="email" wire:model.defer="accommodationSupportEmail">
                        </label>
                        <label class="ash-field">
                            <span class="ash-label">Support phone</span>
                            <input class="ash-input" wire:model.defer="accommodationSupportPhone">
                        </label>
                        <label class="ash-field">
                            <span class="ash-label">Support WhatsApp URL</span>
                            <input class="ash-input" wire:model.defer="accommodationSupportWhatsapp" placeholder="https://wa.me/...">
                        </label>
                        <label class="ash-field ash-field-full">
                            <span class="ash-label">Support instructions</span>
                            <textarea class="ash-textarea" wire:model.defer="accommodationSupportInstructions"></textarea>
                        </label>
                    </div>
                </section>

                <section class="ash-panel" x-show="tab === 'integrations'" x-cloak>
                    <div class="ash-panel-head">
                        <h2 class="ash-h2">Integrations</h2>
                        <p class="ash-muted">Open focused setup pages for credentials and operational tooling.</p>
                    </div>
                    <div class="ash-grid" style="padding-bottom:0;">
                        <label class="ash-field ash-field-full">
                            <span class="ash-label">Legacy Firebase service account path</span>
                            <input class="ash-input" type="password" wire:model.defer="serviceAccountPath" placeholder="Leave blank to keep saved value">
                        </label>
                    </div>
                    <div class="ash-links">
                        @foreach ($quickLinks as $link)
                            <a class="ash-link" href="{{ $link['url'] }}">
                                <span>
                                    <h3>{{ $link['label'] }}</h3>
                                    <p>{{ $link['description'] }}</p>
                                </span>
                                <span aria-hidden="true">-></span>
                            </a>
                        @endforeach
                    </div>
                </section>

                <section class="ash-panel" x-show="tab === 'other'" x-cloak>
                    <div class="ash-panel-head">
                        <h2 class="ash-h2">Other Settings</h2>
                        <p class="ash-muted">Any app settings not already handled by the grouped tabs or focused setup pages appear here automatically.</p>
                    </div>
                    @forelse ($additionalSettingGroups as $group => $settings)
                        <div class="ash-panel-head" style="border-top:1px solid var(--ash-line);">
                            <h3 class="ash-h2" style="font-size:18px;">{{ $group }}</h3>
                        </div>
                        <div class="ash-grid">
                            @foreach ($settings as $setting)
                                <label class="ash-field {{ strlen($setting['value']) > 120 ? 'ash-field-full' : '' }}">
                                    <span class="ash-label">{{ $setting['label'] }}</span>
                                    @if (strlen($setting['value']) > 120)
                                        <textarea
                                            class="ash-textarea"
                                            wire:model.defer="additionalSettings.{{ $setting['key'] }}.value"
                                            placeholder="{{ $setting['is_secret'] ? 'Leave blank to keep saved secret' : '' }}"
                                        ></textarea>
                                    @else
                                        <input
                                            class="ash-input"
                                            type="{{ $setting['is_secret'] ? 'password' : 'text' }}"
                                            wire:model.defer="additionalSettings.{{ $setting['key'] }}.value"
                                            placeholder="{{ $setting['is_secret'] ? 'Leave blank to keep saved secret' : '' }}"
                                        >
                                    @endif
                                    @if (filled($setting['description']))
                                        <span class="ash-tab-note">{{ $setting['description'] }}</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    @empty
                        <div class="ash-grid">
                            <p class="ash-muted ash-field-full">All current app settings are already grouped above or linked to focused setup pages.</p>
                        </div>
                    @endforelse
                </section>

                <div class="ash-actions">
                    <button type="submit" class="ash-button">Save settings</button>
                </div>
            </div>
        </div>
    </form>
</x-filament-panels::page>
