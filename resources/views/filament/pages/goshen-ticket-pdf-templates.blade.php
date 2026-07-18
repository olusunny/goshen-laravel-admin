<x-filament-panels::page>
    <style>
        .gtpt-page { display:grid; gap:22px; color:#111827; }
        .dark .gtpt-page { color:#f8fafc; }
        .gtpt-hero,
        .gtpt-card {
            border:1px solid #e5e7eb;
            border-radius:24px;
            background:#fff;
            box-shadow:0 18px 46px rgba(15,23,42,.08);
        }
        .dark .gtpt-hero,
        .dark .gtpt-card {
            border-color:rgba(148,163,184,.22);
            background:#111827;
            box-shadow:0 20px 52px rgba(0,0,0,.28);
        }
        .gtpt-hero { padding:24px; }
        .gtpt-eyebrow { margin:0 0 8px; color:#d9a920; font-size:12px; font-weight:900; letter-spacing:.14em; text-transform:uppercase; }
        .gtpt-title { margin:0; font-size:clamp(28px,4vw,44px); line-height:1.04; font-weight:950; }
        .gtpt-copy { margin:10px 0 0; max-width:900px; color:#667085; line-height:1.6; }
        .dark .gtpt-copy { color:#a8b0bd; }
        .gtpt-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; }
        .gtpt-card { padding:18px; position:relative; overflow:hidden; }
        .gtpt-card.gtpt-active { border-color:#f59e0b; box-shadow:0 20px 50px rgba(245,158,11,.18); }
        .gtpt-choice { display:flex; gap:12px; align-items:flex-start; margin-bottom:14px; }
        .gtpt-radio { margin-top:5px; accent-color:#f59e0b; }
        .gtpt-name { margin:0; font-size:18px; font-weight:950; }
        .gtpt-subtitle { margin:2px 0 0; color:#d9a920; font-size:12px; font-weight:900; letter-spacing:.1em; text-transform:uppercase; }
        .gtpt-description { margin:8px 0 0; color:#667085; font-size:13px; line-height:1.5; }
        .dark .gtpt-description { color:#a8b0bd; }
        .gtpt-preview {
            min-height:560px;
            border-radius:20px;
            border:1px solid #d8e1eb;
            background:#f8fafc;
            padding:14px;
            transform:translateZ(0);
        }
        .dark .gtpt-preview { border-color:rgba(148,163,184,.2); background:#0b1220; }
        .gtpt-ticket {
            width:100%;
            max-width:390px;
            min-height:530px;
            margin:0 auto;
            background:#fff;
            color:#101820;
            border-radius:18px;
            padding:14px;
            box-shadow:0 14px 32px rgba(8,40,58,.14);
            font-size:10px;
        }
        .gtpt-logo { height:38px; margin:0 auto 8px; display:block; object-fit:contain; }
        .gtpt-mini-title { margin:0; font-size:16px; font-weight:950; text-align:center; color:#08283a; }
        .gtpt-mini-sub { margin:3px 0 0; text-align:center; color:#64748b; font-weight:700; }
        .gtpt-qr {
            width:132px;
            height:132px;
            background:
                linear-gradient(90deg, #000 5px, transparent 5px 10px) 0 0 / 16px 16px,
                linear-gradient(#000 5px, transparent 5px 10px) 0 0 / 16px 16px,
                repeating-linear-gradient(45deg, transparent 0 4px, #000 4px 6px, transparent 6px 11px),
                #fff;
            box-shadow:inset 0 0 0 4px #000;
        }
        .gtpt-pill { display:inline-block; padding:4px 8px; border-radius:999px; background:#e8f8ef; color:#047857; font-weight:900; }
        .gtpt-line { height:1px; background:#dbe3ea; margin:10px 0; }
        .gtpt-detail { margin:7px 0; }
        .gtpt-label { display:block; color:#9a7200; font-size:8px; font-weight:950; letter-spacing:.1em; text-transform:uppercase; }
        .gtpt-value { display:block; color:#111827; font-size:11px; font-weight:850; }
        .gtpt-avatar { width:42px; height:42px; border-radius:999px; background:linear-gradient(135deg,#dbeafe,#fef3c7); border:3px solid #fff; box-shadow:0 8px 18px rgba(8,40,58,.22); }
        .gtpt-actions { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
        .gtpt-button { min-height:44px; border:0; border-radius:14px; padding:11px 18px; background:#f59e0b; color:#111827; font-weight:950; cursor:pointer; }
        .gtpt-current { color:#667085; font-size:14px; }
        .dark .gtpt-current { color:#a8b0bd; }
        .gtpt-venue-light { margin-top:14px; padding:12px 14px; border:1px solid #f0c94d; border-radius:14px; background:#fff8df; }
        .gtpt-venue-dark { margin-top:16px; padding:12px 14px; border-radius:14px; background:#08283a; color:#fff; }
        .gtpt-venue-dark .gtpt-value { color:#fff; }
        .executive .gtpt-ticket { border:2px solid #d9a920; }
        .executive .gtpt-preview-main { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:18px; align-items:start; }
        .executive .gtpt-qr { width:170px; height:170px; margin:0 auto 12px; }
        .boarding .gtpt-ticket { padding:0; overflow:hidden; }
        .boarding .gtpt-band { min-height:170px; padding:20px 18px; background:linear-gradient(135deg,#08283a,#0d4b55 70%,#d9a920); color:#fff; border-radius:18px 18px 0 0; }
        .boarding .gtpt-band .gtpt-mini-title { color:#fff; text-align:left; }
        .boarding .gtpt-band .gtpt-mini-sub { color:#e2e8f0; text-align:left; }
        .boarding .gtpt-body { margin:-28px 12px 0; display:grid; grid-template-columns:1.25fr .85fr; gap:12px; align-items:start; }
        .boarding .gtpt-pass-card { min-height:210px; border:1px solid #dbe3ea; border-radius:16px; background:#fff; padding:16px; }
        .boarding .gtpt-stub { min-height:210px; border:1px solid #dbe3ea; border-radius:16px; background:#fff; padding:18px 10px; text-align:center; }
        .boarding .gtpt-stub .gtpt-qr { width:125px; height:125px; margin:0 auto 14px; }
        .credential .gtpt-ticket { padding:12px; }
        .credential .gtpt-credential-top { display:flex; align-items:center; justify-content:space-between; gap:12px; padding-bottom:12px; border-bottom:4px solid #08283a; }
        .credential .gtpt-credential-top .gtpt-subtitle { color:#d9a920; letter-spacing:.14em; text-align:right; }
        .credential .gtpt-id-head { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:18px; padding:18px; background:#f8fafc; border-radius:18px; }
        .credential .gtpt-body { display:grid; grid-template-columns:1fr 145px; gap:14px; margin-top:16px; align-items:start; }
        .credential .gtpt-qr { width:138px; height:138px; }
        .qrhero .gtpt-ticket { border-left:10px solid #fff2cc; text-align:center; }
        .qrhero .gtpt-hero-head { display:grid; grid-template-columns:95px 1fr 46px; gap:10px; align-items:center; padding-bottom:14px; border-bottom:1px solid #dbe3ea; text-align:left; }
        .qrhero .gtpt-qr { width:210px; height:210px; margin:22px auto 12px; box-shadow:0 0 0 10px #fff, 0 0 0 12px #08283a; }
        .qrhero .gtpt-number-pill { display:inline-block; border-radius:999px; background:#08283a; color:#fff; padding:9px 18px; font-weight:950; letter-spacing:.1em; }
        .qrhero .gtpt-card-grid { display:grid; grid-template-columns:2fr 1fr; gap:10px; margin-top:22px; text-align:left; }
        .qrhero .gtpt-card-grid .gtpt-detail { border:1px solid #dbe3ea; border-radius:14px; background:#f8fafc; padding:12px; margin:0; }
        .certificate .gtpt-ticket { border:3px double #d9a920; font-family:Georgia,serif; }
        .certificate .gtpt-mini-title { font-family:Georgia,serif; font-size:24px; }
        .certificate .gtpt-issued { display:flex; justify-content:center; align-items:center; gap:14px; margin:20px 0; }
        .certificate .gtpt-body { display:grid; grid-template-columns:150px 1fr; gap:16px; margin-top:14px; }
        .certificate .gtpt-qr { width:145px; height:145px; }
        .certificate .gtpt-cert-line { display:flex; justify-content:space-between; gap:8px; border-bottom:1px solid #dbe3ea; padding:8px 0; }
        @media (max-width: 980px) { .gtpt-grid { grid-template-columns:1fr; } }
    </style>

    <form wire:submit.prevent="save" class="gtpt-page">
        <section class="gtpt-hero">
            <p class="gtpt-eyebrow">Goshen Retreat PDF design</p>
            <h2 class="gtpt-title">Choose the preferred ticket PDF template</h2>
            <p class="gtpt-copy">
                Select one of the five premium ticket templates. The saved template will be used for newly generated or regenerated ticket PDFs, including email attachments. Venue details are read from the retreat edition; attendee profile photo appears only when available.
            </p>
        </section>

        <section class="gtpt-grid">
            @foreach($templates as $key => $template)
                @php
                    $selected = $selectedTemplate === $key;
                    $previewClass = match ($key) {
                        'boarding_pass' => 'boarding',
                        'identity_credential' => 'credential',
                        'qr_hero' => 'qrhero',
                        'certificate' => 'certificate',
                        default => 'executive',
                    };
                @endphp
                <article class="gtpt-card {{ $selected ? 'gtpt-active' : '' }}">
                    <label class="gtpt-choice">
                        <input class="gtpt-radio" type="radio" wire:model.live="selectedTemplate" value="{{ $key }}">
                        <span>
                            <h3 class="gtpt-name">{{ $template['name'] }}</h3>
                            <p class="gtpt-subtitle">{{ $template['subtitle'] }}</p>
                            <p class="gtpt-description">{{ $template['description'] }}</p>
                        </span>
                    </label>

                    <div class="gtpt-preview {{ $previewClass }}">
                        <div class="gtpt-ticket">
                            @if($key === 'boarding_pass')
                                <div class="gtpt-band">
                                    <span style="display:inline-block;background:#fff;border:8px solid #e8eef2;border-radius:16px;padding:4px 6px;">
                                        <img class="gtpt-logo" src="{{ asset('images/goshenretreatlogo.png') }}" alt="Goshen Camp Retreat">
                                    </span>
                                    <h4 class="gtpt-mini-title">Goshen Camp Retreat 2026</h4>
                                    <p class="gtpt-mini-sub">Official attendee access pass</p>
                                    <span style="display:inline-block;margin-top:10px;border-radius:999px;background:rgba(255,255,255,.2);padding:6px 12px;font-weight:950;letter-spacing:.08em;">GOSHEN-2-000003</span>
                                </div>
                                <div class="gtpt-body">
                                    <div class="gtpt-pass-card">
                                        <div class="gtpt-avatar"></div>
                                        <div class="gtpt-detail"><span class="gtpt-label">Attendee</span><span class="gtpt-value">Adeoye Oduwaiye</span></div>
                                        <div class="gtpt-line"></div>
                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                            <div class="gtpt-detail"><span class="gtpt-label">Ticket type</span><span class="gtpt-value">GOSHEN INDIVIDUAL</span></div>
                                            <div class="gtpt-detail"><span class="gtpt-label">Amount paid</span><span class="gtpt-value">GBP 280.00</span></div>
                                        </div>
                                        <span class="gtpt-pill">not checked in</span>
                                    </div>
                                    <div class="gtpt-stub"><div class="gtpt-qr"></div><strong>Scan at check-in</strong><p style="margin:6px 0 0;color:#64748b;font-size:10px;">Fast validation for this attendee only.</p></div>
                                </div>
                                <div class="gtpt-venue-light"><span class="gtpt-label">Retreat venue</span><span class="gtpt-value">High Leigh Conference Centre</span><span style="display:block;color:#64748b;">Lord Street, Hoddesdon, Hertfordshire EN11 8SG</span></div>
                            @elseif($key === 'identity_credential')
                                <div class="gtpt-credential-top">
                                    <img class="gtpt-logo" src="{{ asset('images/goshenretreatlogo.png') }}" alt="Goshen Camp Retreat">
                                    <p class="gtpt-subtitle">Official attendee credential</p>
                                </div>
                                <h4 class="gtpt-mini-title" style="text-align:left;margin-top:22px;font-size:22px;">Goshen Camp Retreat 2026</h4>
                                <div class="gtpt-id-head"><div><span class="gtpt-label">Attendee</span><span class="gtpt-value">Adeoye Oduwaiye</span></div><div class="gtpt-avatar"></div></div>
                                <div class="gtpt-body">
                                    <div>
                                        <div class="gtpt-detail"><span class="gtpt-label">Ticket number</span><span class="gtpt-value">GOSHEN-2-000003</span></div>
                                        <div class="gtpt-detail"><span class="gtpt-label">Ticket type</span><span class="gtpt-value">GOSHEN INDIVIDUAL</span></div>
                                        <div class="gtpt-detail"><span class="gtpt-label">Amount paid</span><span class="gtpt-value">GBP 280.00</span></div>
                                        <span class="gtpt-pill">not checked in</span>
                                    </div>
                                    <div><div class="gtpt-qr"></div></div>
                                </div>
                                <div class="gtpt-venue-dark"><span class="gtpt-label">Retreat venue</span><span class="gtpt-value">High Leigh Conference Centre • Lord Street, Hoddesdon, Hertfordshire EN11 8SG</span></div>
                            @elseif($key === 'qr_hero')
                                <div class="gtpt-hero-head">
                                    <img class="gtpt-logo" src="{{ asset('images/goshenretreatlogo.png') }}" alt="Goshen Camp Retreat">
                                    <div><p class="gtpt-subtitle">Official attendee ticket</p><h4 class="gtpt-mini-title" style="text-align:left;">Goshen Camp Retreat 2026</h4></div>
                                    <div class="gtpt-avatar" style="width:46px;height:46px;"></div>
                                </div>
                                <div class="gtpt-qr"></div>
                                <div class="gtpt-number-pill">GOSHEN-2-000003</div>
                                <p style="color:#64748b;">Present this QR code at check-in. It belongs only to the named attendee.</p>
                                <div class="gtpt-card-grid">
                                    <div class="gtpt-detail"><span class="gtpt-label">Attendee</span><span class="gtpt-value">Adeoye Oduwaiye</span></div>
                                    <div class="gtpt-detail"><span class="gtpt-label">Status</span><span class="gtpt-pill">not checked in</span></div>
                                    <div class="gtpt-detail"><span class="gtpt-label">Ticket type</span><span class="gtpt-value">GOSHEN INDIVIDUAL</span></div>
                                    <div class="gtpt-detail"><span class="gtpt-label">Amount paid</span><span class="gtpt-value">GBP 280.00</span></div>
                                </div>
                                <div class="gtpt-venue-light"><span class="gtpt-label">Retreat venue</span><span class="gtpt-value">High Leigh Conference Centre</span><span style="display:block;color:#64748b;">Lord Street, Hoddesdon, Hertfordshire EN11 8SG</span></div>
                            @elseif($key === 'certificate')
                                <img class="gtpt-logo" src="{{ asset('images/goshenretreatlogo.png') }}" alt="Goshen Camp Retreat">
                                <h4 class="gtpt-mini-title">Goshen Camp Retreat 2026</h4>
                                <p class="gtpt-subtitle">Official attendee ticket</p>
                                <div class="gtpt-issued"><div class="gtpt-avatar"></div><div class="gtpt-detail" style="text-align:left;"><span class="gtpt-label">Issued to</span><span class="gtpt-value" style="font-family:Georgia,serif;font-size:20px;">Adeoye Oduwaiye</span></div></div>
                                <div class="gtpt-body">
                                    <div><div class="gtpt-qr"></div></div>
                                    <div>
                                        <div class="gtpt-cert-line"><span class="gtpt-label">Ticket</span><span class="gtpt-value">GOSHEN-2-000003</span></div>
                                        <div class="gtpt-cert-line"><span class="gtpt-label">Ticket type</span><span class="gtpt-value">GOSHEN INDIVIDUAL</span></div>
                                        <div class="gtpt-cert-line"><span class="gtpt-label">Amount</span><span class="gtpt-value">GBP 280.00</span></div>
                                        <div class="gtpt-cert-line"><span class="gtpt-label">Status</span><span class="gtpt-pill">not checked in</span></div>
                                    </div>
                                </div>
                                <div class="gtpt-venue-dark"><span class="gtpt-label">Retreat venue</span><span class="gtpt-value">High Leigh Conference Centre • Lord Street, Hoddesdon, Hertfordshire EN11 8SG</span></div>
                            @else
                                <img class="gtpt-logo" src="{{ asset('images/goshenretreatlogo.png') }}" alt="Goshen Camp Retreat">
                                <h4 class="gtpt-mini-title">Goshen Camp Retreat 2026</h4>
                                <p class="gtpt-mini-sub">Official attendee ticket</p>
                                <div class="gtpt-line"></div>
                                <div class="gtpt-preview-main">
                                    <div><div class="gtpt-qr"></div><strong>Scan this QR code at check-in</strong><p style="color:#64748b;">Keep this PDF accessible on your phone or printed copy.</p></div>
                                    <div>
                                        <div class="gtpt-avatar"></div>
                                        <div class="gtpt-detail"><span class="gtpt-label">Ticket</span><span class="gtpt-value">GOSHEN-2-000003</span></div>
                                        <div class="gtpt-detail"><span class="gtpt-label">Attendee</span><span class="gtpt-value">Adeoye Oduwaiye</span></div>
                                        <span class="gtpt-pill">not checked in</span>
                                    </div>
                                </div>
                                <div class="gtpt-venue-light"><span class="gtpt-label">Venue</span><span class="gtpt-value">High Leigh Conference Centre</span><span style="display:block;color:#64748b;">Lord Street, Hoddesdon, Hertfordshire EN11 8SG</span></div>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="gtpt-actions">
            <button type="submit" class="gtpt-button">Save selected PDF template</button>
            <span class="gtpt-current">
                Current saved template:
                <strong>{{ $templates[$activeTemplate]['name'] ?? 'Executive White Border' }}</strong>
            </span>
        </section>
    </form>
</x-filament-panels::page>
