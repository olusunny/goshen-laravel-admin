<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Event Installments')</title>
    <style>
        :root { color-scheme: light; --bg: #f8fafc; --panel: #ffffff; --ink: #111827; --muted: #6b7280; --line: #d1d5db; --accent: #0f766e; --danger: #b91c1c; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 14px/1.5 ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        a { color: var(--accent); text-decoration: none; }
        .shell { max-width: 1180px; margin: 0 auto; padding: 24px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
        h1 { margin: 0; font-size: 24px; font-weight: 700; }
        h2 { margin: 0 0 12px; font-size: 18px; }
        .panel { background: var(--panel); border: 1px solid var(--line); border-radius: 8px; padding: 18px; margin-bottom: 18px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        label { display: block; font-weight: 600; margin-bottom: 5px; }
        input, select, textarea { width: 100%; box-sizing: border-box; border: 1px solid var(--line); border-radius: 6px; padding: 9px 10px; font: inherit; background: #fff; color: var(--ink); }
        textarea { min-height: 120px; resize: vertical; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 8px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
        th { color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .button, button { display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--accent); background: var(--accent); color: #fff; border-radius: 6px; padding: 8px 12px; font-weight: 700; cursor: pointer; }
        .button.secondary, button.secondary { background: #fff; color: var(--accent); }
        button.danger { border-color: var(--danger); background: var(--danger); }
        .notice { border: 1px solid #99f6e4; background: #f0fdfa; color: #134e4a; padding: 10px 12px; border-radius: 6px; margin-bottom: 16px; }
        .errors { border: 1px solid #fecaca; background: #fef2f2; color: #7f1d1d; padding: 10px 12px; border-radius: 6px; margin-bottom: 16px; }
        .muted { color: var(--muted); }
        @media (max-width: 760px) { .shell { padding: 16px; } .topbar, .grid, .grid-3 { display: block; } .grid > *, .grid-3 > * { margin-bottom: 12px; } table { display: block; overflow-x: auto; } }
    </style>
</head>
<body>
    <main class="shell">
        <div class="topbar">
            <h1>@yield('title', 'Event Installments')</h1>
            <nav class="actions">
                <a class="button secondary" href="{{ route('event-installments.events.index') }}">Events</a>
                @yield('actions')
            </nav>
        </div>

        @if (session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="errors">
                <strong>Fix these fields:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
