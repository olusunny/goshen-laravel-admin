<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page?->title ?? ucfirst($type) }}</title>
    <style>
        :root {
            --ink: #0c2230;
            --muted: #62727b;
            --line: #dce7eb;
            --wash: #f3f8fa;
            --card: #ffffff;
            --gold: #f8b522;
            --green: #0f4b3d;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--wash);
            color: var(--ink);
        }
        a { color: inherit; }
        .topbar {
            min-height: 72px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px clamp(18px, 5vw, 56px);
            border-bottom: 1px solid var(--line);
            background: rgba(255, 255, 255, .92);
            backdrop-filter: blur(16px);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            font-weight: 1000;
        }
        .brand img {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            object-fit: cover;
        }
        .back-link {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 0 14px;
            text-decoration: none;
            font-weight: 900;
            background: var(--card);
        }
        main {
            width: min(100%, 920px);
            margin: 0 auto;
            padding: clamp(26px, 6vw, 56px) 18px;
        }
        .page-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 28px;
            box-shadow: 0 18px 50px rgba(12, 34, 48, .10);
            padding: clamp(24px, 5vw, 44px);
        }
        h1 {
            margin: 0 0 10px;
            font-size: clamp(32px, 8vw, 54px);
            line-height: 1.02;
            letter-spacing: 0;
        }
        .eyebrow {
            margin: 0 0 10px;
            color: var(--green);
            font-size: 13px;
            font-weight: 1000;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        article {
            color: var(--muted);
            font-size: 17px;
            line-height: 1.75;
        }
        article h2, article h3 {
            color: var(--ink);
            line-height: 1.2;
            margin: 28px 0 8px;
        }
        article p { margin: 0 0 16px; }
        article ul, article ol { padding-left: 24px; }
        article li { margin: 8px 0; }
        article strong { color: var(--ink); }
    </style>
</head>
<body>
    <header class="topbar">
        <a class="brand" href="/app">
            <img src="/favicon.png" alt="">
            <span>Goshen Retreat</span>
        </a>
        <a class="back-link" href="/app">Back to portal</a>
    </header>
    <main>
        <section class="page-card">
            <p class="eyebrow">MFM Triumphant Church</p>
            <h1>{{ $page?->title ?? ucfirst($type) }}</h1>
            <article>
            {!! $page?->body ?: '<p>This page is being prepared.</p>' !!}
            </article>
        </section>
    </main>
</body>
</html>
