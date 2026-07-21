<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0c2230">
    <title>Join me at {{ $event?->name ?? 'Goshen Retreat' }}</title>
    <meta name="description" content="I am attending {{ $event?->name ?? 'Goshen Retreat' }}. Come and seek God with me at {{ $venue }} on {{ $eventDate }}.">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Join me at {{ $event?->name ?? 'Goshen Retreat' }}">
    <meta property="og:description" content="I am attending {{ $event?->name ?? 'Goshen Retreat' }}. Come and seek God with me at {{ $venue }} on {{ $eventDate }}.">
    <meta property="og:url" content="{{ url()->current() }}">
    @if ($featureImageUrl)
        <meta property="og:image" content="{{ $featureImageUrl }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="{{ $featureImageUrl }}">
    @else
        <meta name="twitter:card" content="summary">
    @endif
    <meta name="twitter:title" content="Join me at {{ $event?->name ?? 'Goshen Retreat' }}">
    <meta name="twitter:description" content="Come and seek God with me at {{ $venue }} on {{ $eventDate }}.">
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; background: #eff6f8; color: #0c2230; }
        main { width: min(100% - 32px, 640px); margin: 0 auto; padding: 32px 0 48px; }
        .card { overflow: hidden; border: 1px solid #dce7eb; border-radius: 24px; background: #fff; box-shadow: 0 18px 50px rgba(12, 34, 48, .12); }
        .media { display: block; width: 100%; aspect-ratio: 16 / 9; object-fit: cover; background: #0c2230; }
        .content { padding: 28px; }
        .eyebrow { margin: 0 0 10px; color: #0f4b3d; font-size: 13px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        h1 { margin: 0 0 12px; font-size: clamp(30px, 8vw, 46px); line-height: 1.04; }
        p { color: #52636c; font-size: 16px; line-height: 1.6; }
        .details { display: grid; gap: 10px; margin: 24px 0; padding: 18px; border-radius: 16px; background: #f3f8fa; }
        .details strong { display: block; color: #0c2230; }
        .details span { color: #52636c; }
        .code { margin: 20px 0; color: #0f4b3d; font-size: 22px; font-weight: 900; letter-spacing: .08em; text-align: center; }
        .button { display: inline-flex; min-height: 50px; width: 100%; align-items: center; justify-content: center; border-radius: 15px; background: #f8b522; color: #0c2230; font-weight: 900; text-decoration: none; }
    </style>
</head>
<body>
    <main>
        <article class="card">
            @if ($featureImageUrl)
                <img class="media" src="{{ $featureImageUrl }}" alt="{{ $event?->name ?? 'Goshen Retreat' }} feature image">
            @endif
            <div class="content">
                <p class="eyebrow">MFM Triumphant Church</p>
                <h1>Come and seek God with me at {{ $event?->name ?? 'Goshen Retreat' }}</h1>
                <p>I am attending and would love for us to experience this time of prayer, worship, and renewal together.</p>
                <div class="details">
                    <div><strong>Date</strong><span>{{ $eventDate }}</span></div>
                    <div><strong>Venue</strong><span>{{ $venue }}</span></div>
                </div>
                <p class="code">Referral code: {{ $code }}</p>
                <a class="button" href="{{ url('/app?ref='.$code) }}">Join the retreat</a>
            </div>
        </article>
    </main>
</body>
</html>
