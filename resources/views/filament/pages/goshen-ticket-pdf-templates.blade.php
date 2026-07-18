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
            min-height:420px;
            border-radius:20px;
            border:1px solid #d8e1eb;
            background:#f8fafc;
            padding:14px;
            transform:translateZ(0);
        }
        .dark .gtpt-preview { border-color:rgba(148,163,184,.2); background:#0b1220; }
        .gtpt-ticket {
            width:100%;
            max-width:310px;
            min-height:390px;
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
            width:118px;
            height:118px;
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
        .executive .gtpt-ticket { border:2px solid #d9a920; }
        .executive .gtpt-preview-main { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:14px; }
        .boarding .gtpt-ticket { padding:0; overflow:hidden; }
        .boarding .gtpt-band { padding:16px; background:linear-gradient(135deg,#08283a,#0d4b55 70%,#e1b12c); color:#fff; }
        .boarding .gtpt-band .gtpt-mini-title { color:#fff; text-align:left; }
        .boarding .gtpt-band .gtpt-mini-sub { color:#e2e8f0; text-align:left; }
        .boarding .gtpt-body { padding:14px; display:grid; grid-template-columns:1fr 118px; gap:12px; }
        .credential .gtpt-ticket { border-top:8px solid #08283a; }
        .credential .gtpt-id-head { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:12px; padding:10px; background:#f8fafc; border-radius:12px; }
        .credential .gtpt-body { display:grid; grid-template-columns:1fr 118px; gap:12px; margin-top:12px; }
        .qrhero .gtpt-ticket { border-left:7px solid #d9a920; text-align:center; }
        .qrhero .gtpt-qr { width:150px; height:150px; margin:18px auto 14px; }
        .certificate .gtpt-ticket { border:3px double #d9a920; font-family:Georgia,serif; }
        .certificate .gtpt-body { display:grid; grid-template-columns:120px 1fr; gap:12px; margin-top:14px; }
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
                                    <img class="gtpt-logo" src="{{ asset('images/goshenretreatlogo.png') }}" alt="Goshen Camp Retreat">
                                    <h4 class="gtpt-mini-title">Goshen Camp Retreat 2026</h4>
                                    <p class="gtpt-mini-sub">Official attendee access pass</p>
                                </div>
                                <div class="gtpt-body">
                                    <div>
                                        <div class="gtpt-avatar"></div>
                                        <div class="gtpt-detail"><span class="gtpt-label">Attendee</span><span class="gtpt-value">Adeoye Oduwaiye</span></div>
                                        <div class="gtpt-detail"><span class="gtpt-label">Ticket</span><span class="gtpt-value">GOSHEN-2-000003</span></div>
                                        <span class="gtpt-pill">not checked in</span>
                                    </div>
                                    <div><div class="gtpt-qr"></div></div>
                                </div>
                            @elseif($key === 'identity_credential')
                                <img class="gtpt-logo" src="{{ asset('images/goshenretreatlogo.png') }}" alt="Goshen Camp Retreat">
                                <div class="gtpt-id-head"><div><span class="gtpt-label">Attendee</span><span class="gtpt-value">Adeoye Oduwaiye</span></div><div class="gtpt-avatar"></div></div>
                                <div class="gtpt-body">
                                    <div>
                                        <div class="gtpt-detail"><span class="gtpt-label">Ticket number</span><span class="gtpt-value">GOSHEN-2-000003</span></div>
                                        <div class="gtpt-detail"><span class="gtpt-label">Venue</span><span class="gtpt-value">High Leigh Conference Centre</span></div>
                                        <span class="gtpt-pill">not checked in</span>
                                    </div>
                                    <div><div class="gtpt-qr"></div></div>
                                </div>
                            @elseif($key === 'qr_hero')
                                <img class="gtpt-logo" src="{{ asset('images/goshenretreatlogo.png') }}" alt="Goshen Camp Retreat">
                                <h4 class="gtpt-mini-title">Goshen Camp Retreat 2026</h4>
                                <div class="gtpt-qr"></div>
                                <div class="gtpt-detail"><span class="gtpt-label">Ticket number</span><span class="gtpt-value">GOSHEN-2-000003</span></div>
                                <div class="gtpt-line"></div>
                                <div class="gtpt-detail"><span class="gtpt-label">Attendee</span><span class="gtpt-value">Adeoye Oduwaiye</span></div>
                                <span class="gtpt-pill">not checked in</span>
                            @elseif($key === 'certificate')
                                <img class="gtpt-logo" src="{{ asset('images/goshenretreatlogo.png') }}" alt="Goshen Camp Retreat">
                                <h4 class="gtpt-mini-title">Goshen Camp Retreat 2026</h4>
                                <div class="gtpt-detail" style="text-align:center;"><span class="gtpt-label">Issued to</span><span class="gtpt-value">Adeoye Oduwaiye</span></div>
                                <div class="gtpt-body">
                                    <div><div class="gtpt-qr"></div></div>
                                    <div>
                                        <div class="gtpt-detail"><span class="gtpt-label">Ticket</span><span class="gtpt-value">GOSHEN-2-000003</span></div>
                                        <div class="gtpt-detail"><span class="gtpt-label">Venue</span><span class="gtpt-value">High Leigh Conference Centre</span></div>
                                        <span class="gtpt-pill">not checked in</span>
                                    </div>
                                </div>
                            @else
                                <img class="gtpt-logo" src="{{ asset('images/goshenretreatlogo.png') }}" alt="Goshen Camp Retreat">
                                <h4 class="gtpt-mini-title">Goshen Camp Retreat 2026</h4>
                                <p class="gtpt-mini-sub">Official attendee ticket</p>
                                <div class="gtpt-line"></div>
                                <div class="gtpt-preview-main">
                                    <div><div class="gtpt-qr"></div></div>
                                    <div>
                                        <div class="gtpt-avatar"></div>
                                        <div class="gtpt-detail"><span class="gtpt-label">Ticket</span><span class="gtpt-value">GOSHEN-2-000003</span></div>
                                        <div class="gtpt-detail"><span class="gtpt-label">Attendee</span><span class="gtpt-value">Adeoye Oduwaiye</span></div>
                                        <span class="gtpt-pill">not checked in</span>
                                    </div>
                                </div>
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
