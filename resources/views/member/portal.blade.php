<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0c2230">
    <title>Goshen Retreat Portal</title>
    <link rel="manifest" href="/member-manifest.json">
    <link rel="icon" href="/favicon.png">
    <script>
        (() => {
            const key = 'goshen_portal_theme';
            const saved = localStorage.getItem(key);
            const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches;
            const resolved = saved === 'light' || saved === 'dark' ? saved : (prefersDark ? 'dark' : 'light');
            if (resolved === 'dark') {
                document.documentElement.classList.add('theme-dark');
            }
        })();
    </script>
    <style>
        :root {
            color-scheme: light;
            --ink: #0c2230;
            --muted: #65747c;
            --line: #dce7eb;
            --wash: #f3f8fa;
            --card: #ffffff;
            --field: #f8fbfc;
            --nav-surface: rgba(255,255,255,.94);
            --topbar-surface: rgba(243, 248, 250, .92);
            --brand: #0c2230;
            --brand-2: #0f4b3d;
            --gold: #f8b522;
            --gold-2: #fff5db;
            --ok: #167a5b;
            --danger: #b42318;
            --shadow: 0 18px 50px rgba(12, 34, 48, .12);
            --soft-shadow: 0 12px 28px rgba(12, 34, 48, .08);
            --radius: 28px;
            --bottom-nav: 84px;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        html.theme-dark, body.theme-dark {
            color-scheme: dark;
            --ink: #f4fbff;
            --muted: #a8b8c1;
            --line: #24404c;
            --wash: #07151d;
            --card: #102633;
            --field: #0b1d27;
            --nav-surface: rgba(12, 28, 38, .96);
            --topbar-surface: rgba(7, 21, 29, .92);
            --brand: #f8b522;
            --brand-2: #f8b522;
            --gold-2: rgba(248,181,34,.16);
            --shadow: 0 18px 50px rgba(0, 0, 0, .38);
            --soft-shadow: 0 12px 28px rgba(0, 0, 0, .22);
        }

        * { box-sizing: border-box; }
        html, body { min-height: 100%; }
        body {
            margin: 0;
            background: var(--wash);
            color: var(--ink);
            overflow-x: hidden;
            transition: background-color .18s ease, color .18s ease;
        }

        a { color: inherit; }
        button, input, select, textarea { font: inherit; }
        [hidden] { display: none !important; }

        .toast {
            position: fixed;
            left: 18px;
            right: 18px;
            bottom: calc(var(--bottom-nav) + env(safe-area-inset-bottom) + 14px);
            z-index: 60;
            border-radius: 18px;
            padding: 14px 16px;
            background: #17252d;
            color: #fff;
            box-shadow: var(--shadow);
        }
        .toast.error { background: #3a1515; }

        .auth-shell {
            min-height: 100vh;
            padding: max(18px, env(safe-area-inset-top)) 18px max(24px, env(safe-area-inset-bottom));
            display: grid;
            align-items: center;
            justify-items: center;
        }

        .auth-card {
            width: min(100%, 520px);
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 34px;
            box-shadow: var(--shadow);
            padding: 28px;
        }

        .auth-logo, .brand-logo {
            width: 78px;
            height: 78px;
            object-fit: cover;
            border-radius: 24px;
            box-shadow: 0 12px 30px rgba(12, 34, 48, .16);
            background: var(--card);
        }

        .auth-head {
            display: grid;
            justify-items: center;
            gap: 12px;
            text-align: center;
            margin-bottom: 22px;
        }

        .eyebrow {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        h1, h2, h3, p { margin-top: 0; }
        h1 {
            margin-bottom: 0;
            font-size: clamp(30px, 8vw, 44px);
            line-height: 1.02;
            letter-spacing: 0;
        }

        .auth-head p:not(.eyebrow), .muted { color: var(--muted); line-height: 1.6; }

        .segmented {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
            padding: 6px;
            border-radius: 18px;
            background: var(--field);
            margin: 18px 0;
        }

        .segmented button, .ghost-button, .button, .icon-button {
            min-height: 48px;
            border: 0;
            border-radius: 15px;
            cursor: pointer;
            font-weight: 900;
        }

        .segmented button {
            background: transparent;
            color: var(--muted);
        }
        .segmented button.active {
            background: var(--card);
            color: var(--ink);
            box-shadow: var(--soft-shadow);
        }

        .form { display: grid; gap: 14px; }
        .field { display: grid; gap: 7px; }
        .field label {
            color: var(--muted);
            font-weight: 800;
            font-size: 14px;
        }
        .input {
            width: 100%;
            min-height: 52px;
            border: 1px solid var(--line);
            border-radius: 17px;
            background: var(--field);
            color: var(--ink);
            padding: 0 16px;
            outline: none;
        }
        textarea.input {
            min-height: 110px;
            padding-top: 14px;
            resize: vertical;
        }
        .input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 4px rgba(248, 181, 34, .18);
        }

        .form-grid {
            display: grid;
            gap: 14px;
        }

        .button {
            width: 100%;
            background: var(--gold);
            color: var(--ink);
            padding: 0 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }
        .button.dark { background: var(--brand); color: #fff; }
        html.theme-dark .button.dark, body.theme-dark .button.dark { color: #07151d; }
        .button.subtle { background: var(--field); color: var(--ink); }
        .button.outline {
            background: var(--card);
            border: 1px solid var(--line);
            color: var(--ink);
        }
        .button.small {
            min-height: 42px;
            width: auto;
            padding: 0 14px;
            border-radius: 13px;
        }
        .link-button {
            border: 0;
            background: transparent;
            color: var(--brand);
            font-weight: 900;
            cursor: pointer;
            min-height: 44px;
            padding: 0;
        }

        .inline-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .notice {
            border-radius: 16px;
            padding: 13px 14px;
            background: #eef7f3;
            color: #12563f;
            font-weight: 800;
            line-height: 1.45;
        }
        .notice.error {
            background: #fff0ef;
            color: var(--danger);
        }

        .social-auth-panel {
            display: grid;
            gap: 12px;
            margin: 16px 0 18px;
            text-align: center;
        }
        .auth-divider {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 12px;
            align-items: center;
            color: var(--muted);
            font-size: 13px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .auth-divider::before,
        .auth-divider::after {
            content: "";
            height: 1px;
            background: var(--line);
        }
        .google-button-shell {
            min-height: 44px;
            display: grid;
            justify-items: center;
            align-items: center;
            max-width: 100%;
        }
        .google-button-shell > div { max-width: 100%; }
        .google-button-shell.busy {
            pointer-events: none;
            opacity: .62;
        }
        .social-auth-help {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
        }

        .theme-mode-switch {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px;
            padding: 6px;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: var(--field);
            margin: -8px 0 18px;
        }
        .sidebar .theme-mode-switch {
            border-color: rgba(255,255,255,.16);
            background: rgba(255,255,255,.08);
        }
        .theme-mode-switch button {
            min-height: 40px;
            border: 0;
            border-radius: 13px;
            background: transparent;
            color: var(--muted);
            display: inline-grid;
            place-items: center;
            cursor: pointer;
            font-weight: 1000;
        }
        .sidebar .theme-mode-switch button {
            color: rgba(255,255,255,.72);
        }
        .theme-mode-switch button.active {
            background: var(--brand);
            color: #fff;
            box-shadow: var(--soft-shadow);
        }
        html.theme-dark .theme-mode-switch button.active,
        body.theme-dark .theme-mode-switch button.active {
            color: #07151d;
        }

        .auth-footer {
            margin-top: 18px;
            display: flex;
            justify-content: center;
            gap: 16px;
            color: var(--muted);
            font-size: 14px;
            font-weight: 800;
        }

        .portal-shell {
            min-height: 100vh;
            display: grid;
        }

        .mobile-topbar {
            position: sticky;
            top: 0;
            z-index: 30;
            height: calc(68px + env(safe-area-inset-top));
            padding: env(safe-area-inset-top) 18px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--topbar-surface);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(220, 231, 235, .85);
        }

        .icon-button {
            width: 48px;
            background: var(--card);
            color: var(--ink);
            border: 1px solid var(--line);
            display: inline-grid;
            place-items: center;
        }
        .hamburger, .hamburger::before, .hamburger::after {
            display: block;
            width: 20px;
            height: 2px;
            border-radius: 99px;
            background: currentColor;
        }
        .hamburger { position: relative; }
        .hamburger::before, .hamburger::after {
            content: "";
            position: absolute;
            left: 0;
        }
        .hamburger::before { top: -6px; }
        .hamburger::after { top: 6px; }

        .top-title { display: grid; gap: 1px; text-align: center; }
        .top-title strong { font-size: 17px; }
        .top-title span { color: var(--muted); font-size: 12px; font-weight: 800; }

        .sidebar {
            display: none;
            background: linear-gradient(180deg, #0c2230, #113d35);
            color: #fff;
            min-height: 100vh;
            padding: 22px;
            position: sticky;
            top: 0;
        }

        .sidebar-brand, .drawer-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
        }
        .brand-logo { width: 54px; height: 54px; border-radius: 17px; }
        .sidebar-brand strong, .drawer-brand strong { display: block; font-size: 17px; }
        .sidebar-brand span, .drawer-brand span { color: rgba(255,255,255,.68); font-size: 12px; font-weight: 800; }
        .drawer-brand span { color: var(--muted); }

        .user-chip {
            border: 1px solid rgba(255,255,255,.16);
            background: rgba(255,255,255,.08);
            border-radius: 22px;
            padding: 14px;
            margin-bottom: 20px;
        }
        .user-chip strong { display: block; }
        .user-chip span { color: rgba(255,255,255,.7); font-size: 13px; overflow-wrap: anywhere; }

        .nav-list {
            display: grid;
            gap: 8px;
        }
        .nav-item {
            min-height: 48px;
            border: 0;
            border-radius: 16px;
            padding: 0 14px;
            background: transparent;
            color: inherit;
            display: flex;
            align-items: center;
            gap: 12px;
            text-align: left;
            font-weight: 900;
            cursor: pointer;
        }
        .nav-item:hover, .nav-item.active {
            background: rgba(248, 181, 34, .16);
            color: #fff;
        }
        .nav-icon {
            width: 26px;
            height: 26px;
            display: inline-block;
            opacity: .9;
            flex: 0 0 auto;
            stroke-width: 2.2;
        }

        .portal-main {
            width: min(100%, 1180px);
            margin: 0 auto;
            padding: 20px 18px calc(var(--bottom-nav) + env(safe-area-inset-bottom) + 24px);
        }

        .page-view { display: none; animation: fade .18s ease-out; }
        .page-view.active { display: grid; gap: 18px; }
        @keyframes fade { from { opacity: .65; transform: translateY(4px); } to { opacity: 1; transform: none; } }

        .hero-card {
            background: radial-gradient(circle at 85% 12%, rgba(248,181,34,.24), transparent 28%), linear-gradient(135deg, #0c2230, #0f4b3d);
            color: #fff;
            border-radius: 30px;
            padding: 24px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .hero-card h2 {
            margin-bottom: 8px;
            font-size: clamp(28px, 8vw, 44px);
            line-height: 1.04;
        }
        .hero-card p { color: rgba(255,255,255,.78); margin-bottom: 0; line-height: 1.55; }

        .section-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 14px;
        }
        .section-head h2 { margin-bottom: 4px; font-size: clamp(24px, 6vw, 34px); }
        .section-head p { margin: 0; color: var(--muted); line-height: 1.5; }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--soft-shadow);
        }
        .card h3 { font-size: 22px; margin-bottom: 8px; }

        .grid {
            display: grid;
            gap: 14px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .stat {
            background: var(--field);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 16px;
            min-width: 0;
        }
        .stat span {
            display: block;
            color: var(--muted);
            font-weight: 900;
            font-size: 13px;
        }
        .stat strong {
            display: block;
            margin-top: 5px;
            font-size: clamp(22px, 6vw, 30px);
            line-height: 1.1;
            overflow-wrap: anywhere;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            border-radius: 999px;
            padding: 0 12px;
            background: var(--gold-2);
            color: #6f4c00;
            font-size: 13px;
            font-weight: 900;
            text-transform: capitalize;
        }
        .badge.ok { background: #e8f7f1; color: var(--ok); }
        .badge.danger { background: #fff0ef; color: var(--danger); }

        .event-media {
            margin: -20px -20px 18px;
            min-height: 190px;
            background: linear-gradient(135deg, #eaf4f2, #fff6de);
            border-radius: var(--radius) var(--radius) 0 0;
            overflow: hidden;
        }
        .event-media img {
            width: 100%;
            height: 100%;
            min-height: 190px;
            object-fit: cover;
            display: block;
        }

        .detail-list {
            display: grid;
            gap: 10px;
            margin-top: 12px;
        }
        .detail-row {
            display: grid;
            gap: 3px;
            padding: 13px 0;
            border-top: 1px solid var(--line);
        }
        .detail-row:first-child { border-top: 0; padding-top: 0; }
        .detail-row span, .item-meta { color: var(--muted); line-height: 1.5; }

        .attendee-card {
            background: var(--field);
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 16px;
            display: grid;
            gap: 14px;
        }

        .choice-grid {
            display: grid;
            gap: 10px;
        }
        .choice {
            min-height: 50px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--card);
            padding: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .choice img {
            width: 62px;
            height: 48px;
            object-fit: cover;
            border-radius: 12px;
        }
        .swatch {
            width: 26px;
            height: 26px;
            border-radius: 99px;
            border: 1px solid var(--line);
            display: inline-block;
        }

        .record-list {
            display: grid;
            gap: 12px;
        }
        .record {
            border: 1px solid var(--line);
            background: var(--field);
            border-radius: 22px;
            padding: 16px;
            display: grid;
            gap: 12px;
        }
        .record-top {
            display: flex;
            align-items: start;
            justify-content: space-between;
            gap: 12px;
        }
        .record-title {
            display: grid;
            gap: 4px;
            min-width: 0;
        }
        .record-title strong {
            font-size: 18px;
            overflow-wrap: anywhere;
        }
        .record-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .wallet-balance-card {
            background: radial-gradient(circle at 84% 14%, rgba(248,181,34,.22), transparent 30%), linear-gradient(135deg, #0c2230, #0f4b3d);
            color: #fff;
            border: 0;
        }
        .wallet-balance-card .muted { color: rgba(255,255,255,.76); }
        .wallet-balance-card .wallet-balance {
            display: block;
            margin: 10px 0 4px;
            font-size: clamp(38px, 10vw, 56px);
            line-height: 1;
            font-weight: 1000;
            overflow-wrap: anywhere;
        }
        .wallet-actions-grid {
            display: grid;
            gap: 14px;
        }
        .wallet-tabs {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            padding: 6px;
            border: 1px solid var(--line);
            border-radius: 20px;
            background: var(--card);
            box-shadow: var(--soft-shadow);
        }
        .wallet-tab {
            min-height: 46px;
            border: 0;
            border-radius: 15px;
            background: transparent;
            color: var(--muted);
            font-weight: 1000;
            cursor: pointer;
        }
        .wallet-tab.active {
            background: var(--brand);
            color: #fff;
            box-shadow: var(--soft-shadow);
        }
        html.theme-dark .wallet-tab.active, body.theme-dark .wallet-tab.active {
            color: #07151d;
        }
        .wallet-panel {
            display: none;
            gap: 14px;
        }
        .wallet-panel.active {
            display: grid;
        }
        .wallet-mini-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .progress-track {
            height: 10px;
            border-radius: 99px;
            background: #e8f0f3;
            overflow: hidden;
        }
        .progress-track span {
            display: block;
            height: 100%;
            width: var(--progress, 0%);
            border-radius: inherit;
            background: var(--gold);
        }
        .wallet-ledger-row {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 12px;
            align-items: center;
        }
        .wallet-direction {
            width: 38px;
            height: 38px;
            display: inline-grid;
            place-items: center;
            border-radius: 14px;
            background: #e8f7f1;
            color: var(--ok);
            font-weight: 1000;
        }
        .wallet-direction.debit {
            background: #fff0ef;
            color: var(--danger);
        }
        .wallet-amount {
            font-weight: 1000;
            white-space: nowrap;
        }

        .qr-holder {
            width: 152px;
            min-height: 152px;
            border: 1px dashed var(--line);
            border-radius: 18px;
            display: grid;
            place-items: center;
            background: #fff;
            overflow: hidden;
        }
        .qr-holder img { width: 100%; height: auto; display: block; }
        .ticket-card {
            gap: 18px;
        }
        .ticket-summary {
            display: grid;
            gap: 8px;
        }
        .ticket-summary strong {
            font-size: clamp(22px, 6vw, 30px);
            line-height: 1.12;
            overflow-wrap: anywhere;
        }
        .ticket-qr-stage {
            display: grid;
            justify-items: center;
            gap: 10px;
            padding: 16px;
            border: 1px solid var(--line);
            border-radius: 24px;
            background: var(--card);
        }
        .ticket-qr-stage .qr-holder {
            width: min(78vw, 320px);
            min-height: min(78vw, 320px);
            border-radius: 22px;
            border-style: solid;
            box-shadow: 0 14px 34px rgba(12, 34, 48, .12);
        }
        .ticket-qr-stage p {
            margin: 0;
            color: var(--muted);
            font-weight: 900;
            text-align: center;
        }
        .ticket-details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .ticket-detail {
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 12px;
            background: var(--card);
            min-width: 0;
        }
        .ticket-detail span {
            display: block;
            color: var(--muted);
            font-size: 12px;
            font-weight: 900;
            margin-bottom: 4px;
        }
        .ticket-detail strong {
            display: block;
            overflow-wrap: anywhere;
        }
        .profile-form-grid {
            display: grid;
            gap: 14px;
        }
        .profile-section-title {
            margin: 10px 0 0;
            font-size: 18px;
        }

        .empty {
            border: 1px dashed var(--line);
            border-radius: 22px;
            padding: 22px;
            background: var(--card);
            color: var(--muted);
            line-height: 1.6;
            font-weight: 800;
        }

        .drawer-backdrop {
            position: fixed;
            inset: 0;
            z-index: 50;
            background: rgba(12, 34, 48, .42);
        }
        .drawer {
            width: min(86vw, 360px);
            height: 100%;
            background: var(--card);
            padding: calc(18px + env(safe-area-inset-top)) 18px 18px;
            box-shadow: var(--shadow);
            transform: translateX(0);
            display: grid;
            grid-template-rows: auto auto 1fr auto;
            gap: 12px;
        }
        .drawer .user-chip {
            color: var(--ink);
            background: var(--field);
            border-color: var(--line);
        }
        .drawer .user-chip span { color: var(--muted); }
        .drawer .nav-item { color: var(--ink); }
        .drawer .nav-item.active, .drawer .nav-item:hover {
            background: var(--field);
            color: var(--brand-2);
        }

        .bottom-nav {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 35;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 4px;
            padding: 8px 10px calc(8px + env(safe-area-inset-bottom));
            background: var(--nav-surface);
            border-top: 1px solid var(--line);
            backdrop-filter: blur(18px);
        }
        .bottom-nav button {
            min-height: 58px;
            border: 0;
            border-radius: 18px;
            background: transparent;
            color: var(--muted);
            display: grid;
            place-items: center;
            gap: 2px;
            font-weight: 900;
            font-size: 12px;
            cursor: pointer;
        }
        .bottom-nav button.active {
            background: var(--brand);
            color: #fff;
        }
        html.theme-dark .bottom-nav button.active, body.theme-dark .bottom-nav button.active {
            color: #07151d;
        }

        @media (max-width: 430px) {
            .wallet-tabs {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .ticket-details {
                grid-template-columns: 1fr;
            }
        }

        @media (min-width: 620px) {
            .form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .stats-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            .grid.two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .ticket-qr-stage .qr-holder {
                width: 340px;
                min-height: 340px;
            }
        }

        @media (min-width: 980px) {
            .portal-shell {
                grid-template-columns: 292px minmax(0, 1fr);
            }
            .sidebar { display: block; }
            .mobile-topbar, .bottom-nav { display: none; }
            .portal-main {
                padding: 34px 30px 56px;
                margin: 0;
            }
            .page-view.active { gap: 24px; }
            .toast {
                left: auto;
                right: 28px;
                bottom: 28px;
                width: min(420px, calc(100vw - 56px));
            }
        }
    </style>
</head>
<body>
    <div id="toast" class="toast" role="status" hidden></div>
    <svg aria-hidden="true" width="0" height="0" style="position:absolute;overflow:hidden">
        <symbol id="icon-home" viewBox="0 0 24 24">
            <path d="M3 10.6 12 3l9 7.6v9.1a1.3 1.3 0 0 1-1.3 1.3h-5.2v-6.2h-5V21H4.3A1.3 1.3 0 0 1 3 19.7z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        <symbol id="icon-calendar" viewBox="0 0 24 24">
            <rect x="3" y="5" width="18" height="16" rx="2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M8 3v4M16 3v4M3 10h18M8 15l2.2 2.2L16 12" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        <symbol id="icon-ticket" viewBox="0 0 24 24">
            <path d="M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v2a2.5 2.5 0 0 0 0 5v2A2.5 2.5 0 0 1 17.5 19h-11A2.5 2.5 0 0 1 4 16.5v-2a2.5 2.5 0 0 0 0-5zM10 8v8M14 8v8" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        <symbol id="icon-card" viewBox="0 0 24 24">
            <rect x="3" y="5" width="18" height="14" rx="2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M3 10h18M7 15h4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        <symbol id="icon-wallet" viewBox="0 0 24 24">
            <path d="M4 7.5h14A2.5 2.5 0 0 1 20.5 10v7A2.5 2.5 0 0 1 18 19.5H5.5A2.5 2.5 0 0 1 3 17V7.7A2.7 2.7 0 0 1 5.7 5H17" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M16 12.2h4.5v3.6H16a1.8 1.8 0 1 1 0-3.6z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        <symbol id="icon-receipt" viewBox="0 0 24 24">
            <path d="M6 3h12v18l-2-1.2-2 1.2-2-1.2-2 1.2-2-1.2L6 21zM9 8h6M9 12h6M9 16h3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        <symbol id="icon-bell" viewBox="0 0 24 24">
            <path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        <symbol id="icon-user" viewBox="0 0 24 24">
            <circle cx="12" cy="8" r="4" fill="none" stroke="currentColor"/>
            <path d="M4.5 21a7.5 7.5 0 0 1 15 0" fill="none" stroke="currentColor" stroke-linecap="round"/>
        </symbol>
        <symbol id="icon-help" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor"/>
            <path d="M9.7 9a2.5 2.5 0 1 1 4.1 1.9c-.9.7-1.8 1.3-1.8 2.6M12 17h.01" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
    </svg>

    <section id="authShell" class="auth-shell">
        <div class="auth-card">
            <div class="auth-head">
                <img class="auth-logo" src="/favicon.png" alt="MFM Triumphant Church">
                <p class="eyebrow">MFM Triumphant Church</p>
                <h1>Welcome to Goshen Retreat</h1>
                <p>Sign in or create your account to manage registration, tickets, payments, and retreat updates.</p>
            </div>

            <div class="segmented" role="tablist" aria-label="Member access">
                <button type="button" data-auth-tab="login" class="active">Sign in</button>
                <button type="button" data-auth-tab="register">Register</button>
                <button type="button" data-auth-tab="verify">Verify</button>
            </div>

            <div id="authNotice" class="notice" hidden></div>

            <div id="googleAuthPanel" class="social-auth-panel" hidden>
                <div class="auth-divider"><span>Continue with</span></div>
                <div id="googleSignInButton" class="google-button-shell"></div>
                <p class="social-auth-help">Use your Google account to sign in or create your Goshen portal profile.</p>
            </div>

            <form id="loginForm" class="form" autocomplete="on">
                <div class="field">
                    <label for="loginEmail">Email address</label>
                    <input id="loginEmail" class="input" name="email" type="email" autocomplete="email" required>
                </div>
                <div class="field">
                    <label for="loginPassword">Password</label>
                    <input id="loginPassword" class="input" name="password" type="password" autocomplete="current-password" required>
                </div>
                <div class="inline-actions">
                    <a class="link-button" href="/app/forgot-password" data-auth-tab="forgot">Forgot password?</a>
                </div>
                <button class="button dark" type="submit">Sign in</button>
            </form>

            <form id="registerForm" class="form" autocomplete="on" hidden>
                <div class="form-grid">
                    <div class="field">
                        <label for="registerFirstName">First name</label>
                        <input id="registerFirstName" class="input" name="first_name" autocomplete="given-name" required>
                    </div>
                    <div class="field">
                        <label for="registerLastName">Last name</label>
                        <input id="registerLastName" class="input" name="last_name" autocomplete="family-name" required>
                    </div>
                    <div class="field">
                        <label for="registerEmail">Email address</label>
                        <input id="registerEmail" class="input" name="email" type="email" autocomplete="email" required>
                    </div>
                    <div class="field">
                        <label for="registerPhone">Phone number</label>
                        <input id="registerPhone" class="input" name="phone" type="tel" autocomplete="tel" required>
                    </div>
                    <div class="field">
                        <label for="registerGender">Gender</label>
                        <select id="registerGender" class="input" name="gender" required>
                            <option value="">Please select</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="registerGroup">Church group</label>
                        <select id="registerGroup" class="input" name="group_id" required>
                            <option value="">Loading church groups...</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="registerMemberType">Member type</label>
                        <select id="registerMemberType" class="input" name="member_type" required>
                            <option value="church_member">Church member</option>
                            <option value="visitor">Visitor</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="registerCountry">Country of residence</label>
                        <input id="registerCountry" class="input" name="country_of_residence" autocomplete="country-name" required>
                    </div>
                    <div class="field">
                        <label for="registerState">State/county/province</label>
                        <input id="registerState" class="input" name="state_county_province" autocomplete="address-level1" required>
                    </div>
                    <div class="field">
                        <label for="registerAddress">Address</label>
                        <input id="registerAddress" class="input" name="address" autocomplete="street-address" required>
                    </div>
                    <div class="field">
                        <label for="registerPassword">Password</label>
                        <input id="registerPassword" class="input" name="password" type="password" autocomplete="new-password" minlength="8" required>
                    </div>
                    <div class="field">
                        <label for="registerPasswordConfirm">Confirm password</label>
                        <input id="registerPasswordConfirm" class="input" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
                    </div>
                </div>
                <button class="button dark" type="submit">Create account</button>
            </form>

            <form id="verifyForm" class="form" autocomplete="one-time-code" hidden>
                <div class="field">
                    <label for="verifyEmail">Email address</label>
                    <input id="verifyEmail" class="input" name="email" type="email" autocomplete="email" required>
                </div>
                <div class="field">
                    <label for="verifyCode">Verification code</label>
                    <input id="verifyCode" class="input" name="code" inputmode="numeric" autocomplete="one-time-code" required>
                </div>
                <button class="button dark" type="submit">Verify account</button>
                <button id="resendCode" class="button outline" type="button">Resend code</button>
            </form>

            <form id="forgotForm" class="form" autocomplete="on" hidden>
                <div class="field">
                    <label for="forgotEmail">Email address</label>
                    <input id="forgotEmail" class="input" name="email" type="email" autocomplete="email" required>
                </div>
                <button class="button dark" type="submit">Send reset code</button>
                <button class="button outline" type="button" data-auth-tab="login">Back to sign in</button>
            </form>

            <form id="resetForm" class="form" autocomplete="on" hidden>
                <div class="field">
                    <label for="resetEmail">Email address</label>
                    <input id="resetEmail" class="input" name="email" type="email" autocomplete="email" required>
                </div>
                <div class="field">
                    <label for="resetCode">Reset code</label>
                    <input id="resetCode" class="input" name="code" inputmode="numeric" autocomplete="one-time-code" required>
                </div>
                <div class="field">
                    <label for="resetPassword">New password</label>
                    <input id="resetPassword" class="input" name="password" type="password" autocomplete="new-password" minlength="8" required>
                </div>
                <button class="button dark" type="submit">Reset password</button>
                <button class="button outline" type="button" data-auth-tab="login">Back to sign in</button>
            </form>

            <div class="auth-footer">
                <a href="/privacy">Privacy</a>
                <a href="/terms">Terms</a>
                <a href="/aboutus">Support</a>
            </div>
        </div>
    </section>

    <div id="portalShell" class="portal-shell" hidden>
        <aside class="sidebar" aria-label="Portal navigation">
            <div class="sidebar-brand">
                <img class="brand-logo" src="/favicon.png" alt="">
                <div>
                    <strong>Goshen Retreat</strong>
                    <span>User portal</span>
                </div>
            </div>
            <div class="user-chip">
                <strong id="sidebarUserName">Member</strong>
                <span id="sidebarUserEmail">Signed in</span>
            </div>
            <div class="theme-mode-switch" role="radiogroup" aria-label="Theme preference">
                <button type="button" data-theme-mode="light" aria-label="Use light mode">Sun</button>
                <button type="button" data-theme-mode="dark" aria-label="Use dark mode">Moon</button>
                <button type="button" data-theme-mode="device" aria-label="Use device theme">Auto</button>
            </div>
            <nav class="nav-list">
                <button class="nav-item active" type="button" data-nav-page="home"><svg class="nav-icon" aria-hidden="true"><use href="#icon-home"></use></svg>Home</button>
                <button class="nav-item" type="button" data-nav-page="retreat"><svg class="nav-icon" aria-hidden="true"><use href="#icon-calendar"></use></svg>Retreat Registration</button>
                <button class="nav-item" type="button" data-nav-page="tickets"><svg class="nav-icon" aria-hidden="true"><use href="#icon-ticket"></use></svg>My Ticket</button>
                <button class="nav-item" type="button" data-nav-page="payments"><svg class="nav-icon" aria-hidden="true"><use href="#icon-card"></use></svg>Payments</button>
                <button class="nav-item" type="button" data-nav-page="wallet"><svg class="nav-icon" aria-hidden="true"><use href="#icon-wallet"></use></svg>Wallet</button>
                <button class="nav-item" type="button" data-nav-page="receipts"><svg class="nav-icon" aria-hidden="true"><use href="#icon-receipt"></use></svg>Receipts</button>
                <button class="nav-item" type="button" data-nav-page="updates"><svg class="nav-icon" aria-hidden="true"><use href="#icon-bell"></use></svg>Updates</button>
                <button class="nav-item" type="button" data-nav-page="profile"><svg class="nav-icon" aria-hidden="true"><use href="#icon-user"></use></svg>Profile</button>
                <button class="nav-item" type="button" data-nav-page="support"><svg class="nav-icon" aria-hidden="true"><use href="#icon-help"></use></svg>Support</button>
            </nav>
            <div style="margin-top:24px">
                <button id="sidebarLogout" class="button outline" type="button">Sign out</button>
            </div>
        </aside>

        <header class="mobile-topbar">
            <button id="openDrawer" class="icon-button" type="button" aria-label="Open navigation menu"><span class="hamburger"></span></button>
            <div class="top-title">
                <strong id="mobileTitle">Home</strong>
                <span>Goshen Retreat</span>
            </div>
            <button class="icon-button" type="button" data-nav-page="profile" aria-label="Open profile"><svg class="nav-icon" aria-hidden="true"><use href="#icon-user"></use></svg></button>
        </header>

        <div id="drawerBackdrop" class="drawer-backdrop" hidden>
            <div class="drawer" role="dialog" aria-modal="true" aria-label="Portal navigation">
                <div class="drawer-brand">
                    <img class="brand-logo" src="/favicon.png" alt="">
                    <div>
                        <strong>Goshen Retreat</strong>
                        <span>User portal</span>
                    </div>
                </div>
                <div class="user-chip">
                    <strong id="drawerUserName">Member</strong>
                    <span id="drawerUserEmail">Signed in</span>
                </div>
                <div class="theme-mode-switch" role="radiogroup" aria-label="Theme preference">
                    <button type="button" data-theme-mode="light" aria-label="Use light mode">Sun</button>
                    <button type="button" data-theme-mode="dark" aria-label="Use dark mode">Moon</button>
                    <button type="button" data-theme-mode="device" aria-label="Use device theme">Auto</button>
                </div>
                <nav class="nav-list">
                    <button class="nav-item active" type="button" data-nav-page="home"><svg class="nav-icon" aria-hidden="true"><use href="#icon-home"></use></svg>Home</button>
                    <button class="nav-item" type="button" data-nav-page="retreat"><svg class="nav-icon" aria-hidden="true"><use href="#icon-calendar"></use></svg>Retreat Registration</button>
                    <button class="nav-item" type="button" data-nav-page="tickets"><svg class="nav-icon" aria-hidden="true"><use href="#icon-ticket"></use></svg>My Ticket</button>
                    <button class="nav-item" type="button" data-nav-page="payments"><svg class="nav-icon" aria-hidden="true"><use href="#icon-card"></use></svg>Payments</button>
                    <button class="nav-item" type="button" data-nav-page="wallet"><svg class="nav-icon" aria-hidden="true"><use href="#icon-wallet"></use></svg>Wallet</button>
                    <button class="nav-item" type="button" data-nav-page="receipts"><svg class="nav-icon" aria-hidden="true"><use href="#icon-receipt"></use></svg>Receipts</button>
                    <button class="nav-item" type="button" data-nav-page="updates"><svg class="nav-icon" aria-hidden="true"><use href="#icon-bell"></use></svg>Updates</button>
                    <button class="nav-item" type="button" data-nav-page="profile"><svg class="nav-icon" aria-hidden="true"><use href="#icon-user"></use></svg>Profile</button>
                    <button class="nav-item" type="button" data-nav-page="support"><svg class="nav-icon" aria-hidden="true"><use href="#icon-help"></use></svg>Support</button>
                </nav>
                <button id="drawerLogout" class="button dark" type="button">Sign out</button>
            </div>
        </div>

        <main class="portal-main" id="portalMain">
            <section class="page-view active" data-page-view="home">
                <div class="hero-card">
                    <p class="eyebrow">Member dashboard</p>
                    <h2 id="homeGreeting">Welcome to Goshen Retreat</h2>
                    <p>Manage your registration, tickets, payments, receipts, and retreat updates in one secure place.</p>
                </div>
                <div id="homeStats" class="stats-grid"></div>
                <div id="homeNextAction" class="card"></div>
                <div id="homeEventPreview" class="card"></div>
            </section>

            <section class="page-view" data-page-view="retreat">
                <div class="section-head">
                    <div>
                        <h2>Retreat Registration</h2>
                        <p>Choose a published Goshen Retreat edition and register each attendee.</p>
                    </div>
                </div>
                <div id="retreatEvents" class="grid"></div>
            </section>

            <section class="page-view" data-page-view="tickets">
                <div class="section-head">
                    <div>
                        <h2>My Ticket</h2>
                        <p>View issued tickets, QR codes, and downloadable documents.</p>
                    </div>
                </div>
                <div id="ticketsList" class="record-list"></div>
            </section>

            <section class="page-view" data-page-view="payments">
                <div class="section-head">
                    <div>
                        <h2>Payments</h2>
                        <p>Continue pending card payments, use wallet funds, or redeem a voucher where available.</p>
                    </div>
                </div>
                <div id="paymentsList" class="record-list"></div>
            </section>

            <section class="page-view" data-page-view="wallet">
                <div class="section-head">
                    <div>
                        <h2>Goshen Wallet</h2>
                        <p>Top up, save, transfer, withdraw, and review your wallet activity.</p>
                    </div>
                </div>
                <div id="walletContent" class="grid"></div>
            </section>

            <section class="page-view" data-page-view="receipts">
                <div class="section-head">
                    <div>
                        <h2>Receipts</h2>
                        <p>Review your Goshen Retreat payment records and references.</p>
                    </div>
                </div>
                <div id="receiptsList" class="record-list"></div>
            </section>

            <section class="page-view" data-page-view="updates">
                <div class="section-head">
                    <div>
                        <h2>Updates</h2>
                        <p>Important retreat notices and account messages.</p>
                    </div>
                </div>
                <div id="updatesList" class="record-list"></div>
            </section>

            <section class="page-view" data-page-view="profile">
                <div class="section-head">
                    <div>
                        <h2>Profile</h2>
                        <p>Details used for your retreat registration and payment records.</p>
                    </div>
                </div>
                <div id="profileCard" class="card"></div>
            </section>

            <section class="page-view" data-page-view="support">
                <div class="section-head">
                    <div>
                        <h2>Support</h2>
                        <p>Use the published retreat support details when you need help.</p>
                    </div>
                </div>
                <div id="supportCard" class="card"></div>
            </section>
        </main>

        <nav class="bottom-nav" aria-label="Primary navigation">
            <button class="active" type="button" data-nav-page="home"><svg class="nav-icon" aria-hidden="true"><use href="#icon-home"></use></svg><span>Home</span></button>
            <button type="button" data-nav-page="retreat"><svg class="nav-icon" aria-hidden="true"><use href="#icon-calendar"></use></svg><span>Retreat</span></button>
            <button type="button" data-nav-page="tickets"><svg class="nav-icon" aria-hidden="true"><use href="#icon-ticket"></use></svg><span>Tickets</span></button>
            <button type="button" data-nav-page="payments"><svg class="nav-icon" aria-hidden="true"><use href="#icon-card"></use></svg><span>Payments</span></button>
        </nav>
    </div>

    <script>
        const storageKey = 'goshen_member_user';
        const themeKey = 'goshen_portal_theme';
        const walletTabKey = 'goshen_wallet_tab';
        const googleLoginConfig = @json($googleLogin ?? ['enabled' => false, 'clientId' => '']);
        const pageTitles = {
            home: 'Home',
            retreat: 'Retreat',
            tickets: 'Tickets',
            payments: 'Payments',
            wallet: 'Wallet',
            receipts: 'Receipts',
            updates: 'Updates',
            profile: 'Profile',
            support: 'Support',
        };

        let currentUser = null;
        let eventsCache = [];
        let memberData = { registrations: [], payment_history: [], tickets: [], accommodation_allocations: [] };
        let walletData = null;
        let activePage = 'home';
        let activeWalletTab = localStorage.getItem(walletTabKey) || 'overview';
        let churchGroupsCache = [];
        let handledReturnNotice = false;

        const authShell = document.getElementById('authShell');
        const portalShell = document.getElementById('portalShell');
        const authNotice = document.getElementById('authNotice');
        const toast = document.getElementById('toast');
        const drawerBackdrop = document.getElementById('drawerBackdrop');
        const googleAuthPanel = document.getElementById('googleAuthPanel');
        const googleSignInButton = document.getElementById('googleSignInButton');
        let googleIdentityLoading = null;
        let googleIdentityReady = false;

        function escapeHtml(value) {
            return `${value ?? ''}`.replace(/[&<>"']/g, (char) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
            }[char]));
        }

        function payloadFromForm(form) {
            return Object.fromEntries(new FormData(form).entries());
        }

        function messageFromErrorPayload(payload, fallback) {
            return payload?.message || payload?.msg || Object.values(payload?.errors || {})?.flat?.()?.[0] || fallback;
        }

        async function apiPost(url, data = {}) {
            const response = await fetch(url, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ data }),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload.status === 'error') {
                throw new Error(messageFromErrorPayload(payload, 'Request failed. Please try again.'));
            }
            return payload;
        }

        async function apiGet(url) {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload.status === 'error') {
                throw new Error(messageFromErrorPayload(payload, 'Request failed. Please try again.'));
            }
            return payload;
        }

        function authPayload(extra = {}) {
            return { ...extra, email: currentUser?.email || '', api_token: currentUser?.api_token || '' };
        }

        function savedThemeMode() {
            const saved = localStorage.getItem(themeKey);
            return ['light', 'dark', 'device'].includes(saved) ? saved : 'device';
        }

        function resolvedTheme(mode = savedThemeMode()) {
            if (mode === 'light' || mode === 'dark') return mode;
            return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        function applyTheme(mode = savedThemeMode()) {
            const resolved = resolvedTheme(mode);
            const isDark = resolved === 'dark';
            document.documentElement.classList.toggle('theme-dark', isDark);
            document.body.classList.toggle('theme-dark', isDark);
            document.querySelector('meta[name="theme-color"]')?.setAttribute('content', isDark ? '#07151d' : '#0c2230');
            document.querySelectorAll('[data-theme-mode]').forEach((button) => {
                const active = button.dataset.themeMode === mode;
                button.classList.toggle('active', active);
                button.setAttribute('aria-checked', active ? 'true' : 'false');
            });
        }

        function setThemeMode(mode) {
            const next = ['light', 'dark', 'device'].includes(mode) ? mode : 'device';
            localStorage.setItem(themeKey, next);
            applyTheme(next);
        }

        function setWalletTab(tab, persist = true) {
            activeWalletTab = ['overview', 'transfer', 'withdraw', 'activity'].includes(tab) ? tab : 'overview';
            if (persist) localStorage.setItem(walletTabKey, activeWalletTab);
            document.querySelectorAll('[data-wallet-tab]').forEach((button) => {
                const active = button.dataset.walletTab === activeWalletTab;
                button.classList.toggle('active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            document.querySelectorAll('[data-wallet-panel]').forEach((panel) => {
                const active = panel.dataset.walletPanel === activeWalletTab;
                panel.classList.toggle('active', active);
                panel.hidden = !active;
            });
        }

        function notify(message, type = 'ok') {
            toast.textContent = message;
            toast.classList.toggle('error', type === 'error');
            toast.hidden = false;
            clearTimeout(notify.timer);
            notify.timer = setTimeout(() => { toast.hidden = true; }, 5200);
        }

        function handlePaymentReturnNotice() {
            if (handledReturnNotice) return;

            const params = new URLSearchParams(window.location.search);
            const checkout = params.get('checkout');
            const wallet = params.get('wallet');
            const giving = params.get('giving');

            if (!checkout && !wallet && !giving) return;

            handledReturnNotice = true;

            if (checkout === 'success') {
                notify('Payment completed. Your retreat status will update once Stripe confirms it.');
                loadMemberRetreatData();
                loadWallet();
                return;
            }

            if (checkout === 'cancelled') {
                notify('Payment was cancelled. You can try again when ready.', 'error');
                return;
            }

            if (wallet === 'success') {
                notify('Wallet top-up completed. Your balance will update once Stripe confirms it.');
                loadWallet();
                return;
            }

            if (wallet === 'cancelled') {
                notify('Wallet top-up was cancelled.', 'error');
                return;
            }

            if (giving === 'success') {
                notify('Thank you. Your giving payment will appear once Stripe confirms it.');
                return;
            }

            if (giving === 'cancelled') {
                notify('Giving checkout was cancelled.', 'error');
            }
        }

        function showAuthNotice(message, type = 'ok') {
            authNotice.textContent = message;
            authNotice.classList.toggle('error', type === 'error');
            authNotice.hidden = !message;
        }

        function setBusy(form, busy) {
            form.querySelectorAll('button, input, select, textarea').forEach((node) => {
                if (node.type !== 'hidden') node.disabled = busy;
            });
        }

        function formatMoney(amount, currency = 'GBP') {
            const code = `${currency || 'GBP'}`.toUpperCase();
            try {
                return new Intl.NumberFormat(undefined, { style: 'currency', currency: code }).format(Number(amount || 0));
            } catch {
                return `${code} ${Number(amount || 0).toFixed(2)}`;
            }
        }

        function formatDate(value) {
            if (!value) return 'Date unavailable';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return `${value}`;
            return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(date);
        }

        function formatDateTime(value) {
            if (!value) return 'Not available';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return `${value}`;
            return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
        }

        function statusBadge(value) {
            const status = `${value || 'pending'}`.replace(/_/g, ' ');
            const className = /paid|issued|active|ok|confirmed|completed/i.test(status)
                ? 'ok'
                : (/cancel|failed|refunded|expired/i.test(status) ? 'danger' : '');
            return `<span class="badge ${className}">${escapeHtml(status)}</span>`;
        }

        function currentPathSegment() {
            const segment = window.location.pathname.replace(/^\/app\/?/, '').split('/')[0];
            return segment || 'home';
        }

        function showAuth(tab = 'login', push = true) {
            currentUser = null;
            authShell.hidden = false;
            portalShell.hidden = true;
            if (googleAuthPanel) {
                googleAuthPanel.hidden = !canShowGoogleAuth(tab);
            }
            document.querySelectorAll('[data-auth-tab]').forEach((button) => {
                button.classList.toggle('active', button.dataset.authTab === tab);
            });
            ['login', 'register', 'verify', 'forgot', 'reset'].forEach((name) => {
                const form = document.getElementById(`${name}Form`);
                if (form) form.hidden = name !== tab;
            });
            if (push) {
                const url = tab === 'forgot' ? '/app/forgot-password' : (tab === 'reset' ? '/app/reset-password' : '/app');
                history.replaceState(null, '', url);
            }
        }

        function canShowGoogleAuth(tab = 'login') {
            return Boolean(googleLoginConfig?.enabled && googleLoginConfig?.clientId && ['login', 'register'].includes(tab));
        }

        function setGoogleBusy(busy) {
            googleSignInButton?.classList.toggle('busy', busy);
            googleSignInButton?.setAttribute('aria-busy', busy ? 'true' : 'false');
        }

        function loadGoogleIdentityScript() {
            if (!canShowGoogleAuth('login')) return Promise.resolve(false);
            if (window.google?.accounts?.id) return Promise.resolve(true);
            if (googleIdentityLoading) return googleIdentityLoading;

            googleIdentityLoading = new Promise((resolve) => {
                const script = document.createElement('script');
                script.src = 'https://accounts.google.com/gsi/client';
                script.async = true;
                script.defer = true;
                script.onload = () => resolve(Boolean(window.google?.accounts?.id));
                script.onerror = () => resolve(false);
                document.head.appendChild(script);
            });

            return googleIdentityLoading;
        }

        async function handleGoogleCredential(response) {
            const idToken = response?.credential;
            if (!idToken) {
                showAuthNotice('Google did not return a sign-in token. Please try again.', 'error');
                return;
            }

            setGoogleBusy(true);
            showAuthNotice('');

            try {
                const payload = await apiPost('/api/googleAuth', { id_token: idToken });
                saveUser(payload.user);
                notify(`Welcome, ${payload.user?.name || 'member'}.`);
            } catch (error) {
                showAuthNotice(error.message, 'error');
            } finally {
                setGoogleBusy(false);
            }
        }

        async function initializeGoogleLogin() {
            if (!googleAuthPanel || !googleSignInButton || !canShowGoogleAuth('login') || googleIdentityReady) {
                return;
            }

            const loaded = await loadGoogleIdentityScript();
            if (!loaded) {
                googleAuthPanel.hidden = true;
                return;
            }

            window.google.accounts.id.initialize({
                client_id: googleLoginConfig.clientId,
                callback: handleGoogleCredential,
                auto_select: false,
                cancel_on_tap_outside: true,
            });

            window.google.accounts.id.renderButton(googleSignInButton, {
                theme: 'outline',
                size: 'large',
                shape: 'pill',
                text: 'continue_with',
                width: Math.min(320, googleAuthPanel.clientWidth || googleSignInButton.clientWidth || 320),
            });

            googleIdentityReady = true;
        }

        function saveUser(user) {
            if (!user?.api_token) {
                throw new Error('The server did not return a login session.');
            }
            currentUser = user;
            localStorage.setItem(storageKey, JSON.stringify(user));
            showPortal();
        }

        function clearUser(message) {
            currentUser = null;
            localStorage.removeItem(storageKey);
            showAuth('login');
            if (message) notify(message, 'error');
        }

        function showPortal(page = null) {
            authShell.hidden = true;
            portalShell.hidden = false;
            updateUserIdentity();
            const requestedPage = page || currentPathSegment();
            showPage(pageTitles[requestedPage] ? requestedPage : 'home', false);
            loadEvents();
            loadMemberRetreatData();
            loadWallet();
            loadUpdates();
            handlePaymentReturnNotice();
        }

        function updateUserIdentity() {
            const name = currentUser?.name || [currentUser?.first_name, currentUser?.last_name].filter(Boolean).join(' ') || 'Member';
            const email = currentUser?.email || '';
            ['sidebarUserName', 'drawerUserName'].forEach((id) => { document.getElementById(id).textContent = name; });
            ['sidebarUserEmail', 'drawerUserEmail'].forEach((id) => { document.getElementById(id).textContent = email; });
            document.getElementById('homeGreeting').textContent = `Welcome, ${name.split(' ')[0] || 'Member'}`;
        }

        function showPage(page, push = true) {
            activePage = pageTitles[page] ? page : 'home';
            document.querySelectorAll('[data-page-view]').forEach((view) => {
                view.classList.toggle('active', view.dataset.pageView === activePage);
            });
            document.querySelectorAll('[data-nav-page]').forEach((button) => {
                button.classList.toggle('active', button.dataset.navPage === activePage);
            });
            document.getElementById('mobileTitle').textContent = pageTitles[activePage];
            if (push) history.pushState(null, '', activePage === 'home' ? '/app' : `/app/${activePage}`);
            closeDrawer();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function openDrawer() {
            drawerBackdrop.hidden = false;
            const first = drawerBackdrop.querySelector('button');
            window.setTimeout(() => first?.focus(), 20);
        }

        function closeDrawer() {
            drawerBackdrop.hidden = true;
        }

        async function loadGroups() {
            const select = document.getElementById('registerGroup');
            try {
                const payload = await apiGet('/api/church_groups');
                const groups = Array.isArray(payload.groups) ? payload.groups : [];
                churchGroupsCache = groups;
                if (select) select.innerHTML = '<option value="">Please select</option>' + groups
                    .map((group) => `<option value="${escapeHtml(group.id)}">${escapeHtml(group.name)}</option>`)
                    .join('');
                if (currentUser && activePage === 'profile') renderProfile();
            } catch {
                churchGroupsCache = [];
                if (select) select.innerHTML = '<option value="">Church groups unavailable</option>';
            }
        }

        async function restoreUser() {
            const raw = localStorage.getItem(storageKey);
            if (!raw) {
                const segment = currentPathSegment();
                showAuth(segment === 'forgot-password' ? 'forgot' : (segment === 'reset-password' ? 'reset' : 'login'), false);
                return;
            }

            try {
                const saved = JSON.parse(raw);
                if (!saved?.api_token) throw new Error('Missing token');
                currentUser = saved;
                showPortal();
                const payload = await apiPost('/api/member/me', { api_token: saved.api_token });
                if (payload.user) {
                    currentUser = { ...payload.user, api_token: saved.api_token };
                    localStorage.setItem(storageKey, JSON.stringify(currentUser));
                    updateUserIdentity();
                    renderProfile();
                }
            } catch {
                clearUser('Your saved session expired. Please sign in again.');
            }
        }

        function eventImage(event) {
            return event?.feature_image_url || event?.image_url || event?.cover_image_url || event?.media_url || '';
        }

        function eventDate(event) {
            const start = event?.starts_at || event?.start_at || event?.sales_start_at;
            const end = event?.ends_at || event?.end_at;
            return end ? `${formatDate(start)} - ${formatDate(end)}` : formatDate(start);
        }

        function eventVenue(event) {
            return [event?.venue_name, event?.venue_address].filter(Boolean).join(' - ') || 'Venue details will be shared by the church.';
        }

        async function loadEvents() {
            try {
                const payload = await apiGet('/api/goshen-retreat/events');
                eventsCache = Array.isArray(payload.data) ? payload.data : [];
                renderEvents();
                renderHome();
                renderSupport();
            } catch (error) {
                document.getElementById('retreatEvents').innerHTML = `<div class="empty">${escapeHtml(error.message)}</div>`;
            }
        }

        async function loadMemberRetreatData() {
            if (!currentUser?.api_token) return;
            try {
                const payload = await apiPost('/api/goshen-retreat/me', authPayload());
                memberData = {
                    registrations: payload.data?.registrations || [],
                    payment_history: payload.data?.payment_history || [],
                    tickets: payload.data?.tickets || [],
                    accommodation_allocations: payload.data?.accommodation_allocations || [],
                    referral: payload.data?.referral || null,
                };
                if (payload.data?.user) {
                    currentUser = { ...currentUser, ...payload.data.user, api_token: currentUser.api_token };
                    localStorage.setItem(storageKey, JSON.stringify(currentUser));
                    updateUserIdentity();
                }
                renderHome();
                renderTickets();
                renderPayments();
                renderReceipts();
                renderProfile();
            } catch (error) {
                ['ticketsList', 'paymentsList', 'receiptsList'].forEach((id) => {
                    document.getElementById(id).innerHTML = `<div class="empty">${escapeHtml(error.message)}</div>`;
                });
            }
        }

        async function loadWallet() {
            if (!currentUser?.api_token) return;
            const walletContent = document.getElementById('walletContent');
            if (!walletData) {
                walletContent.innerHTML = '<div class="empty">Loading your Goshen wallet...</div>';
            }
            try {
                const payload = await apiPost('/api/goshen-wallet', authPayload());
                walletData = payload.data || null;
                renderWallet();
                renderHome();
            } catch (error) {
                walletContent.innerHTML = `<div class="empty">${escapeHtml(error.message)}</div>`;
            }
        }

        async function loadUpdates() {
            if (!currentUser?.api_token) return;
            try {
                const payload = await apiPost('/api/fetch_inbox', authPayload({ page: 0 }));
                const inbox = Array.isArray(payload.inbox) ? payload.inbox : [];
                document.getElementById('updatesList').innerHTML = inbox.length
                    ? inbox.map(renderInboxMessage).join('')
                    : '<div class="empty">No retreat updates are available for your account yet.</div>';
            } catch (error) {
                document.getElementById('updatesList').innerHTML = `<div class="empty">${escapeHtml(error.message)}</div>`;
            }
        }

        function renderEvents() {
            const node = document.getElementById('retreatEvents');
            if (!eventsCache.length) {
                node.innerHTML = '<div class="empty">No published Goshen Retreat edition is available yet.</div>';
                return;
            }
            node.innerHTML = eventsCache.map(renderEventCard).join('');
        }

        function renderEventCard(event) {
            const image = eventImage(event);
            const schedules = Array.isArray(event.schedules) ? event.schedules : [];
            const tickets = Array.isArray(event.ticket_types) ? event.ticket_types : [];
            const registrationOpen = Boolean(event.registration?.open ?? event.registration_open ?? true);
            return `
                <article class="card">
                    <div class="event-media">${image ? `<img src="${escapeHtml(image)}" alt="">` : ''}</div>
                    <div class="record-top">
                        <div class="record-title">
                            <span class="badge ${registrationOpen ? 'ok' : 'danger'}">${registrationOpen ? 'Registration open' : 'Registration closed'}</span>
                            <strong>${escapeHtml(event.name || event.title || 'Goshen Retreat')}</strong>
                            <span class="item-meta">${escapeHtml(eventDate(event))}</span>
                        </div>
                    </div>
                    <div class="detail-list">
                        <div class="detail-row"><strong>Venue</strong><span>${escapeHtml(eventVenue(event))}</span></div>
                        ${tickets.length ? `<div class="detail-row"><strong>Ticket options</strong>${tickets.map((ticket) => `<span>${escapeHtml(ticket.name || 'Ticket')} - ${escapeHtml(formatMoney(ticket.price, ticket.currency))}</span>`).join('')}</div>` : ''}
                        ${schedules.length ? `<div class="detail-row"><strong>Schedule</strong>${schedules.slice(0, 4).map((schedule) => `<span>${escapeHtml(schedule.title || 'Session')} - ${escapeHtml(formatDateTime(schedule.starts_at))}</span>`).join('')}</div>` : ''}
                    </div>
                    ${registrationOpen ? renderRegistrationForm(event) : `<div class="empty">${escapeHtml(event.registration?.message || 'Registration is currently closed for this retreat edition.')}</div>`}
                </article>
            `;
        }

        function registrationFieldsFor(event) {
            const fields = Array.isArray(event?.registration_form?.attendee_fields)
                ? event.registration_form.attendee_fields
                : (Array.isArray(event?.attendee_fields) ? event.attendee_fields : []);
            if (fields.length) {
                return fields.filter((field) => field && field.key && field.label)
                    .sort((a, b) => Number(a.sort_order || 0) - Number(b.sort_order || 0));
            }
            return [
                { key: 'gender', label: 'Gender', type: 'select', is_required: true, options: [
                    { label: 'Please Select', value: '' }, { label: 'Male', value: 'male' }, { label: 'Female', value: 'female' },
                ] },
                { key: 'age_group', label: 'Age group', type: 'select', is_required: true, options: [
                    { label: 'Please Select', value: '' }, { label: 'Child', value: 'child' }, { label: 'Teen', value: 'teen' }, { label: 'Adult', value: 'adult' },
                ] },
                { key: 'free_church_bus_interest', label: 'Interested in joining FREE church bus', type: 'select', is_required: true, options: [
                    { label: 'Please Select', value: '' }, { label: 'Yes', value: 'yes' }, { label: 'No thanks', value: 'no_thanks' },
                ] },
                { key: 'volunteer_department', label: 'What department would you like to volunteer in?', type: 'select', is_required: true, options: [
                    { label: 'Please Select', value: '' }, { label: 'Children department', value: 'children_department' }, { label: 'Intercessory', value: 'intercessory' }, { label: 'Media', value: 'media' }, { label: 'Protocol', value: 'protocol' }, { label: 'Sanctuary', value: 'sanctuary' }, { label: 'No Chance at the moment', value: 'no_chance_at_the_moment' },
                ] },
            ];
        }

        function renderRegistrationForm(event) {
            const tickets = Array.isArray(event.ticket_types) ? event.ticket_types : [];
            if (!tickets.length) {
                return '<div class="empty">Ticket types are not available yet for this retreat edition.</div>';
            }
            const ticketOptions = tickets.map((ticket) => `<option value="${escapeHtml(ticket.public_id)}">${escapeHtml(ticket.name || 'Ticket')} - ${escapeHtml(formatMoney(ticket.price, ticket.currency))}</option>`).join('');
            return `
                <form class="form registration-form" data-event-id="${escapeHtml(event.public_id)}">
                    <div class="form-grid">
                        <div class="field">
                            <label>Ticket type</label>
                            <select class="input" name="ticket_type_id" required>${ticketOptions}</select>
                        </div>
                        <div class="field">
                            <label>Attendees</label>
                            <input class="input attendee-quantity" name="quantity" type="number" min="1" max="20" value="1" required>
                        </div>
                        <div class="field">
                            <label>Payment method</label>
                            <select class="input payment-mode" name="payment_mode" required>
                                <option value="outright">Card payment</option>
                                <option value="wallet">Wallet funds</option>
                                <option value="voucher">Voucher</option>
                            </select>
                        </div>
                        <div class="field voucher-field" hidden>
                            <label>Voucher code</label>
                            <input class="input" name="voucher_code" autocomplete="off">
                        </div>
                    </div>
                    <div class="attendee-fields">${renderAttendeeFields(1, event)}</div>
                    <label class="choice">
                        <input type="checkbox" name="uk_privacy_consent" value="1" required>
                        <span>I agree that MFM Triumphant Church may process my registration, attendee, payment, ticket, and travel-support information for Goshen Retreat administration in line with UK data protection requirements.</span>
                    </label>
                    <button class="button" type="submit">Register for this retreat</button>
                </form>
            `;
        }

        function splitName(name) {
            const parts = `${name || ''}`.trim().split(/\s+/).filter(Boolean);
            return { first: parts[0] || '', last: parts.slice(1).join(' ') };
        }

        function renderAttendeeFields(quantity, event) {
            const total = Math.max(1, Math.min(20, Number(quantity || 1)));
            const fields = registrationFieldsFor(event);
            const name = splitName(currentUser?.name || '');
            const cards = [];
            for (let index = 0; index < total; index += 1) {
                cards.push(`
                    <div class="attendee-card" data-attendee-index="${index}">
                        <strong>Attendee ${index + 1}${index === 0 ? ' - account holder' : ''}</strong>
                        <div class="form-grid">
                            <div class="field"><label>First name</label><input class="input attendee-first-name" value="${escapeHtml(index === 0 ? name.first : '')}" required></div>
                            <div class="field"><label>Last name</label><input class="input attendee-last-name" value="${escapeHtml(index === 0 ? name.last : '')}"></div>
                            <div class="field"><label>Email</label><input class="input attendee-email" type="email" value="${escapeHtml(index === 0 ? currentUser?.email || '' : '')}"></div>
                            <div class="field"><label>Phone</label><input class="input attendee-phone" type="tel" value="${escapeHtml(index === 0 ? currentUser?.phone || '' : '')}"></div>
                            ${fields.map((field) => renderRegistrationField(field, index)).join('')}
                        </div>
                    </div>
                `);
            }
            return cards.join('');
        }

        function optionValue(option) {
            return option && typeof option === 'object' ? (option.value ?? option.label ?? '') : option;
        }

        function optionLabel(option) {
            return option && typeof option === 'object' ? (option.label ?? option.value ?? 'Option') : option;
        }

        function renderRegistrationField(field, attendeeIndex) {
            const key = `${field.key || ''}`.trim();
            const label = escapeHtml(field.label || key);
            const type = `${field.type || 'text'}`.toLowerCase();
            const required = field.is_required ? 'required' : '';
            const options = Array.isArray(field.options) ? field.options : [];

            if (['select', 'single_select'].includes(type)) {
                return `<div class="field"><label>${label}</label><select class="input attendee-dynamic-control" data-field-key="${escapeHtml(key)}" ${required}>${options.map((option) => `<option value="${escapeHtml(optionValue(option))}">${escapeHtml(optionLabel(option))}</option>`).join('')}</select></div>`;
            }

            if (type === 'textarea') {
                return `<div class="field"><label>${label}</label><textarea class="input attendee-dynamic-control" data-field-key="${escapeHtml(key)}" ${required}></textarea></div>`;
            }

            if (['image_select', 'color_select'].includes(type)) {
                const radioName = `attendee_${attendeeIndex}_${key}`;
                return `<div class="field"><label>${label}</label><div class="choice-grid">${options.filter((option) => `${optionValue(option)}` !== '').map((option) => {
                    const image = option?.image_url ? `<img src="${escapeHtml(option.image_url)}" alt="">` : '';
                    const swatch = option?.color_hex ? `<span class="swatch" style="background:${escapeHtml(option.color_hex)}"></span>` : '';
                    return `<label class="choice"><input class="attendee-dynamic-control" data-field-key="${escapeHtml(key)}" type="radio" name="${escapeHtml(radioName)}" value="${escapeHtml(optionValue(option))}" ${required}>${image || swatch}<span>${escapeHtml(optionLabel(option))}</span></label>`;
                }).join('')}</div></div>`;
            }

            return `<div class="field"><label>${label}</label><input class="input attendee-dynamic-control" data-field-key="${escapeHtml(key)}" ${required}></div>`;
        }

        function collectDynamicFields(card) {
            const fields = {};
            card.querySelectorAll('.attendee-dynamic-control[data-field-key]').forEach((input) => {
                const key = input.dataset.fieldKey;
                if (!key) return;
                if (input.type === 'radio') {
                    if (input.checked) fields[key] = input.value || '';
                    return;
                }
                fields[key] = input.value || '';
            });
            return fields;
        }

        async function submitRegistration(form) {
            if (!form.reportValidity()) return;
            const values = payloadFromForm(form);
            const attendees = [...form.querySelectorAll('.attendee-card')].map((card) => {
                const dynamic = collectDynamicFields(card);
                return {
                    first_name: card.querySelector('.attendee-first-name')?.value || '',
                    last_name: card.querySelector('.attendee-last-name')?.value || '',
                    email: card.querySelector('.attendee-email')?.value || currentUser.email || '',
                    phone: card.querySelector('.attendee-phone')?.value || currentUser.phone || '',
                    ...dynamic,
                    custom_fields: dynamic,
                };
            });
            const payload = authPayload({
                event_id: form.dataset.eventId,
                ticket_type_id: values.ticket_type_id,
                payment_mode: values.payment_mode || 'outright',
                voucher_code: values.voucher_code || '',
                quantity: Number(values.quantity || 1),
                uk_privacy_consent: form.querySelector('input[name="uk_privacy_consent"]')?.checked === true,
                privacy_policy_version: 'uk-gdpr-2026-06',
                apply_pay_in_full_discount: true,
                free_church_bus_consent: attendees.some((attendee) => attendee.free_church_bus_interest === 'yes'),
                attendees,
            });
            setBusy(form, true);
            try {
                const response = await apiPost('/api/goshen-retreat/bookings', payload);
                notify(response.message || 'Your Goshen Retreat registration has been started.');
                await loadMemberRetreatData();
                const booking = response.booking;
                const installment = firstPayableInstallment(booking);
                if (payload.payment_mode === 'outright' && installment) {
                    await startCheckout(booking.public_id, installment.public_id);
                } else {
                    showPage('tickets');
                }
            } catch (error) {
                notify(error.message, 'error');
            } finally {
                setBusy(form, false);
            }
        }

        function firstPayableInstallment(booking) {
            return (booking?.installments || []).find((installment) => !/paid|cancel|refund|complete/i.test(`${installment.status || ''}`));
        }

        function payableRows() {
            return (memberData.registrations || [])
                .flatMap((booking) => (booking.installments || []).map((installment) => ({ booking, installment })))
                .filter(({ booking, installment }) => booking.can_pay && !/paid|cancel|refund|complete/i.test(`${installment.status || ''}`));
        }

        function renderHome() {
            const registrations = memberData.registrations || [];
            const tickets = memberData.tickets || [];
            const payable = payableRows();
            const outstanding = payable.reduce((sum, row) => sum + Math.max(0, Number(row.installment.amount || 0) - Number(row.installment.paid_amount || 0)), 0);
            const currency = payable[0]?.installment?.currency || registrations[0]?.currency || 'GBP';
            document.getElementById('homeStats').innerHTML = `
                <div class="stat"><span>Registrations</span><strong>${registrations.length}</strong></div>
                <div class="stat"><span>Tickets</span><strong>${tickets.length}</strong></div>
                <div class="stat"><span>Outstanding</span><strong>${escapeHtml(formatMoney(outstanding, currency))}</strong></div>
                <div class="stat"><span>Wallet</span><strong>${escapeHtml(walletData ? formatMoney(walletData.balance, walletData.currency) : 'Load')}</strong></div>
            `;
            document.getElementById('homeNextAction').innerHTML = payable.length
                ? `<h3>Payment waiting</h3><p class="muted">You have a Goshen Retreat payment to complete.</p><button class="button" type="button" data-nav-page="payments">Continue payment</button>`
                : (registrations.length
                    ? `<h3>Registration ready</h3><p class="muted">Your registration records are available. View tickets and receipts from this portal.</p><button class="button subtle" type="button" data-nav-page="tickets">View tickets</button>`
                    : `<h3>Start your retreat journey</h3><p class="muted">Register for a published Goshen Retreat edition when you are ready.</p><button class="button" type="button" data-nav-page="retreat">Start registration</button>`);
            const event = eventsCache[0];
            document.getElementById('homeEventPreview').innerHTML = event
                ? `<h3>${escapeHtml(event.name || 'Goshen Retreat')}</h3><p class="muted">${escapeHtml(eventDate(event))}</p><div class="detail-list"><div class="detail-row"><strong>Venue</strong><span>${escapeHtml(eventVenue(event))}</span></div></div>`
                : '<div class="empty">No published retreat edition is available yet.</div>';
        }

        function walletAmount(amount, currency) {
            return escapeHtml(formatMoney(amount, currency || walletData?.currency || 'GBP'));
        }

        function walletProgress(goal) {
            if (!goal) return 0;
            const raw = Number(goal.progress ?? 0);
            if (raw > 1) return Math.max(0, Math.min(100, raw));
            return Math.max(0, Math.min(100, raw * 100));
        }

        function renderWallet() {
            const root = document.getElementById('walletContent');
            const wallet = walletData;
            if (!wallet) {
                root.innerHTML = '<div class="empty">Unable to load your Goshen wallet.</div>';
                return;
            }

            const currency = wallet.currency || 'GBP';
            const primaryGoal = (wallet.goals || []).find((goal) => goal.is_primary && goal.status === 'active')
                || (wallet.goals || []).find((goal) => goal.status === 'active');
            const goalPercent = walletProgress(primaryGoal);
            const plans = wallet.savings_plans || [];
            const withdrawals = wallet.withdrawal_requests || [];
            const ledger = wallet.ledger || [];

            root.innerHTML = `
                <section class="card wallet-balance-card">
                    <p class="eyebrow">Goshen savings wallet</p>
                    <strong class="wallet-balance">${walletAmount(wallet.balance, currency)}</strong>
                    <p class="muted">Use wallet funds for eligible retreat payments, transfers, and managed withdrawals.</p>
                    ${wallet.security_reset?.reset_required ? `<p class="muted">${escapeHtml(wallet.security_reset.message || 'Wallet security reset is pending.')}</p>` : ''}
                </section>

                <div class="wallet-tabs" role="tablist" aria-label="Wallet tools">
                    <button class="wallet-tab" type="button" role="tab" data-wallet-tab="overview">Overview</button>
                    <button class="wallet-tab" type="button" role="tab" data-wallet-tab="transfer">Transfer</button>
                    <button class="wallet-tab" type="button" role="tab" data-wallet-tab="withdraw">Withdraw</button>
                    <button class="wallet-tab" type="button" role="tab" data-wallet-tab="activity">Activity</button>
                </div>

                <section class="wallet-panel wallet-actions-grid" data-wallet-panel="overview" role="tabpanel">
                    <article class="card">
                        <h3>Top up now</h3>
                        <p class="muted">Add money securely with Stripe.</p>
                        <form class="form wallet-topup-form">
                            <div class="wallet-mini-grid">
                                <div class="field"><label>Amount (${escapeHtml(currency)})</label><input class="input" name="amount" type="number" min="1" step="0.01" required></div>
                                <div class="field"><label>Currency</label><input class="input" name="currency" maxlength="3" value="${escapeHtml(currency)}" required></div>
                            </div>
                            <label class="choice"><input name="save_payment_method" type="checkbox" value="1"><span>Save card for auto top-up</span></label>
                            <button class="button" type="submit">Top up with Stripe</button>
                        </form>
                    </article>

                    <article class="card">
                        <h3>Savings goal</h3>
                        <p class="muted">Edit a saved goal or add another target.</p>
                        <form class="form wallet-goal-form">
                            <input type="hidden" name="goal_id" value="${escapeHtml(primaryGoal?.id || '')}">
                            <div class="field"><label>Goal name</label><input class="input" name="goal_label" value="${escapeHtml(primaryGoal?.label || wallet.goal_label || 'Goshen Retreat savings')}" required></div>
                            <div class="wallet-mini-grid">
                                <div class="field"><label>Target amount</label><input class="input" name="goal_amount" type="number" min="1" step="0.01" value="${escapeHtml(primaryGoal?.target_amount || wallet.goal_amount || '')}" required></div>
                                <div class="field"><label>Currency</label><input class="input" name="currency" maxlength="3" value="${escapeHtml(primaryGoal?.currency || currency)}" required></div>
                            </div>
                            <div class="progress-track" aria-label="Savings goal progress"><span style="--progress:${goalPercent}%"></span></div>
                            <button class="button" type="submit">Save selected goal</button>
                            <button class="button outline wallet-new-goal" type="button">Add as new goal</button>
                            ${primaryGoal ? `<button class="button subtle wallet-cancel-goal" type="button" data-goal-id="${escapeHtml(primaryGoal.id)}">Cancel selected goal</button>` : ''}
                        </form>
                    </article>

                    <article class="card">
                        <h3>Auto top-up plan</h3>
                        <p class="muted">Save a fixed amount daily, weekly, or monthly.</p>
                        <form class="form wallet-plan-form">
                            <div class="wallet-mini-grid">
                                <div class="field"><label>Amount (${escapeHtml(currency)})</label><input class="input" name="amount" type="number" min="1" step="0.01" required></div>
                                <div class="field"><label>Frequency</label><select class="input" name="frequency" required><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></div>
                            </div>
                            <div class="field"><label>Number of top-ups (optional)</label><input class="input" name="remaining_cycles" type="number" min="1" max="520"></div>
                            <button class="button" type="submit">Create auto top-up</button>
                        </form>
                        <div class="record-list" style="margin-top:14px">${plans.length ? plans.map(renderSavingsPlan).join('') : '<div class="empty">No auto top-up plan has been created yet.</div>'}</div>
                    </article>
                </section>

                <section class="wallet-panel" data-wallet-panel="transfer" role="tabpanel">
                    <article class="card">
                        <h3>Transfer to a member</h3>
                        <p class="muted">Send wallet funds to another member by email, phone, or Triumphant ID.</p>
                        <form class="form wallet-transfer-form">
                            <div class="field"><label>Recipient</label><input class="input" name="recipient" required></div>
                            <div class="wallet-mini-grid">
                                <div class="field"><label>Amount</label><input class="input" name="amount" type="number" min="1" step="0.01" required></div>
                                <div class="field"><label>Currency</label><input class="input" name="currency" maxlength="3" value="${escapeHtml(currency)}" required></div>
                            </div>
                            <div class="field"><label>Note (optional)</label><input class="input" name="note" maxlength="240"></div>
                            <button class="button" type="submit">Send transfer</button>
                        </form>
                    </article>
                </section>

                <section class="wallet-panel" data-wallet-panel="withdraw" role="tabpanel">
                    <article class="card">
                        <h3>Withdraw wallet funds</h3>
                        <p class="muted">Submit a withdrawal request for admin review.</p>
                        <form class="form wallet-withdrawal-form">
                            <div class="wallet-mini-grid">
                                <div class="field"><label>Amount</label><input class="input" name="amount" type="number" min="1" step="0.01" required></div>
                                <div class="field"><label>Currency</label><input class="input" name="currency" maxlength="3" value="${escapeHtml(currency)}" required></div>
                            </div>
                            <div class="field"><label>Bank name</label><input class="input" name="bank_name" required></div>
                            <div class="field"><label>Account name</label><input class="input" name="account_name" required></div>
                            <div class="field"><label>Account number</label><input class="input" name="account_number" required></div>
                            <div class="wallet-mini-grid">
                                <div class="field"><label>Sort code</label><input class="input" name="sort_code"></div>
                                <div class="field"><label>IBAN</label><input class="input" name="iban"></div>
                            </div>
                            <div class="field"><label>Note for admin (optional)</label><textarea class="input" name="user_note" maxlength="500"></textarea></div>
                            <button class="button" type="submit">Submit withdrawal request</button>
                        </form>
                        <div class="record-list" style="margin-top:14px">${withdrawals.length ? withdrawals.map(renderWithdrawal).join('') : '<div class="empty">No withdrawal request yet.</div>'}</div>
                    </article>
                </section>

                <section class="wallet-panel" data-wallet-panel="activity" role="tabpanel">
                    <article class="card">
                        <h3>Recent activity</h3>
                        <p class="muted">Wallet top-ups, transfers, payments, and withdrawal movements.</p>
                        <div class="record-list">${ledger.length ? ledger.map(renderLedgerEntry).join('') : '<div class="empty">No wallet activity yet.</div>'}</div>
                    </article>
                </section>
            `;
            setWalletTab(activeWalletTab, false);
        }

        function renderSavingsPlan(plan) {
            const next = plan.next_charge_at ? formatDateTime(plan.next_charge_at) : 'Next charge unavailable';
            return `
                <article class="record">
                    <div class="record-top">
                        <div class="record-title">
                            <strong>${walletAmount(plan.amount, plan.currency)} ${escapeHtml(plan.frequency || '')}</strong>
                            <span class="item-meta">Next: ${escapeHtml(next)}</span>
                            ${statusBadge(plan.status)}
                        </div>
                    </div>
                    <div class="record-actions">
                        ${plan.status === 'paused' ? `<button class="button small wallet-plan-status" type="button" data-plan-id="${escapeHtml(plan.id)}" data-status="active">Resume</button>` : `<button class="button small subtle wallet-plan-status" type="button" data-plan-id="${escapeHtml(plan.id)}" data-status="paused">Pause</button>`}
                        ${plan.status !== 'cancelled' ? `<button class="button small outline wallet-plan-status" type="button" data-plan-id="${escapeHtml(plan.id)}" data-status="cancelled">Cancel</button>` : ''}
                        ${plan.status === 'setup_required' ? `<button class="button small wallet-plan-setup" type="button" data-plan-id="${escapeHtml(plan.id)}" data-amount="${escapeHtml(plan.amount)}" data-currency="${escapeHtml(plan.currency || 'GBP')}">Complete card setup</button>` : ''}
                    </div>
                </article>
            `;
        }

        function renderWithdrawal(item) {
            return `
                <article class="record">
                    <div class="record-top">
                        <div class="record-title">
                            <strong>${walletAmount(item.amount, item.currency)}</strong>
                            <span class="item-meta">${escapeHtml(item.bank_name || 'Withdrawal request')} - ${escapeHtml(formatDateTime(item.requested_at || item.created_at))}</span>
                            ${statusBadge(item.status)}
                        </div>
                    </div>
                    ${item.status === 'pending' ? `<div class="record-actions"><button class="button small outline wallet-cancel-withdrawal" type="button" data-withdrawal-id="${escapeHtml(item.id)}">Cancel request</button></div>` : ''}
                </article>
            `;
        }

        function renderLedgerEntry(entry) {
            const debit = `${entry.direction || ''}`.toLowerCase() === 'debit';
            const sign = debit ? '-' : '+';
            return `
                <article class="record wallet-ledger-row">
                    <span class="wallet-direction ${debit ? 'debit' : ''}">${debit ? '&uarr;' : '&darr;'}</span>
                    <span class="record-title">
                        <strong>${escapeHtml(entry.description || entry.type || 'Wallet activity')}</strong>
                        <span class="item-meta">${escapeHtml(formatDateTime(entry.created_at))} - ${escapeHtml(entry.status || '')}</span>
                    </span>
                    <span class="wallet-amount">${sign}${walletAmount(entry.amount, entry.currency)}</span>
                </article>
            `;
        }

        function renderTickets() {
            const tickets = memberData.tickets || [];
            document.getElementById('ticketsList').innerHTML = tickets.length
                ? tickets.map(renderTicket).join('')
                : '<div class="empty">No issued Goshen Retreat ticket is linked to your account yet.</div>';
            loadTicketQrImages();
        }

        function renderTicket(ticket) {
            const urls = ticket.document_urls || {};
            const ticketNumber = ticket.ticket_number || ticket.public_id || 'Ticket';
            return `
                <article class="record ticket-card">
                    <div class="ticket-summary">
                        ${statusBadge(ticket.status)}
                        <strong>${escapeHtml(ticketNumber)}</strong>
                        <span class="item-meta">${escapeHtml(ticket.attendee_name || 'Attendee')} - ${escapeHtml(ticket.ticket_type || 'Goshen Retreat')}</span>
                    </div>
                    <div class="ticket-qr-stage">
                        <div class="qr-holder" data-qr-url="${escapeHtml(urls.qr || '')}">QR</div>
                        <p>Scan this QR code at check-in</p>
                    </div>
                    <div class="ticket-details">
                        <div class="ticket-detail"><span>Ticket holder</span><strong>${escapeHtml(ticket.attendee_name || currentUser?.name || 'Attendee')}</strong></div>
                        <div class="ticket-detail"><span>Ticket type</span><strong>${escapeHtml(ticket.ticket_type || 'Goshen Retreat')}</strong></div>
                        <div class="ticket-detail"><span>Issued for</span><strong>${escapeHtml(ticket.event?.name || ticket.event_name || 'Goshen Retreat')}</strong></div>
                        <div class="ticket-detail"><span>Ticket number</span><strong>${escapeHtml(ticketNumber)}</strong></div>
                    </div>
                    <div class="record-actions">
                        ${urls.pdf ? `<button class="button subtle ticket-download" type="button" data-url="${escapeHtml(urls.pdf)}" data-filename="${escapeHtml(ticketNumber + '.pdf')}">Download PDF</button>` : ''}
                        ${urls.ics ? `<button class="button outline ticket-download" type="button" data-url="${escapeHtml(urls.ics)}" data-filename="${escapeHtml(ticketNumber + '.ics')}">Add to calendar</button>` : ''}
                    </div>
                </article>
            `;
        }

        async function loadTicketQrImages() {
            const holders = [...document.querySelectorAll('.qr-holder[data-qr-url]')].filter((node) => node.dataset.qrUrl && !node.dataset.loaded);
            for (const holder of holders) {
                holder.dataset.loaded = '1';
                try {
                    const response = await fetch(holder.dataset.qrUrl, {
                        headers: { Accept: 'image/svg+xml', Authorization: `Bearer ${currentUser.api_token}` },
                    });
                    if (!response.ok) throw new Error('QR unavailable');
                    const blobUrl = URL.createObjectURL(await response.blob());
                    holder.innerHTML = `<img src="${blobUrl}" alt="Ticket QR code">`;
                } catch {
                    holder.textContent = 'QR unavailable';
                }
            }
        }

        function renderPayments() {
            const rows = payableRows();
            document.getElementById('paymentsList').innerHTML = rows.length
                ? rows.map(({ booking, installment }) => renderPaymentDue(booking, installment)).join('')
                : '<div class="empty">No pending Goshen Retreat payment is waiting on your account.</div>';
        }

        function renderPaymentDue(booking, installment) {
            const balance = Math.max(0, Number(installment.amount || 0) - Number(installment.paid_amount || 0));
            return `
                <article class="record">
                    <div class="record-top">
                        <div class="record-title">
                            <strong>${escapeHtml(installment.label || 'Retreat payment')}</strong>
                            <span class="item-meta">${escapeHtml(booking.event?.name || 'Goshen Retreat')} - due ${escapeHtml(formatDate(installment.due_on))}</span>
                            ${statusBadge(installment.status)}
                        </div>
                        <strong>${escapeHtml(formatMoney(balance, installment.currency || booking.currency))}</strong>
                    </div>
                    <div class="record-actions">
                        <button class="button small checkout-button" type="button" data-booking-id="${escapeHtml(booking.public_id)}" data-payment-id="${escapeHtml(installment.public_id)}">Pay by card</button>
                        <button class="button small subtle wallet-pay-button" type="button" data-booking-id="${escapeHtml(booking.public_id)}">Use wallet</button>
                    </div>
                    <form class="voucher-pay-form form" data-booking-id="${escapeHtml(booking.public_id)}">
                        <div class="field">
                            <label>Voucher code</label>
                            <input class="input" name="voucher_code" autocomplete="off">
                        </div>
                        <button class="button outline" type="submit">Redeem voucher</button>
                    </form>
                </article>
            `;
        }

        function renderReceipts() {
            const rows = memberData.payment_history || [];
            document.getElementById('receiptsList').innerHTML = rows.length
                ? rows.map((item) => `
                    <article class="record">
                        <div class="record-top">
                            <div class="record-title">
                                <strong>${escapeHtml(formatMoney(item.amount, item.currency))}</strong>
                                <span class="item-meta">${escapeHtml(item.event?.name || 'Goshen Retreat')} - ${escapeHtml(item.gateway || 'payment')}</span>
                                <span class="item-meta">Reference: ${escapeHtml(item.reference || item.public_id || '')}</span>
                            </div>
                            ${statusBadge(item.status)}
                        </div>
                        <span class="item-meta">${escapeHtml(formatDateTime(item.paid_at || item.created_at))}</span>
                    </article>
                `).join('')
                : '<div class="empty">No Goshen Retreat payment receipt is available yet.</div>';
        }

        function renderInboxMessage(message) {
            return `
                <article class="record">
                    <div class="record-title">
                        <strong>${escapeHtml(message.title || message.subject || 'Retreat update')}</strong>
                        <span class="item-meta">${escapeHtml(formatDateTime(message.published_at || message.created_at || message.dateInserted))}</span>
                    </div>
                    <p class="muted">${escapeHtml(message.message || message.content || message.body || '')}</p>
                </article>
            `;
        }

        function selectedAttr(value, selected) {
            return `${value ?? ''}` === `${selected ?? ''}` ? 'selected' : '';
        }

        function optionMarkup(options, selected, placeholder = 'Please select') {
            return `<option value="">${escapeHtml(placeholder)}</option>` + options
                .map((option) => {
                    const value = option && typeof option === 'object' ? option.value : option;
                    const label = option && typeof option === 'object' ? option.label : option;
                    return `<option value="${escapeHtml(value)}" ${selectedAttr(value, selected)}>${escapeHtml(label)}</option>`;
                })
                .join('');
        }

        function groupOptionsMarkup(selected) {
            const groups = churchGroupsCache.length
                ? churchGroupsCache
                : (currentUser?.group_id ? [{ id: currentUser.group_id, name: currentUser.group_name || 'Current church group' }] : []);
            return '<option value="">Please select</option>' + groups
                .map((group) => `<option value="${escapeHtml(group.id)}" ${selectedAttr(group.id, selected)}>${escapeHtml(group.name)}</option>`)
                .join('');
        }

        function renderProfile() {
            const user = currentUser || {};
            const nameParts = splitName(user.name || '');
            const firstName = user.first_name || nameParts.first;
            const lastName = user.last_name || nameParts.last;
            document.getElementById('profileCard').innerHTML = `
                <h3>${escapeHtml(user.name || 'Member')}</h3>
                <div class="detail-list">
                    <div class="detail-row"><strong>Triumphant ID</strong><span>${escapeHtml(user.triumphant_id || 'Not assigned yet')}</span></div>
                    <div class="detail-row"><strong>Email</strong><span>${escapeHtml(user.email || '')}</span></div>
                    <div class="detail-row"><strong>Phone</strong><span>${escapeHtml(user.phone || '')}</span></div>
                    <div class="detail-row"><strong>Church group</strong><span>${escapeHtml(user.group_name || 'Not selected')}</span></div>
                    <div class="detail-row"><strong>Residence</strong><span>${escapeHtml([user.country_of_residence, user.state_county_province].filter(Boolean).join(', ') || 'Not provided')}</span></div>
                </div>
                <h4 class="profile-section-title">Edit profile</h4>
                <form class="form profile-update-form">
                    <input type="hidden" name="email" value="${escapeHtml(user.email || '')}">
                    <div class="form-grid profile-form-grid">
                        <div class="field">
                            <label>Title</label>
                            <select class="input" name="title" required>${optionMarkup(['Mr.', 'Mrs.', 'Miss'], user.title || user.profile_title)}</select>
                        </div>
                        <div class="field">
                            <label>First name</label>
                            <input class="input" name="first_name" autocomplete="given-name" value="${escapeHtml(firstName)}" required>
                        </div>
                        <div class="field">
                            <label>Middle name</label>
                            <input class="input" name="middle_name" value="${escapeHtml(user.middle_name || '')}">
                        </div>
                        <div class="field">
                            <label>Last name</label>
                            <input class="input" name="last_name" autocomplete="family-name" value="${escapeHtml(lastName)}" required>
                        </div>
                        <div class="field">
                            <label>Phone number</label>
                            <input class="input" name="phone" type="tel" autocomplete="tel" value="${escapeHtml(user.phone || '')}" required>
                        </div>
                        <div class="field">
                            <label>Gender</label>
                            <select class="input" name="gender" required>${optionMarkup([{ value: 'male', label: 'Male' }, { value: 'female', label: 'Female' }], user.gender)}</select>
                        </div>
                        <div class="field">
                            <label>Marital status</label>
                            <select class="input" name="marital_status" required>${optionMarkup(['Single', 'Married', 'Widowed', 'Divorced/Separated', 'Prefer not to say'], user.marital_status)}</select>
                        </div>
                        <div class="field">
                            <label>Member type</label>
                            <select class="input" name="member_type" required>${optionMarkup([{ value: 'church_member', label: 'Church member' }, { value: 'visitor', label: 'Visitor' }], user.member_type || 'church_member')}</select>
                        </div>
                        <div class="field">
                            <label>Church group</label>
                            <select class="input" name="group_id" required>${groupOptionsMarkup(user.group_id)}</select>
                        </div>
                        <div class="field">
                            <label>Country of residence</label>
                            <input class="input" name="country_of_residence" autocomplete="country-name" value="${escapeHtml(user.country_of_residence || '')}" required>
                        </div>
                        <div class="field">
                            <label>State/county/province</label>
                            <input class="input" name="state_county_province" autocomplete="address-level1" value="${escapeHtml(user.state_county_province || '')}" required>
                        </div>
                        <div class="field">
                            <label>Address</label>
                            <textarea class="input" name="address" autocomplete="street-address" required>${escapeHtml(user.address || '')}</textarea>
                        </div>
                        <div class="field">
                            <label>About me</label>
                            <textarea class="input" name="about_me">${escapeHtml(user.about_me || '')}</textarea>
                        </div>
                    </div>
                    <button class="button" type="submit">Save profile</button>
                </form>
            `;
        }

        function renderSupport() {
            const event = eventsCache[0] || {};
            const email = event.support_email || 'Support details will be published by the church.';
            const phone = event.support_phone || event.phone || '';
            document.getElementById('supportCard').innerHTML = `
                <h3>Need help with Goshen Retreat?</h3>
                <p class="muted">Contact the retreat support team for registration, ticket, or payment help.</p>
                <div class="detail-list">
                    <div class="detail-row"><strong>Email</strong><span>${escapeHtml(email)}</span></div>
                    ${phone ? `<div class="detail-row"><strong>Phone</strong><span>${escapeHtml(phone)}</span></div>` : ''}
                    <div class="detail-row"><strong>Account</strong><span>${escapeHtml(currentUser?.email || '')}</span></div>
                </div>
            `;
        }

        async function startCheckout(bookingId, paymentId) {
            const payload = await apiPost(`/api/goshen-retreat/bookings/${encodeURIComponent(bookingId)}/payments/${encodeURIComponent(paymentId)}/checkout`, authPayload());
            const url = payload.checkout?.checkout_url || payload.checkout?.url;
            if (!url) throw new Error('Checkout link was not returned. Please try again.');
            window.location.href = url;
        }

        function walletFormPayload(form) {
            const data = payloadFromForm(form);
            Object.keys(data).forEach((key) => {
                const value = typeof data[key] === 'string' ? data[key].trim() : data[key];
                if (value === '') {
                    delete data[key];
                } else {
                    data[key] = key === 'currency' ? `${value}`.toUpperCase() : value;
                }
            });
            return data;
        }

        async function walletTopUpCheckout(data) {
            const payload = await apiPost('/api/goshen-wallet/top-up/checkout', authPayload(data));
            const url = payload.checkout?.checkout_url || payload.checkout?.url || payload.checkout_url || payload.url;
            if (!url) throw new Error('Wallet checkout link was not returned. Please try again.');
            window.location.href = url;
        }

        function applyWalletPayload(payload, fallbackMessage) {
            if (payload.data) {
                walletData = payload.data;
                renderWallet();
                renderHome();
            }
            notify(payload.message || fallbackMessage);
        }

        async function walletPay(bookingId, button) {
            button.disabled = true;
            try {
                const payload = await apiPost(`/api/goshen-retreat/bookings/${encodeURIComponent(bookingId)}/wallet-pay`, authPayload());
                notify(payload.message || 'Wallet payment completed.');
                await loadMemberRetreatData();
                await loadWallet();
                showPage('tickets');
            } catch (error) {
                notify(error.message, 'error');
            } finally {
                button.disabled = false;
            }
        }

        async function downloadAuthenticatedDocument(url, filename) {
            const response = await fetch(url, {
                method: 'POST',
                headers: { Accept: 'application/octet-stream, application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${currentUser.api_token}` },
                body: JSON.stringify({ data: authPayload() }),
            });
            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                throw new Error(messageFromErrorPayload(payload, 'Document download failed.'));
            }
            const blob = await response.blob();
            const objectUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = objectUrl;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
        }

        document.querySelectorAll('[data-auth-tab]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                showAuth(button.dataset.authTab);
                showAuthNotice('');
            });
        });

        document.getElementById('loginForm').addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const data = payloadFromForm(form);
            data.email = `${data.email || ''}`.trim().toLowerCase();
            setBusy(form, true);
            showAuthNotice('');
            try {
                const payload = await apiPost('/api/loginUser', data);
                saveUser(payload.user);
                notify(`Welcome back, ${payload.user?.name || 'member'}.`);
            } catch (error) {
                showAuthNotice(error.message, 'error');
            } finally {
                setBusy(form, false);
            }
        });

        document.getElementById('registerForm').addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const data = payloadFromForm(form);
            data.email = `${data.email || ''}`.trim().toLowerCase();
            if (data.password !== data.password_confirmation) {
                showAuthNotice('Password confirmation does not match.', 'error');
                return;
            }
            data.name = [data.first_name, data.last_name].filter(Boolean).join(' ');
            delete data.password_confirmation;
            setBusy(form, true);
            showAuthNotice('');
            try {
                const payload = await apiPost('/api/registerUser', data);
                document.getElementById('verifyEmail').value = data.email;
                showAuth('verify');
                showAuthNotice(payload.message || 'Account created. Enter the verification code sent to your email.');
            } catch (error) {
                showAuthNotice(error.message, 'error');
            } finally {
                setBusy(form, false);
            }
        });

        document.getElementById('verifyForm').addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const data = payloadFromForm(form);
            data.email = `${data.email || ''}`.trim().toLowerCase();
            setBusy(form, true);
            showAuthNotice('');
            try {
                const payload = await apiPost('/api/verifyMobileEmail', data);
                saveUser(payload.user);
                notify('Your account is verified.');
            } catch (error) {
                showAuthNotice(error.message, 'error');
            } finally {
                setBusy(form, false);
            }
        });

        document.getElementById('resendCode').addEventListener('click', async () => {
            const email = document.getElementById('verifyEmail').value;
            if (!email) {
                showAuthNotice('Enter your email address first.', 'error');
                return;
            }
            try {
                const payload = await apiPost('/api/resendMobileVerification', { email });
                showAuthNotice(payload.message || 'A fresh verification code has been sent.');
            } catch (error) {
                showAuthNotice(error.message, 'error');
            }
        });

        document.getElementById('forgotForm').addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const data = payloadFromForm(form);
            data.email = `${data.email || ''}`.trim().toLowerCase();
            setBusy(form, true);
            showAuthNotice('');
            try {
                const payload = await apiPost('/api/requestPasswordReset', data);
                document.getElementById('resetEmail').value = data.email;
                showAuth('reset');
                showAuthNotice(payload.message || 'If this email is registered, a password reset code has been sent.');
            } catch (error) {
                showAuthNotice(error.message, 'error');
            } finally {
                setBusy(form, false);
            }
        });

        document.getElementById('resetForm').addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const data = payloadFromForm(form);
            data.email = `${data.email || ''}`.trim().toLowerCase();
            setBusy(form, true);
            showAuthNotice('');
            try {
                const payload = await apiPost('/api/resetMobilePassword', data);
                showAuth('login');
                showAuthNotice(payload.message || 'Password reset successfully. Please sign in.');
            } catch (error) {
                showAuthNotice(error.message, 'error');
            } finally {
                setBusy(form, false);
            }
        });

        document.querySelectorAll('[data-nav-page]').forEach((button) => {
            button.addEventListener('click', () => showPage(button.dataset.navPage));
        });

        document.getElementById('openDrawer').addEventListener('click', openDrawer);
        drawerBackdrop.addEventListener('click', (event) => {
            if (event.target === drawerBackdrop) closeDrawer();
        });
        window.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeDrawer();
        });
        window.addEventListener('popstate', () => {
            if (currentUser) showPage(currentPathSegment(), false);
        });

        document.getElementById('sidebarLogout').addEventListener('click', () => clearUser());
        document.getElementById('drawerLogout').addEventListener('click', () => clearUser());
        document.querySelectorAll('[data-theme-mode]').forEach((button) => {
            button.addEventListener('click', () => setThemeMode(button.dataset.themeMode));
        });
        window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener?.('change', () => {
            if (savedThemeMode() === 'device') applyTheme('device');
        });

        document.getElementById('portalMain').addEventListener('input', (event) => {
            const quantity = event.target.closest('.attendee-quantity');
            if (quantity) {
                const form = quantity.closest('.registration-form');
                const eventModel = eventsCache.find((item) => `${item.public_id}` === `${form.dataset.eventId}`);
                form.querySelector('.attendee-fields').innerHTML = renderAttendeeFields(quantity.value, eventModel);
            }
            const mode = event.target.closest('.payment-mode');
            if (mode) {
                const form = mode.closest('.registration-form');
                form.querySelector('.voucher-field').hidden = mode.value !== 'voucher';
                form.querySelector('[name="voucher_code"]').required = mode.value === 'voucher';
            }
        });

        document.getElementById('portalMain').addEventListener('submit', async (event) => {
            const registration = event.target.closest('.registration-form');
            if (registration) {
                event.preventDefault();
                submitRegistration(registration);
                return;
            }
            const walletTopUp = event.target.closest('.wallet-topup-form');
            if (walletTopUp) {
                event.preventDefault();
                const data = walletFormPayload(walletTopUp);
                data.save_payment_method = walletTopUp.querySelector('[name="save_payment_method"]')?.checked === true;
                setBusy(walletTopUp, true);
                try {
                    await walletTopUpCheckout(data);
                } catch (error) {
                    notify(error.message, 'error');
                    setBusy(walletTopUp, false);
                }
                return;
            }
            const walletGoal = event.target.closest('.wallet-goal-form');
            if (walletGoal) {
                event.preventDefault();
                const data = walletFormPayload(walletGoal);
                const goalId = data.goal_id;
                if (!goalId) delete data.goal_id;
                setBusy(walletGoal, true);
                try {
                    const url = goalId ? `/api/goshen-wallet/goals/${encodeURIComponent(goalId)}` : '/api/goshen-wallet/goal';
                    const payload = await apiPost(url, authPayload(data));
                    applyWalletPayload(payload, 'Savings goal updated.');
                } catch (error) {
                    notify(error.message, 'error');
                } finally {
                    setBusy(walletGoal, false);
                }
                return;
            }
            const walletPlan = event.target.closest('.wallet-plan-form');
            if (walletPlan) {
                event.preventDefault();
                const data = walletFormPayload(walletPlan);
                setBusy(walletPlan, true);
                try {
                    const payload = await apiPost('/api/goshen-wallet/savings-plans', authPayload(data));
                    applyWalletPayload(payload, 'Auto top-up plan created.');
                } catch (error) {
                    notify(error.message, 'error');
                } finally {
                    setBusy(walletPlan, false);
                }
                return;
            }
            const walletTransfer = event.target.closest('.wallet-transfer-form');
            if (walletTransfer) {
                event.preventDefault();
                const data = walletFormPayload(walletTransfer);
                setBusy(walletTransfer, true);
                try {
                    const payload = await apiPost('/api/goshen-wallet/transfer', authPayload(data));
                    applyWalletPayload(payload, 'Wallet transfer completed.');
                    walletTransfer.reset();
                    walletTransfer.querySelector('[name="currency"]').value = walletData?.currency || 'GBP';
                } catch (error) {
                    notify(error.message, 'error');
                } finally {
                    setBusy(walletTransfer, false);
                }
                return;
            }
            const walletWithdrawal = event.target.closest('.wallet-withdrawal-form');
            if (walletWithdrawal) {
                event.preventDefault();
                const data = walletFormPayload(walletWithdrawal);
                setBusy(walletWithdrawal, true);
                try {
                    const payload = await apiPost('/api/goshen-wallet/withdrawals', authPayload(data));
                    applyWalletPayload(payload, 'Withdrawal request submitted.');
                    walletWithdrawal.reset();
                    walletWithdrawal.querySelector('[name="currency"]').value = walletData?.currency || 'GBP';
                } catch (error) {
                    notify(error.message, 'error');
                } finally {
                    setBusy(walletWithdrawal, false);
                }
                return;
            }
            const profileUpdate = event.target.closest('.profile-update-form');
            if (profileUpdate) {
                event.preventDefault();
                if (!profileUpdate.reportValidity()) return;
                const data = payloadFromForm(profileUpdate);
                data.fullname = [data.first_name, data.middle_name, data.last_name].filter(Boolean).join(' ');
                setBusy(profileUpdate, true);
                try {
                    const payload = await apiPost('/api/updateProfile', authPayload(data));
                    currentUser = { ...currentUser, ...(payload.user || {}), api_token: payload.user?.api_token || currentUser.api_token };
                    localStorage.setItem(storageKey, JSON.stringify(currentUser));
                    updateUserIdentity();
                    renderProfile();
                    notify(payload.message || 'Profile updated successfully.');
                    await loadMemberRetreatData();
                } catch (error) {
                    notify(error.message, 'error');
                } finally {
                    setBusy(profileUpdate, false);
                }
                return;
            }
            const voucher = event.target.closest('.voucher-pay-form');
            if (voucher) {
                event.preventDefault();
                const data = payloadFromForm(voucher);
                if (!data.voucher_code) {
                    notify('Enter a voucher code first.', 'error');
                    return;
                }
                setBusy(voucher, true);
                try {
                    const payload = await apiPost(`/api/goshen-retreat/bookings/${encodeURIComponent(voucher.dataset.bookingId)}/voucher-pay`, authPayload({ voucher_code: data.voucher_code }));
                    notify(payload.message || 'Voucher applied.');
                    await loadMemberRetreatData();
                    showPage('tickets');
                } catch (error) {
                    notify(error.message, 'error');
                } finally {
                    setBusy(voucher, false);
                }
            }
        });

        document.getElementById('portalMain').addEventListener('click', async (event) => {
            const walletTab = event.target.closest('[data-wallet-tab]');
            if (walletTab) {
                setWalletTab(walletTab.dataset.walletTab);
                return;
            }
            const nav = event.target.closest('[data-nav-page]');
            if (nav) {
                showPage(nav.dataset.navPage);
                return;
            }
            const checkout = event.target.closest('.checkout-button');
            if (checkout) {
                checkout.disabled = true;
                try {
                    await startCheckout(checkout.dataset.bookingId, checkout.dataset.paymentId);
                } catch (error) {
                    notify(error.message, 'error');
                    checkout.disabled = false;
                }
                return;
            }
            const wallet = event.target.closest('.wallet-pay-button');
            if (wallet) {
                walletPay(wallet.dataset.bookingId, wallet);
                return;
            }
            const newGoal = event.target.closest('.wallet-new-goal');
            if (newGoal) {
                const form = newGoal.closest('.wallet-goal-form');
                const data = walletFormPayload(form);
                delete data.goal_id;
                data.is_primary = false;
                newGoal.disabled = true;
                try {
                    const payload = await apiPost('/api/goshen-wallet/goals', authPayload(data));
                    applyWalletPayload(payload, 'Savings goal added.');
                } catch (error) {
                    notify(error.message, 'error');
                    newGoal.disabled = false;
                }
                return;
            }
            const cancelGoal = event.target.closest('.wallet-cancel-goal');
            if (cancelGoal) {
                cancelGoal.disabled = true;
                try {
                    const payload = await apiPost(`/api/goshen-wallet/goals/${encodeURIComponent(cancelGoal.dataset.goalId)}/cancel`, authPayload());
                    applyWalletPayload(payload, 'Savings goal cancelled.');
                } catch (error) {
                    notify(error.message, 'error');
                    cancelGoal.disabled = false;
                }
                return;
            }
            const planStatus = event.target.closest('.wallet-plan-status');
            if (planStatus) {
                planStatus.disabled = true;
                try {
                    const payload = await apiPost(`/api/goshen-wallet/savings-plans/${encodeURIComponent(planStatus.dataset.planId)}`, authPayload({ status: planStatus.dataset.status }));
                    applyWalletPayload(payload, 'Auto top-up plan updated.');
                } catch (error) {
                    notify(error.message, 'error');
                    planStatus.disabled = false;
                }
                return;
            }
            const planSetup = event.target.closest('.wallet-plan-setup');
            if (planSetup) {
                planSetup.disabled = true;
                try {
                    await walletTopUpCheckout({
                        amount: planSetup.dataset.amount,
                        currency: planSetup.dataset.currency || walletData?.currency || 'GBP',
                        save_payment_method: true,
                        savings_plan_id: planSetup.dataset.planId,
                    });
                } catch (error) {
                    notify(error.message, 'error');
                    planSetup.disabled = false;
                }
                return;
            }
            const cancelWithdrawal = event.target.closest('.wallet-cancel-withdrawal');
            if (cancelWithdrawal) {
                cancelWithdrawal.disabled = true;
                try {
                    const payload = await apiPost(`/api/goshen-wallet/withdrawals/${encodeURIComponent(cancelWithdrawal.dataset.withdrawalId)}/cancel`, authPayload());
                    applyWalletPayload(payload, 'Withdrawal request cancelled.');
                } catch (error) {
                    notify(error.message, 'error');
                    cancelWithdrawal.disabled = false;
                }
                return;
            }
            const download = event.target.closest('.ticket-download');
            if (download) {
                download.disabled = true;
                try {
                    await downloadAuthenticatedDocument(download.dataset.url, download.dataset.filename || 'goshen-ticket');
                } catch (error) {
                    notify(error.message, 'error');
                } finally {
                    download.disabled = false;
                }
            }
        });

        applyTheme(savedThemeMode());
        initializeGoogleLogin();
        loadGroups();
        restoreUser();

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/member-sw.js').catch(() => {});
            });
        }
    </script>
</body>
</html>
