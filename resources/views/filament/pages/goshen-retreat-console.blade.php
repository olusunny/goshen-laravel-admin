<x-filament-panels::page>
    <style>
        .gr-page { --gr-primary: #0c2230; --gr-accent: #f5b51b; --gr-card: #ffffff; --gr-soft: #f8fafc; --gr-line: #e5e7eb; --gr-muted: #667085; --gr-text: #111827; --gr-shadow: 0 16px 40px rgba(15, 23, 42, .08); display: grid; gap: 24px; color: var(--gr-text); }
        .dark .gr-page { --gr-card: #111827; --gr-soft: rgba(15, 23, 42, .55); --gr-line: rgba(148, 163, 184, .18); --gr-muted: #a8b0bd; --gr-text: #f8fafc; --gr-shadow: 0 18px 46px rgba(0, 0, 0, .28); }
        .gr-hero { position: relative; isolation: isolate; overflow: hidden; border-radius: 24px; padding: 28px; color: #fff; background: radial-gradient(circle at 90% 20%, rgba(245, 181, 27, .28), transparent 30%), linear-gradient(135deg, #0c2230 0%, #12384a 52%, #0f513c 100%); box-shadow: var(--gr-shadow); }
        .gr-hero:after { content: ""; position: absolute; inset: auto -70px -135px auto; width: 320px; height: 320px; border: 1px solid rgba(255, 255, 255, .16); border-radius: 999px; z-index: -1; }
        .gr-eyebrow { margin: 0 0 8px; color: #facc15; font-size: 12px; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; }
        .gr-title { margin: 0; font-size: clamp(26px, 3vw, 38px); line-height: 1.08; font-weight: 950; letter-spacing: -.03em; }
        .gr-copy { margin: 12px 0 0; max-width: 760px; color: rgba(255, 255, 255, .82); font-size: 15px; line-height: 1.65; }
        .gr-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
        .gr-card { position: relative; display: flex; min-height: 210px; flex-direction: column; justify-content: space-between; overflow: hidden; border: 1px solid var(--gr-line); border-radius: 22px; padding: 22px; background: var(--gr-card); box-shadow: var(--gr-shadow); text-decoration: none; transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease; }
        .gr-card:hover { transform: translateY(-2px); border-color: rgba(245, 181, 27, .55); box-shadow: 0 20px 48px rgba(15, 23, 42, .12); }
        .gr-card:after { content: ""; position: absolute; inset: auto -80px -100px auto; width: 210px; height: 210px; border-radius: 999px; background: radial-gradient(circle, rgba(245, 181, 27, .15), transparent 66%); pointer-events: none; }
        .gr-icon { display: inline-flex; width: 48px; height: 48px; align-items: center; justify-content: center; border-radius: 16px; background: rgba(245, 181, 27, .16); color: var(--gr-primary); font-size: 24px; }
        .dark .gr-icon { color: #facc15; background: rgba(245, 181, 27, .12); }
        .gr-card-title { margin: 18px 0 0; color: var(--gr-text); font-size: 20px; line-height: 1.18; font-weight: 950; letter-spacing: -.02em; }
        .gr-card-copy { margin: 10px 0 0; color: var(--gr-muted); font-size: 14px; line-height: 1.55; }
        .gr-card-action { display: inline-flex; width: fit-content; align-items: center; gap: 8px; margin-top: 18px; color: var(--gr-primary); font-size: 13px; font-weight: 900; }
        .dark .gr-card-action { color: #facc15; }
        .gr-empty { border: 1px solid var(--gr-line); border-radius: 20px; padding: 24px; background: var(--gr-card); color: var(--gr-muted); box-shadow: var(--gr-shadow); }
        @media (max-width: 1100px) { .gr-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 720px) { .gr-grid { grid-template-columns: 1fr; } .gr-hero { padding: 22px; } }
    </style>

    @php
        $icons = [
            'calendar' => 'EV',
            'clock' => 'SC',
            'ticket' => 'TK',
            'wallet' => 'PY',
            'form' => 'FM',
            'clipboard' => 'BK',
            'qr' => 'QR',
            'bed' => 'AC',
            'gift' => 'RF',
            'settings' => 'ST',
        ];
    @endphp

    <div class="gr-page">
        <section class="gr-hero">
            <p class="gr-eyebrow">Goshen Retreat</p>
            <h2 class="gr-title">Event registration and installment control center</h2>
            <p class="gr-copy">
                Manage retreat editions, ticket types, installment plans, bookings, QR tickets, scanner operations,
                and accommodation allocations from one clear place.
            </p>
        </section>

        @if (empty($cards))
            <div class="gr-empty">
                You do not have permission to manage any Goshen Retreat area yet. A super admin can grant access from Role Permissions.
            </div>
        @else
            <section class="gr-grid" aria-label="Goshen Retreat admin areas">
                @foreach ($cards as $card)
                    <a class="gr-card" href="{{ $card['url'] }}">
                        <div>
                            <span class="gr-icon" aria-hidden="true">{{ $icons[$card['icon']] ?? 'GO' }}</span>
                            <h3 class="gr-card-title">{{ $card['title'] }}</h3>
                            <p class="gr-card-copy">{{ $card['description'] }}</p>
                        </div>
                        <span class="gr-card-action">Open area <span aria-hidden="true">-&gt;</span></span>
                    </a>
                @endforeach
            </section>
        @endif
    </div>
</x-filament-panels::page>
