<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Goshen Forms</title>
    <style>
        :root {
            --primary: #0c2230;
            --gold: #ffb82e;
            --muted: #60717c;
            --surface: #f3f8fb;
            --line: #dce8ed;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--surface);
            color: var(--primary);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.55;
        }

        main {
            width: min(980px, calc(100% - 32px));
            margin: 0 auto;
            padding: 36px 0 52px;
        }

        .hero {
            background: linear-gradient(135deg, #0c2230, #115047);
            color: white;
            border-radius: 24px;
            padding: 28px;
            margin-bottom: 22px;
            box-shadow: 0 20px 45px rgba(12, 34, 48, .14);
        }

        h1, h2, p { margin-top: 0; }
        h1 { margin-bottom: 8px; font-size: clamp(2rem, 5vw, 3.4rem); line-height: 1.05; }
        .hero p { max-width: 640px; margin-bottom: 0; color: rgba(255,255,255,.78); font-size: 1.05rem; }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
        }

        .card {
            display: flex;
            flex-direction: column;
            gap: 14px;
            min-height: 220px;
            background: white;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 22px;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 14px 35px rgba(12, 34, 48, .07);
        }

        .card h2 { margin-bottom: 0; font-size: 1.45rem; line-height: 1.2; }
        .card p { color: var(--muted); margin-bottom: auto; }
        .meta { color: var(--muted); font-weight: 700; font-size: .92rem; }
        .button {
            align-self: flex-start;
            background: var(--gold);
            color: var(--primary);
            border-radius: 999px;
            padding: 11px 18px;
            font-weight: 800;
        }

        .empty {
            background: white;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 24px;
            color: var(--muted);
        }
    </style>
</head>
<body>
<main>
    <section class="hero">
        <h1>Goshen Forms</h1>
        <p>Open forms and registrations from Covenant of Mercy. Choose a form below to submit details or complete a secure payment when required.</p>
    </section>

    @if($forms->isEmpty())
        <div class="empty">There are no open forms right now.</div>
    @else
        <section class="grid" aria-label="Open forms">
            @foreach($forms as $form)
                <a class="card" href="{{ route('dynamic-forms.show', $form->slug) }}">
                    <span class="meta">{{ $form->fields->count() }} {{ Str::plural('field', $form->fields->count()) }}</span>
                    <h2>{{ $form->title }}</h2>
                    @if($form->description)
                        <p>{{ Str::limit(strip_tags($form->description), 150) }}</p>
                    @else
                        <p>Complete this form and submit it securely.</p>
                    @endif
                    @if($form->requiresPayment())
                        <span class="meta">{{ $form->currency }} {{ number_format((float) $form->fixed_amount, 2) }}</span>
                    @endif
                    <span class="button">Open form</span>
                </a>
            @endforeach
        </section>
    @endif
</main>
</body>
</html>
