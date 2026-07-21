<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0c2230">
    <meta name="description" content="Access Goshen Retreat registration, tickets, wallet payments, updates, and member services for MFM Triumphant Church.">
    <meta name="application-name" content="Goshen Retreat Portal">
    <meta name="apple-mobile-web-app-title" content="Goshen Retreat">
    <meta property="og:title" content="Goshen Retreat Portal | MFM Triumphant Church">
    <meta property="og:description" content="Access Goshen Retreat registration, tickets, wallet payments, updates, and member services for MFM Triumphant Church.">
    <meta property="og:type" content="website">
    <meta property="og:image" content="/icons/goshen-icon-512.png">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="Goshen Retreat Portal | MFM Triumphant Church">
    <meta name="twitter:description" content="Access Goshen Retreat registration, tickets, wallet payments, updates, and member services for MFM Triumphant Church.">
    <meta name="twitter:image" content="/icons/goshen-icon-512.png">
    <title>Goshen Retreat Portal | MFM Triumphant Church</title>
    <link rel="manifest" href="/member-manifest.json?v=20260718">
    <link rel="icon" href="/favicon.ico?v=20260718" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/goshen-icon-32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/goshen-icon-192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/goshen-icon-180.png">
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
        html, body {
            min-height: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }
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

        body.migration-notice-open {
            overflow: hidden;
        }

        .migration-notice {
            position: fixed;
            inset: 0;
            z-index: 90;
            display: grid;
            place-items: center;
            padding: 22px;
            background:
                radial-gradient(circle at 18% 14%, rgba(248, 181, 34, .18), transparent 18rem),
                rgba(7, 21, 29, .68);
            backdrop-filter: blur(12px);
        }

        .migration-notice-card {
            width: min(100%, 560px);
            max-height: min(88vh, 720px);
            overflow: auto;
            background: var(--card);
            color: var(--ink);
            border: 1px solid var(--line);
            border-radius: 32px;
            box-shadow: 0 28px 80px rgba(0, 0, 0, .26);
            padding: clamp(24px, 5vw, 34px);
        }

        .migration-notice-icon {
            width: 64px;
            height: 64px;
            display: grid;
            place-items: center;
            border-radius: 999px;
            overflow: hidden;
            background: #4d2f65;
            box-shadow: 0 14px 32px rgba(248, 181, 34, .22);
        }

        .migration-notice-icon img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }

        .migration-notice h2 {
            margin: 18px 0 10px;
            font-size: clamp(28px, 7vw, 42px);
            line-height: 1;
            letter-spacing: -.04em;
        }

        .migration-notice-lead {
            margin: 0;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.65;
        }

        .migration-notice-list {
            display: grid;
            gap: 12px;
            margin: 22px 0;
            padding: 0;
            list-style: none;
        }

        .migration-notice-list li {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 12px;
            align-items: start;
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: var(--field);
            color: var(--muted);
            line-height: 1.5;
        }

        .migration-notice-list strong {
            color: var(--ink);
        }

        .migration-notice-check {
            width: 28px;
            height: 28px;
            display: grid;
            place-items: center;
            border-radius: 999px;
            background: rgba(248, 181, 34, .2);
            color: var(--brand-2);
            font-weight: 1000;
        }

        .migration-notice-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 22px;
        }

        .migration-notice-actions .button {
            width: auto;
            min-width: 0;
            flex: 1 1 190px;
            text-align: center;
            text-decoration: none;
        }

        .migration-notice-close {
            appearance: none;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: transparent;
            color: var(--ink);
            cursor: pointer;
            font-weight: 1000;
            padding: 14px 18px;
        }

        .migration-notice-close:focus-visible,
        .migration-notice-actions .button:focus-visible {
            outline: 3px solid rgba(248, 181, 34, .38);
            outline-offset: 3px;
        }

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
        .password-control {
            position: relative;
            display: grid;
        }
        .password-control .input {
            padding-right: 86px;
        }
        .password-reveal {
            position: absolute;
            top: 50%;
            right: 8px;
            transform: translateY(-50%);
            min-height: 38px;
            border: 0;
            border-radius: 13px;
            padding: 0 12px;
            background: var(--card);
            color: var(--brand-2);
            box-shadow: inset 0 0 0 1px var(--line);
            cursor: pointer;
            font-size: 13px;
            font-weight: 1000;
        }
        .password-reveal:focus-visible {
            outline: 3px solid rgba(248, 181, 34, .36);
            outline-offset: 2px;
        }

        .quantity-stepper {
            display: grid;
            grid-template-columns: 54px minmax(84px, 1fr) 54px;
            gap: 10px;
            align-items: center;
        }
        .quantity-stepper-button {
            width: 54px;
            min-height: 54px;
            border: 1px solid var(--line);
            border-radius: 17px;
            background: var(--card);
            color: var(--ink);
            display: inline-grid;
            place-items: center;
            cursor: pointer;
            font-size: 28px;
            font-weight: 1000;
            line-height: 1;
        }
        .quantity-stepper-button.add {
            border-color: transparent;
            background: var(--gold);
            color: #0c2230;
        }
        .quantity-stepper-button:disabled {
            cursor: not-allowed;
            opacity: .42;
        }
        .attendee-quantity-value {
            min-height: 54px;
            border: 1px solid var(--line);
            border-radius: 17px;
            background: var(--field);
            color: var(--ink);
            display: grid;
            place-items: center;
            padding: 0 16px;
            font-size: 22px;
            font-weight: 1000;
        }
        .attendee-quantity-field {
            grid-column: 1 / -1;
        }
        .attendee-quantity-field .hint {
            margin-top: 2px;
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
        .registration-profile-notice {
            display: grid;
            gap: 10px;
            margin: 0 0 18px;
            padding: 16px;
            border: 1px solid color-mix(in srgb, var(--gold) 54%, var(--line));
            border-radius: 16px;
            background: color-mix(in srgb, var(--gold-2) 48%, var(--card));
            color: var(--brand);
        }
        .registration-profile-notice strong { font-size: 16px; }
        .registration-profile-notice p {
            margin: 0;
            color: var(--ink);
            line-height: 1.5;
        }
        .registration-profile-notice .inline-actions { margin-top: 2px; }
        .profile-completion-notice {
            position: fixed;
            z-index: 70;
            inset: 0;
            display: grid;
            place-items: center;
            padding: 20px;
            background: rgba(7, 21, 29, .58);
        }
        .profile-completion-notice-card {
            width: min(100%, 480px);
            display: grid;
            gap: 16px;
            padding: 26px;
            border: 1px solid var(--line);
            border-radius: 20px;
            background: var(--card);
            box-shadow: 0 22px 54px rgba(7, 21, 29, .3);
        }
        .profile-completion-notice-card h2,
        .profile-completion-notice-card p { margin: 0; }
        .profile-completion-notice-card p { color: var(--muted); line-height: 1.55; }
        .profile-completion-notice-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .profile-completion-notice-actions .button { flex: 1 1 180px; }

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
        .user-chip-header {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            align-items: center;
            gap: 12px;
        }
        .user-chip-avatar {
            flex: 0 0 46px;
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            border-radius: 999px;
            overflow: hidden;
            border: 2px solid rgba(248, 181, 34, .72);
            background: rgba(255,255,255,.12);
            box-shadow: 0 10px 24px rgba(0,0,0,.18);
        }
        .user-chip-avatar.small {
            flex-basis: 34px;
            width: 34px;
            height: 34px;
            border-width: 1px;
        }
        .user-chip-avatar img,
        .user-chip-avatar span {
            width: 100%;
            height: 100%;
            border-radius: inherit;
        }
        .user-chip-avatar img {
            object-fit: cover;
        }
        .user-chip-avatar span {
            display: grid;
            place-items: center;
            color: #fff;
            background: linear-gradient(145deg, var(--brand), var(--brand-2));
            font-size: 16px;
            font-weight: 950;
        }
        .user-chip-avatar.small span {
            font-size: 12px;
        }
        .user-chip-main {
            min-width: 0;
            display: grid;
            gap: 3px;
        }
        .user-chip-main strong,
        .user-chip-main span {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .user-chip-main span { color: rgba(255,255,255,.7); font-size: 13px; }
        .header-logout-button {
            grid-column: 1 / -1;
            width: 100%;
            justify-content: center;
            min-height: 44px;
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 13px;
            padding: 0 12px;
            background: rgba(255, 255, 255, .08);
            color: #ffd6d6;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            cursor: pointer;
            font-weight: 1000;
            white-space: nowrap;
        }
        .header-logout-button:hover {
            background: rgba(180, 35, 24, .22);
            color: #fff;
        }
        .header-logout-button:focus-visible {
            outline: 3px solid rgba(248, 181, 34, .92);
            outline-offset: 3px;
        }
        .header-logout-button .nav-icon {
            width: 20px;
            height: 20px;
        }

        .nav-list {
            display: grid;
            gap: 8px;
            overflow-y: auto;
            overscroll-behavior: contain;
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
        .nav-item.logout {
            margin-top: 10px;
            color: #ffd6d6;
        }
        .nav-item.logout:hover {
            background: rgba(180, 35, 24, .22);
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
            max-width: 100%;
            min-width: 0;
            margin: 0 auto;
            padding: 20px 18px calc(var(--bottom-nav) + env(safe-area-inset-bottom) + 24px);
        }

        .page-view { display: none; animation: fade .18s ease-out; }
        .page-view.active {
            display: grid;
            gap: 18px;
            min-width: 0;
            max-width: 100%;
        }
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
        .dashboard-triumphant-id {
            display: inline-flex;
            align-items: center;
            width: max-content;
            max-width: 100%;
            min-height: 36px;
            margin: 4px 0 14px;
            border-radius: 999px;
            padding: 0 14px;
            background: rgba(248, 181, 34, .16);
            color: #fff;
            box-shadow: inset 0 0 0 1px rgba(248, 181, 34, .34);
            font-size: 13px;
            font-weight: 1000;
            letter-spacing: .04em;
            overflow-wrap: anywhere;
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
            max-width: 100%;
            min-width: 0;
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
        .event-countdown .stats-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }
        .event-countdown .stat {
            min-height: 112px;
            padding: 12px 8px;
            display: grid;
            align-content: center;
            justify-items: center;
            text-align: center;
        }
        .event-countdown .stat strong {
            margin-top: 0;
            font-size: clamp(24px, 8vw, 34px);
        }
        .event-countdown .stat span {
            margin-top: 4px;
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
            position: relative;
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
        .event-media::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(12, 34, 48, .14), transparent 36%, rgba(12, 34, 48, .72));
            pointer-events: none;
        }
        .event-media-pill {
            position: absolute;
            z-index: 1;
            left: 16px;
            top: 16px;
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            border-radius: 999px;
            padding: 0 12px;
            background: var(--gold);
            color: #0c2230;
            font-size: 13px;
            font-weight: 1000;
        }
        .event-media-date {
            position: absolute;
            z-index: 1;
            right: 16px;
            bottom: 16px;
            max-width: min(80%, 420px);
            border-radius: 999px;
            padding: 8px 12px;
            background: rgba(12, 34, 48, .88);
            color: #fff;
            font-size: 13px;
            font-weight: 900;
            line-height: 1.35;
            text-align: right;
        }
        .event-description {
            margin-top: 10px;
            color: var(--muted);
            line-height: 1.58;
        }
        .inline-action {
            display: inline-flex;
            align-items: center;
            width: max-content;
            margin-top: 8px;
            color: var(--brand-2);
            font-weight: 900;
            text-decoration: none;
        }
        .past-video-slider {
            display: flex;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            gap: 12px;
            margin: 0 -6px;
            padding: 4px 6px 10px;
            overflow-x: auto;
            overflow-y: hidden;
            overscroll-behavior-inline: contain;
            scroll-snap-type: inline mandatory;
            scrollbar-width: thin;
            contain: inline-size;
        }
        .past-video-card {
            flex: 0 0 clamp(216px, 72vw, 320px);
            display: grid;
            gap: 10px;
            align-content: start;
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 10px;
            background: var(--field);
            color: inherit;
            text-decoration: none;
            scroll-snap-align: start;
            min-width: 0;
            max-width: calc(100vw - 48px);
        }
        .past-video-card iframe,
        .past-video-card img {
            width: 100%;
            aspect-ratio: 16 / 9;
            border-radius: 14px;
            border: 0;
            background: linear-gradient(135deg, #0c2230, #14513f);
        }
        .past-video-card img {
            object-fit: cover;
        }
        .past-video-card strong { overflow-wrap: anywhere; }
        .past-video-card .item-meta {
            display: -webkit-box;
            overflow: hidden;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
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
        .profile-triumphant-id {
            display: grid;
            justify-items: center;
            gap: 8px;
            width: min(100%, 440px);
            margin: 18px auto 16px;
            padding: 20px 18px;
            border: 1px solid var(--line);
            border-radius: 26px;
            background: linear-gradient(135deg, var(--gold-2), var(--field));
            box-shadow: var(--soft-shadow);
            text-align: center;
        }
        .profile-triumphant-id span {
            color: var(--muted);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .14em;
            text-transform: uppercase;
        }
        .profile-triumphant-id strong {
            color: var(--brand-2);
            font-size: clamp(36px, 9vw, 58px);
            line-height: .95;
            letter-spacing: .08em;
            overflow-wrap: anywhere;
        }
        .referral-share-card {
            display: grid;
            gap: 16px;
            margin: 18px 0;
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 20px;
            background: var(--field);
        }
        .referral-share-media {
            width: 100%;
            aspect-ratio: 16 / 7;
            object-fit: cover;
            border-radius: 14px;
            background: var(--brand);
        }
        .referral-share-copy {
            display: grid;
            gap: 5px;
        }
        .referral-share-copy strong { font-size: 18px; }
        .referral-share-copy span { color: var(--muted); line-height: 1.5; }
        .referral-share-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .referral-share-actions .button { min-width: 0; }
        .referral-share-actions .referral-share-button { grid-column: 1 / -1; }
        .referral-share-link {
            display: block;
            overflow: hidden;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.4;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        @media (max-width: 380px) {
            .referral-share-actions { grid-template-columns: 1fr; }
        }
        .profile-hero {
            position: relative;
            overflow: hidden;
            display: grid;
            gap: 18px;
            justify-items: center;
            padding: clamp(22px, 5vw, 34px);
            border: 1px solid color-mix(in srgb, var(--brand) 18%, var(--line));
            border-radius: 32px;
            background:
                radial-gradient(circle at 18% 8%, rgba(248, 181, 34, .24), transparent 15rem),
                radial-gradient(circle at 82% 0%, rgba(20, 81, 63, .18), transparent 16rem),
                linear-gradient(145deg, var(--card), var(--field));
            box-shadow: var(--shadow);
            text-align: center;
        }
        .profile-hero .profile-actions {
            position: relative;
            z-index: 1;
            justify-content: center;
            margin-top: 2px;
        }
        .profile-hero::after {
            content: '';
            position: absolute;
            inset: auto -12% -46% auto;
            width: 260px;
            height: 260px;
            border: 1px solid rgba(248, 181, 34, .22);
            border-radius: 999px;
            pointer-events: none;
        }
        .profile-avatar-frame {
            position: relative;
            z-index: 1;
            width: clamp(118px, 28vw, 158px);
            height: clamp(118px, 28vw, 158px);
            display: grid;
            place-items: center;
            border-radius: 999px;
            padding: 7px;
            background:
                linear-gradient(var(--card), var(--card)) padding-box,
                linear-gradient(135deg, var(--gold), #fff2bd 38%, var(--brand-2)) border-box;
            border: 3px solid transparent;
            box-shadow: 0 20px 44px rgba(7, 21, 29, .20);
        }
        .profile-avatar-image,
        .profile-avatar-fallback {
            width: 100%;
            height: 100%;
            border-radius: 999px;
            object-fit: cover;
            border: 1px solid rgba(255, 255, 255, .72);
        }
        .profile-avatar-fallback {
            display: grid;
            place-items: center;
            background: linear-gradient(145deg, var(--brand), var(--brand-2));
            color: #fff;
            font-size: clamp(32px, 9vw, 56px);
            font-weight: 950;
            letter-spacing: .04em;
        }
        .profile-photo-notice {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            width: min(100%, 640px);
            padding: 13px 15px;
            border: 1px solid rgba(248, 181, 34, .35);
            border-radius: 18px;
            background: color-mix(in srgb, var(--gold-2) 62%, var(--card));
            color: var(--brand);
            text-align: left;
            font-weight: 800;
            line-height: 1.45;
        }
        .profile-photo-notice span {
            display: grid;
            place-items: center;
            flex: 0 0 28px;
            width: 28px;
            height: 28px;
            border-radius: 999px;
            background: var(--gold);
            color: var(--brand);
            font-weight: 950;
        }
        .profile-identity {
            position: relative;
            z-index: 1;
            display: grid;
            gap: 8px;
            justify-items: center;
        }
        .profile-identity h3 {
            margin: 0;
            font-size: clamp(28px, 7vw, 46px);
            line-height: 1;
        }
        .profile-identity p {
            margin: 0;
            color: var(--muted);
        }
        .profile-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 18px;
        }
        .profile-detail-card {
            min-width: 0;
            padding: 15px;
            border: 1px solid var(--line);
            border-radius: 20px;
            background: var(--field);
        }
        .profile-detail-card strong {
            display: block;
            margin-bottom: 5px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 950;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .profile-detail-card span {
            display: block;
            overflow-wrap: anywhere;
            color: var(--ink);
            font-weight: 850;
            line-height: 1.4;
        }
        .profile-detail-card.wide { grid-column: 1 / -1; }
        .profile-edit-photo-card {
            display: grid;
            gap: 14px;
            justify-items: center;
            margin-bottom: 18px;
            padding: 18px;
            border: 1px dashed color-mix(in srgb, var(--gold) 54%, var(--line));
            border-radius: 26px;
            background: linear-gradient(145deg, var(--field), var(--card));
            text-align: center;
        }
        .profile-edit-photo-card .field {
            width: min(100%, 460px);
            text-align: left;
        }
        .profile-edit-photo-card input[type="file"] {
            min-height: auto;
            padding: 12px;
        }
        .profile-edit-photo-card p {
            max-width: 560px;
            margin: 0;
        }
        @media (max-width: 720px) {
            .profile-card-head {
                display: grid;
            }
            .profile-card-head .button {
                width: 100%;
            }
            .profile-detail-grid {
                grid-template-columns: 1fr;
            }
        }

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
        .profile-card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 8px;
        }
        .profile-card-head h3 {
            margin-bottom: 4px;
        }
        .profile-card-head p {
            margin-bottom: 0;
        }
        .profile-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
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
        .drawer .header-logout-button {
            border-color: var(--line);
            background: var(--card);
            color: var(--danger);
        }
        .drawer .header-logout-button:hover {
            background: rgba(180, 35, 24, .10);
            color: var(--danger);
        }
        .drawer .nav-item { color: var(--ink); }
        .drawer .nav-item.active, .drawer .nav-item:hover {
            background: var(--field);
            color: var(--brand-2);
        }
        .drawer .nav-item.logout {
            color: var(--danger);
        }
        .drawer .nav-item.logout:hover {
            background: rgba(180, 35, 24, .10);
            color: var(--danger);
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
    <div id="profileCompletionNotice" class="profile-completion-notice" role="dialog" aria-modal="true" aria-labelledby="profileCompletionNoticeTitle" aria-describedby="profileCompletionNoticeDescription" hidden>
        <div class="profile-completion-notice-card" role="document">
            <span class="badge">One quick step</span>
            <h2 id="profileCompletionNoticeTitle">Let us finish your profile first</h2>
            <p id="profileCompletionNoticeDescription">We will keep your retreat registration ready for you, then bring you straight back here to complete it.</p>
            <div class="profile-completion-notice-actions">
                <button class="button dark" type="button" data-profile-completion-action="complete">Complete my profile</button>
                <button class="button outline" type="button" data-profile-completion-action="dismiss">I will do this later</button>
            </div>
        </div>
    </div>
    <div id="migrationNotice" class="migration-notice" role="dialog" aria-modal="true" aria-labelledby="migrationNoticeTitle" aria-describedby="migrationNoticeDescription" hidden>
        <div class="migration-notice-card" role="document">
            <div class="migration-notice-icon" aria-hidden="true">
                <img src="/icons/goshen-icon-192.png" alt="">
            </div>
            <h2 id="migrationNoticeTitle">Welcome to the new Goshen Retreat portal</h2>
            <p id="migrationNoticeDescription" class="migration-notice-lead">
                Existing Goshen users have been moved to this new portal. For your security, all old passwords have been reset.
            </p>
            <ul class="migration-notice-list">
                <li>
                    <span class="migration-notice-check" aria-hidden="true">✓</span>
                    <span><strong>Use your registered email address</strong> to sign in, then use <strong>Forgot password</strong> to create a new password.</span>
                </li>
                <li>
                    <span class="migration-notice-check" aria-hidden="true">✓</span>
                    <span><strong>Pending or ongoing installment payments</strong> should now be completed through the new Wallet facility so you can obtain your ticket.</span>
                </li>
            </ul>
            <div class="migration-notice-actions">
                <a class="button dark" href="/app/forgot-password" data-auth-tab="forgot" data-migration-notice-action="reset">Reset my password</a>
                <button class="migration-notice-close" type="button" data-migration-notice-action="dismiss">I understand</button>
            </div>
        </div>
    </div>
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
        <symbol id="icon-logout" viewBox="0 0 24 24">
            <path d="M10 6H6.8A1.8 1.8 0 0 0 5 7.8v8.4A1.8 1.8 0 0 0 6.8 18H10M14 8l4 4-4 4M8 12h10" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        <symbol id="icon-copy" viewBox="0 0 24 24">
            <rect x="8" y="8" width="11" height="11" rx="2" fill="none" stroke="currentColor" stroke-linejoin="round"/>
            <path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
        <symbol id="icon-share" viewBox="0 0 24 24">
            <circle cx="18" cy="5" r="2.5" fill="none" stroke="currentColor"/>
            <circle cx="6" cy="12" r="2.5" fill="none" stroke="currentColor"/>
            <circle cx="18" cy="19" r="2.5" fill="none" stroke="currentColor"/>
            <path d="m8.2 10.8 7.6-4.6M8.2 13.2l7.6 4.6" fill="none" stroke="currentColor" stroke-linecap="round"/>
        </symbol>
    </svg>

    <section id="authShell" class="auth-shell">
        <div class="auth-card">
            <div class="auth-head">
                <img class="auth-logo" src="/icons/goshen-icon-192.png" alt="Goshen Retreat">
                <p class="eyebrow">MFM Triumphant Church</p>
                <h1>Welcome to Goshen Retreat</h1>
                <p>Sign in or create your account to manage registration, tickets, payments, and retreat updates.</p>
            </div>

            <div class="theme-mode-switch" role="radiogroup" aria-label="Theme preference">
                <button type="button" data-theme-mode="light" aria-label="Use light mode">Sun</button>
                <button type="button" data-theme-mode="dark" aria-label="Use dark mode">Moon</button>
                <button type="button" data-theme-mode="device" aria-label="Use device theme">Auto</button>
            </div>

            <div class="segmented" role="tablist" aria-label="Member access">
                <button type="button" data-auth-tab="login" class="active">Sign in</button>
                <button type="button" data-auth-tab="register">Register</button>
                <button type="button" data-auth-tab="verify">Verify</button>
            </div>

            <div id="authNotice" class="notice" hidden></div>

            <div id="googleAuthPanel" class="social-auth-panel" hidden>
                <div class="auth-divider"><span>Continue with</span></div>
                <div id="googleIdentityButton" class="google-button-shell"></div>
                <p class="social-auth-help">Use your Google account to sign in or create your Goshen portal profile.</p>
            </div>

            <form id="loginForm" class="form" autocomplete="on">
                <div class="field">
                    <label for="loginEmail">Email address</label>
                    <input id="loginEmail" class="input" name="email" type="email" autocomplete="email" required>
                </div>
                <div class="field">
                    <label for="loginPassword">Password</label>
                    <div class="password-control">
                        <input id="loginPassword" class="input" name="password" type="password" autocomplete="current-password" required>
                        <button class="password-reveal" type="button" data-password-toggle="loginPassword" aria-controls="loginPassword" aria-pressed="false">Show</button>
                    </div>
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
                        <div class="password-control">
                            <input id="registerPassword" class="input" name="password" type="password" autocomplete="new-password" minlength="8" required>
                            <button class="password-reveal" type="button" data-password-toggle="registerPassword" aria-controls="registerPassword" aria-pressed="false">Show</button>
                        </div>
                    </div>
                    <div class="field">
                        <label for="registerPasswordConfirm">Confirm password</label>
                        <div class="password-control">
                            <input id="registerPasswordConfirm" class="input" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
                            <button class="password-reveal" type="button" data-password-toggle="registerPasswordConfirm" aria-controls="registerPasswordConfirm" aria-pressed="false">Show</button>
                        </div>
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
                    <div class="password-control">
                        <input id="resetPassword" class="input" name="password" type="password" autocomplete="new-password" minlength="8" required>
                        <button class="password-reveal" type="button" data-password-toggle="resetPassword" aria-controls="resetPassword" aria-pressed="false">Show</button>
                    </div>
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
                <img class="brand-logo" src="/icons/goshen-icon-192.png" alt="">
                <div>
                    <strong>Goshen Retreat</strong>
                    <span>User portal</span>
                </div>
            </div>
            <div class="user-chip">
                <div class="user-chip-header">
                    <div id="sidebarUserAvatar" class="user-chip-avatar" aria-hidden="true"><span>M</span></div>
                    <div class="user-chip-main">
                        <strong id="sidebarUserName">Member</strong>
                        <span id="sidebarUserEmail">Signed in</span>
                    </div>
                    <button id="sidebarLogout" class="header-logout-button" type="button"><svg class="nav-icon" aria-hidden="true"><use href="#icon-logout"></use></svg>Sign out</button>
                </div>
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
        </aside>

        <header class="mobile-topbar">
            <button id="openDrawer" class="icon-button" type="button" aria-label="Open navigation menu"><span class="hamburger"></span></button>
            <div class="top-title">
                <strong id="mobileTitle">Home</strong>
                <span>Goshen Retreat</span>
            </div>
            <button class="icon-button" type="button" data-nav-page="profile" aria-label="Open profile"><span id="mobileProfileAvatar" class="user-chip-avatar small" aria-hidden="true"><span>M</span></span></button>
        </header>

        <div id="drawerBackdrop" class="drawer-backdrop" hidden>
            <div class="drawer" role="dialog" aria-modal="true" aria-label="Portal navigation">
                <div class="drawer-brand">
                    <img class="brand-logo" src="/icons/goshen-icon-192.png" alt="">
                    <div>
                        <strong>Goshen Retreat</strong>
                        <span>User portal</span>
                    </div>
                </div>
                <div class="user-chip">
                    <div class="user-chip-header">
                        <div id="drawerUserAvatar" class="user-chip-avatar" aria-hidden="true"><span>M</span></div>
                        <div class="user-chip-main">
                            <strong id="drawerUserName">Member</strong>
                            <span id="drawerUserEmail">Signed in</span>
                        </div>
                        <button id="drawerLogout" class="header-logout-button" type="button"><svg class="nav-icon" aria-hidden="true"><use href="#icon-logout"></use></svg>Sign out</button>
                    </div>
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
            </div>
        </div>

        <main class="portal-main" id="portalMain">
            <section class="page-view active" data-page-view="home">
                <div class="hero-card">
                    <p class="eyebrow">Member dashboard</p>
                    <h2 id="homeGreeting">Welcome to Goshen Retreat</h2>
                    <div id="homeTriumphantId" class="dashboard-triumphant-id">Triumphant ID pending</div>
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
        const migrationNoticeKey = 'goshen_migration_notice_2026_07_v1';
        const referralInvitationKey = 'goshen_referral_invitation';
        const profileCompletionNoticeKey = 'goshen_profile_completion_notice_2026_07_v1';
        const pendingRegistrationKey = 'goshen_pending_registration_v1';
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
        let profileEditMode = false;

        const authShell = document.getElementById('authShell');
        const portalShell = document.getElementById('portalShell');
        const authNotice = document.getElementById('authNotice');
        const toast = document.getElementById('toast');
        const profileCompletionNotice = document.getElementById('profileCompletionNotice');
        const migrationNotice = document.getElementById('migrationNotice');
        const drawerBackdrop = document.getElementById('drawerBackdrop');
        const googleAuthPanel = document.getElementById('googleAuthPanel');
        const googleIdentityButton = document.getElementById('googleIdentityButton');
        let googleIdentityLoading = null;
        let googleIdentityReady = false;

        function escapeHtml(value) {
            return `${value ?? ''}`.replace(/[&<>"']/g, (char) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
            }[char]));
        }

        function normalizeReferralCode(value) {
            return `${value || ''}`.replace(/[^A-Za-z0-9]/g, '').toUpperCase().slice(0, 32);
        }

        function referralInvitationCode() {
            const fromUrl = normalizeReferralCode(new URLSearchParams(window.location.search).get('ref'));
            if (fromUrl) {
                try { sessionStorage.setItem(referralInvitationKey, fromUrl); } catch {}
                return fromUrl;
            }

            try { return normalizeReferralCode(sessionStorage.getItem(referralInvitationKey)); } catch { return ''; }
        }

        function payloadFromForm(form) {
            return Object.fromEntries(new FormData(form).entries());
        }

        function profileUpdateFormData(form) {
            const formData = new FormData(form);
            const avatar = formData.get('avatar');
            if (typeof File !== 'undefined' && avatar instanceof File && !avatar.name) {
                formData.delete('avatar');
            }
            formData.set('email', currentUser?.email || formData.get('email') || '');
            formData.set('api_token', currentUser?.api_token || '');
            formData.set('fullname', [
                formData.get('first_name'),
                formData.get('middle_name'),
                formData.get('last_name'),
            ].filter(Boolean).join(' '));
            return formData;
        }

        function normalizeBirthdayMonthDayInput(value) {
            const digits = `${value || ''}`.replace(/\D/g, '').slice(0, 4);
            return digits.length > 2 ? `${digits.slice(0, 2)}-${digits.slice(2)}` : digits;
        }

        function birthdayMonthDayError(value) {
            if (!value) return '';
            const match = /^(\d{2})-(\d{2})$/.exec(value);
            if (!match) return 'Enter your birthday as MM-DD, for example 07-23.';

            const month = Number(match[1]);
            const day = Number(match[2]);
            const daysInMonth = [31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
            if (month < 1 || month > 12 || day < 1 || day > daysInMonth[month - 1]) {
                return 'Enter a real calendar date as MM-DD, for example 07-23.';
            }

            return '';
        }

        function validateBirthdayMonthDayInput(input, { format = false } = {}) {
            if (format) input.value = normalizeBirthdayMonthDayInput(input.value);
            input.setCustomValidity(birthdayMonthDayError(input.value));
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
                const error = new Error(messageFromErrorPayload(payload, 'Request failed. Please try again.'));
                error.status = response.status;
                error.payload = payload;
                throw error;
            }
            return payload;
        }

        async function apiPostFormData(url, formData) {
            const response = await fetch(url, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: formData,
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload.status === 'error') {
                const error = new Error(messageFromErrorPayload(payload, 'Request failed. Please try again.'));
                error.status = response.status;
                error.payload = payload;
                throw error;
            }
            return payload;
        }

        async function apiGet(url) {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload.status === 'error') {
                const error = new Error(messageFromErrorPayload(payload, 'Request failed. Please try again.'));
                error.status = response.status;
                error.payload = payload;
                throw error;
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

        function profileMissingFields() {
            return Array.isArray(currentUser?.profile_missing_fields)
                ? currentUser.profile_missing_fields.filter(Boolean)
                : [];
        }

        function profileNeedsCompletion() {
            return currentUser?.member_type !== 'visitor'
                && (currentUser?.profile_needs_update === true || profileMissingFields().length > 0);
        }

        function readPendingRegistration() {
            try {
                const draft = JSON.parse(sessionStorage.getItem(pendingRegistrationKey) || 'null');
                return draft?.eventId && draft?.values ? draft : null;
            } catch {
                return null;
            }
        }

        function pendingRegistrationFor(event) {
            const draft = readPendingRegistration();
            return `${draft?.eventId || ''}` === `${event?.public_id || ''}` ? draft : null;
        }

        function savePendingRegistration(form) {
            if (!form?.dataset?.eventId) return;
            const values = payloadFromForm(form);
            const attendees = collectAttendeeDrafts(form);
            try {
                sessionStorage.setItem(pendingRegistrationKey, JSON.stringify({
                    eventId: form.dataset.eventId,
                    values: {
                        ...values,
                        uk_privacy_consent: form.querySelector('[name="uk_privacy_consent"]')?.checked === true,
                    },
                    attendees,
                    savedAt: new Date().toISOString(),
                }));
            } catch {}
        }

        function clearPendingRegistration() {
            try { sessionStorage.removeItem(pendingRegistrationKey); } catch {}
        }

        function closeProfileCompletionNotice(markSeen = false) {
            if (!profileCompletionNotice) return;
            profileCompletionNotice.hidden = true;
            if (markSeen) {
                try { sessionStorage.setItem(profileCompletionNoticeKey, '1'); } catch {}
            }
        }

        function beginProfileCompletion(form = null) {
            savePendingRegistration(form || document.querySelector('.registration-form'));
            closeProfileCompletionNotice(true);
            showPage('profile');
            profileEditMode = true;
            renderProfile();
            notify('We saved your registration. Complete your profile and we will bring you right back.');
            window.setTimeout(() => document.querySelector('.profile-update-form [name="title"]')?.focus(), 80);
        }

        function maybeShowProfileCompletionNotice() {
            if (!profileCompletionNotice || activePage !== 'retreat' || !profileNeedsCompletion()) return;

            let seen = false;
            try { seen = sessionStorage.getItem(profileCompletionNoticeKey) === '1'; } catch {}
            if (seen) return;

            const missing = profileMissingFields();
            const description = missing.length
                ? `Before payment, please add your ${missing.join(', ')}. We will keep your retreat registration ready for you, then bring you straight back here.`
                : 'Before payment, please complete your member profile. We will keep your retreat registration ready for you, then bring you straight back here.';
            document.getElementById('profileCompletionNoticeDescription').textContent = description;
            profileCompletionNotice.hidden = false;
            window.setTimeout(() => profileCompletionNotice.querySelector('[data-profile-completion-action="complete"]')?.focus(), 40);
            window.setTimeout(() => {
                if (!profileCompletionNotice.hidden && activePage === 'retreat' && profileNeedsCompletion()) beginProfileCompletion();
            }, 1800);
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

        function markMigrationNoticeSeen() {
            try {
                localStorage.setItem(migrationNoticeKey, '1');
            } catch {}
        }

        function closeMigrationNotice() {
            if (!migrationNotice) return;
            migrationNotice.hidden = true;
            document.body.classList.remove('migration-notice-open');
            markMigrationNoticeSeen();
        }

        function initializeMigrationNotice() {
            if (!migrationNotice) return;

            let hasSeenNotice = false;
            try {
                hasSeenNotice = localStorage.getItem(migrationNoticeKey) === '1';
            } catch {}

            if (!hasSeenNotice) {
                migrationNotice.hidden = false;
                document.body.classList.add('migration-notice-open');
                window.setTimeout(() => {
                    migrationNotice.querySelector('[data-migration-notice-action="reset"]')?.focus();
                }, 40);
            }

            migrationNotice.querySelectorAll('[data-migration-notice-action]').forEach((element) => {
                element.addEventListener('click', () => closeMigrationNotice());
            });

            migrationNotice.addEventListener('click', (event) => {
                if (event.target === migrationNotice) closeMigrationNotice();
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !migrationNotice.hidden) closeMigrationNotice();
            });
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

            if (canShowGoogleAuth(tab)) {
                window.setTimeout(() => initializeGoogleLogin(), 0);
            }
        }

        function canShowGoogleAuth(tab = 'login') {
            return Boolean(googleLoginConfig?.enabled && googleLoginConfig?.clientId && ['login', 'register'].includes(tab));
        }

        function setGoogleBusy(busy) {
            googleIdentityButton?.classList.toggle('busy', busy);
            googleIdentityButton?.setAttribute('aria-busy', busy ? 'true' : 'false');
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
                if (payload.profile_needs_update) {
                    showPage('profile');
                    profileEditMode = true;
                    renderProfile();
                    notify('Please complete your profile details before continuing.', 'error');
                    return;
                }
                notify(`Welcome, ${payload.user?.name || 'member'}.`);
            } catch (error) {
                showAuthNotice(error.message, 'error');
            } finally {
                setGoogleBusy(false);
            }
        }

        async function initializeGoogleLogin() {
            if (!googleAuthPanel || !canShowGoogleAuth('login')) {
                return;
            }

            if (!googleIdentityButton || googleIdentityReady) {
                return;
            }

            googleAuthPanel.hidden = false;

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

            window.google.accounts.id.renderButton(googleIdentityButton, {
                theme: 'outline',
                size: 'large',
                shape: 'pill',
                text: 'continue_with',
                width: Math.min(320, googleAuthPanel.clientWidth || googleIdentityButton.clientWidth || 320),
            });

            googleIdentityReady = true;
        }

        function saveUser(user) {
            if (!user?.api_token) {
                throw new Error('The server did not return a login session.');
            }
            currentUser = { ...user, saved_at: new Date().toISOString() };
            profileEditMode = false;
            localStorage.setItem(storageKey, JSON.stringify(currentUser));
            showPortal();
        }

        function clearUser(message) {
            currentUser = null;
            profileEditMode = false;
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
            const avatar = profileAvatarUrl(currentUser);
            const avatarMarkup = avatar
                ? `<img src="${escapeHtml(avatar)}" alt="${escapeHtml(name)} profile photo" loading="lazy">`
                : `<span>${escapeHtml(profileInitials(currentUser, currentUser?.first_name, currentUser?.last_name))}</span>`;
            ['sidebarUserName', 'drawerUserName'].forEach((id) => {
                const element = document.getElementById(id);
                element.textContent = name;
                element.title = name;
            });
            ['sidebarUserEmail', 'drawerUserEmail'].forEach((id) => {
                const element = document.getElementById(id);
                element.textContent = email;
                element.title = email;
            });
            ['sidebarUserAvatar', 'drawerUserAvatar', 'mobileProfileAvatar'].forEach((id) => {
                const element = document.getElementById(id);
                if (element) element.innerHTML = avatarMarkup;
            });
            document.getElementById('homeGreeting').textContent = `Welcome, ${name.split(' ')[0] || 'Member'}`;
            document.getElementById('homeTriumphantId').textContent = currentUser?.triumphant_id
                ? `Triumphant ID: ${currentUser.triumphant_id}`
                : 'Triumphant ID pending';
        }

        function showPage(page, push = true) {
            const previousPage = activePage;
            activePage = pageTitles[page] ? page : 'home';
            document.querySelectorAll('[data-page-view]').forEach((view) => {
                view.classList.toggle('active', view.dataset.pageView === activePage);
            });
            document.querySelectorAll('[data-nav-page]').forEach((button) => {
                button.classList.toggle('active', button.dataset.navPage === activePage);
            });
            document.getElementById('mobileTitle').textContent = pageTitles[activePage];
            if (activePage === 'profile' && (previousPage !== 'profile' || push)) {
                profileEditMode = false;
                renderProfile();
            }
            if (activePage === 'retreat') {
                window.setTimeout(maybeShowProfileCompletionNotice, 0);
            }
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
                const payload = await apiPost('/api/member/me', { api_token: saved.api_token });
                if (!payload.user?.id) throw new Error('Missing member account');

                currentUser = { ...payload.user, api_token: saved.api_token, saved_at: new Date().toISOString() };
                localStorage.setItem(storageKey, JSON.stringify(currentUser));
                showPortal();
            } catch (error) {
                if ([401, 403].includes(Number(error?.status))) {
                    clearUser('Your saved session expired. Please sign in again.');
                    return;
                }
                currentUser = null;
                localStorage.removeItem(storageKey);
                portalShell.hidden = true;
                showAuth('login', false);
                notify('We could not verify your saved session. Please sign in again.', 'error');
            }
        }

        function eventImage(event) {
            return event?.feature_image_url || event?.image_url || event?.cover_image_url || event?.media_url || '';
        }

        function eventDescription(event) {
            return `${event?.description || ''}`.trim();
        }

        function eventStartValue(event) {
            return event?.start_date || event?.startDate || event?.starts_at || event?.start_at || event?.sales_start_at || null;
        }

        function eventEndValue(event) {
            return event?.end_date || event?.endDate || event?.ends_at || event?.end_at || null;
        }

        function eventDate(event) {
            const start = eventStartValue(event);
            const end = eventEndValue(event);
            if (!start) return 'Dates will be announced';
            return end ? `${formatDate(start)} - ${formatDate(end)}` : formatDate(start);
        }

        function eventVenue(event) {
            return [event?.venue_name, event?.venue_address].filter(Boolean).join(' - ') || 'Venue details will be shared by the church.';
        }

        function eventMapsUrl(event) {
            const venue = [event?.venue_name, event?.venue_address].filter(Boolean).join(' ');
            return venue ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(venue)}` : '';
        }

        function eventInquiryPhone(event) {
            const raw = `${event?.inquiry_phone || event?.inquiryPhone || event?.inquiry_phone_number || ''}`.trim();
            if (!raw) return '';
            const hasLeadingPlus = raw.startsWith('+');
            const digits = raw.replace(/\D+/g, '');
            return digits ? `${hasLeadingPlus ? '+' : ''}${digits}` : '';
        }

        function eventCountdownTarget(event) {
            const value = eventStartValue(event);
            if (!value) return null;
            const raw = `${value}`;
            const date = /^\d{4}-\d{2}-\d{2}$/.test(raw) ? new Date(`${raw}T00:00:00`) : new Date(raw);
            return Number.isNaN(date.getTime()) ? null : date;
        }

        function eventCountdownMarkup(event) {
            const target = eventCountdownTarget(event);
            if (!target) {
                return `
                    <section class="card event-countdown">
                        <h3>Retreat countdown</h3>
                        <p class="muted">Dates will be announced soon.</p>
                    </section>
                `;
            }

            let remaining = target.getTime() - Date.now();
            const active = remaining >= 0;
            remaining = Math.max(0, remaining);
            const minutes = Math.floor(remaining / 60000);
            const days = Math.floor(minutes / 1440);
            const hours = Math.floor((minutes % 1440) / 60);
            const mins = minutes % 60;

            return `
                <section class="card event-countdown">
                    <h3>${active ? 'Retreat starts in' : 'Retreat date reached'}</h3>
                    <div class="stats-grid">
                        <div class="stat"><strong>${escapeHtml(days)}</strong><span>Days</span></div>
                        <div class="stat"><strong>${escapeHtml(String(hours).padStart(2, '0'))}</strong><span>Hours</span></div>
                        <div class="stat"><strong>${escapeHtml(String(mins).padStart(2, '0'))}</strong><span>Mins</span></div>
                    </div>
                </section>
            `;
        }

        function renderTicketTypeCards(tickets) {
            if (!tickets.length) return '<div class="empty">Ticket types are not available yet for this retreat edition.</div>';

            return `
                <section class="card">
                    <h3>Ticket types</h3>
                    <div class="record-list">
                        ${tickets.map((ticket) => `
                            <article class="record">
                                <div class="record-top">
                                    <div class="record-title">
                                        <strong>${escapeHtml(ticket.name || 'Ticket')}</strong>
                                        <span class="item-meta">Min ${escapeHtml(ticketMinimum(ticket))} · Max ${escapeHtml(ticketMaximum(ticket))}</span>
                                    </div>
                                    <strong>${escapeHtml(formatMoney(ticket.price, ticket.currency))}</strong>
                                </div>
                            </article>
                        `).join('')}
                    </div>
                </section>
            `;
        }

        function renderPastVideos(event) {
            const videos = Array.isArray(event?.past_videos)
                ? event.past_videos
                : (Array.isArray(event?.pastVideos) ? event.pastVideos : []);
            const validVideos = videos
                .map((video) => ({ ...video, embedUrl: youtubeEmbedUrl(video) }))
                .filter((video) => video.embedUrl);
            if (!validVideos.length) return '';

            return `
                <section class="card">
                    <h3>Past Goshen videos</h3>
                    <div class="past-video-slider" aria-label="Past Goshen videos slider">
                        ${validVideos.map((video) => {
                            const title = video.title || 'Goshen Retreat video';
                            return `
                                <article class="past-video-card">
                                    <iframe
                                        src="${escapeHtml(video.embedUrl)}"
                                        title="${escapeHtml(title)}"
                                        loading="lazy"
                                        referrerpolicy="strict-origin-when-cross-origin"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                        allowfullscreen></iframe>
                                    <strong>${escapeHtml(title)}</strong>
                                    ${video.description ? `<span class="item-meta">${escapeHtml(video.description)}</span>` : ''}
                                </article>
                            `;
                        }).join('')}
                    </div>
                </section>
            `;
        }

        function youtubeEmbedUrl(video) {
            const id = youtubeVideoId(
                video?.youtube_video_id
                || video?.youtubeVideoId
                || video?.youtube_id
                || video?.youtubeId
                || video?.video_id
                || video?.videoId
                || video?.youtube_url
                || video?.youtubeUrl
                || video?.url
                || video?.video_url
                || video?.videoUrl
                || ''
            );

            return id ? `https://www.youtube-nocookie.com/embed/${encodeURIComponent(id)}?rel=0&modestbranding=1&playsinline=1` : '';
        }

        function youtubeVideoId(value) {
            const raw = `${value || ''}`.trim();
            if (!raw) return '';
            if (/^[A-Za-z0-9_-]{11}$/.test(raw)) return raw;

            try {
                const normalized = raw.startsWith('//') ? `https:${raw}` : raw;
                const url = new URL(normalized);
                const host = url.hostname.replace(/^www\./, '').toLowerCase();
                if (host === 'youtu.be') {
                    return youtubeVideoId(url.pathname.split('/').filter(Boolean)[0] || '');
                }
                if (host.endsWith('youtube.com') || host.endsWith('youtube-nocookie.com')) {
                    const fromQuery = url.searchParams.get('v');
                    if (fromQuery) return youtubeVideoId(fromQuery);

                    const parts = url.pathname.split('/').filter(Boolean);
                    const marker = parts.findIndex((part) => ['embed', 'shorts', 'live', 'v'].includes(part));
                    if (marker >= 0 && parts[marker + 1]) return youtubeVideoId(parts[marker + 1]);
                }
            } catch (error) {
                const match = raw.match(/(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:watch\?.*?v=|embed\/|shorts\/|live\/|v\/))([A-Za-z0-9_-]{11})/i);
                if (match?.[1]) return match[1];
            }

            return '';
        }

        async function loadEvents() {
            try {
                const payload = await apiGet('/api/goshen-retreat/events');
                eventsCache = Array.isArray(payload.data) ? payload.data : [];
                renderEvents();
                renderHome();
                renderSupport();
                if (currentUser && activePage === 'profile') renderProfile();
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
                maybeShowProfileCompletionNotice();
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
            const description = eventDescription(event);
            const schedules = Array.isArray(event.schedules) ? event.schedules : [];
            const tickets = Array.isArray(event.ticket_types) ? event.ticket_types : [];
            const registrationOpen = Boolean(event.registration?.open ?? event.registration_open ?? true);
            const mapsUrl = eventMapsUrl(event);
            const inquiryPhone = eventInquiryPhone(event);
            return `
                <article class="card">
                    <div class="event-media">
                        ${image ? `<img src="${escapeHtml(image)}" alt="">` : ''}
                        <span class="event-media-pill">Goshen Retreat</span>
                        <span class="event-media-date">${escapeHtml(eventDate(event))}</span>
                    </div>
                    <div class="record-top">
                        <div class="record-title">
                            <span class="badge ${registrationOpen ? 'ok' : 'danger'}">${registrationOpen ? 'Registration open' : 'Registration closed'}</span>
                            <strong>${escapeHtml(event.name || event.title || 'Goshen Retreat')}</strong>
                            <span class="item-meta">${escapeHtml(eventDate(event))}</span>
                        </div>
                    </div>
                    ${description ? `<p class="event-description">${escapeHtml(description)}</p>` : ''}
                    <div class="detail-list">
                        <div class="detail-row"><strong>Date</strong><span>${escapeHtml(eventDate(event))}</span></div>
                        <div class="detail-row"><strong>Venue</strong><span>${escapeHtml(eventVenue(event))}</span>${mapsUrl ? `<a class="inline-action" href="${escapeHtml(mapsUrl)}" target="_blank" rel="noopener">Open in maps</a>` : ''}</div>
                        ${inquiryPhone ? `<div class="detail-row"><strong>Retreat inquiry</strong><span>${escapeHtml(inquiryPhone)}</span><a class="inline-action" href="tel:${escapeHtml(inquiryPhone)}">Call inquiry line</a></div>` : ''}
                        ${schedules.length ? `<div class="detail-row"><strong>Schedule</strong>${schedules.slice(0, 4).map((schedule) => `<span>${escapeHtml(schedule.title || 'Session')} - ${escapeHtml(formatDateTime(schedule.starts_at))}</span>`).join('')}</div>` : ''}
                    </div>
                    ${eventCountdownMarkup(event)}
                    ${renderPastVideos(event)}
                    ${renderTicketTypeCards(tickets)}
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

        function normalizedTicketLimit(value, fallback) {
            const parsed = Number(value);
            return Number.isFinite(parsed) && parsed > 0 ? Math.floor(parsed) : fallback;
        }

        function eventTicketById(event, ticketId) {
            const tickets = Array.isArray(event?.ticket_types) ? event.ticket_types : [];
            return tickets.find((ticket) => `${ticket.public_id || ticket.id || ''}` === `${ticketId || ''}`)
                || tickets[0]
                || null;
        }

        function ticketMinimum(ticket) {
            return Math.max(1, normalizedTicketLimit(ticket?.min_per_booking, 1));
        }

        function ticketMaximum(ticket) {
            const max = normalizedTicketLimit(ticket?.max_per_booking, 10);
            return Math.max(ticketMinimum(ticket), Math.min(20, max));
        }

        function ticketDefaultQuantity(ticket) {
            return clampTicketQuantity(ticketMinimum(ticket), ticket);
        }

        function clampTicketQuantity(value, ticket) {
            const min = ticketMinimum(ticket);
            const max = ticketMaximum(ticket);
            const parsed = normalizedTicketLimit(value, min);
            return Math.max(min, Math.min(max, parsed));
        }

        function ticketQuantityHint(ticket) {
            const min = ticketMinimum(ticket);
            const max = ticketMaximum(ticket);
            return min === max
                ? `This ticket requires ${min} attendee${min === 1 ? '' : 's'}.`
                : `This ticket allows ${min} to ${max} attendee${max === 1 ? '' : 's'} per booking.`;
        }

        function ticketShowsQuantitySelector(ticket) {
            const explicitMin = normalizedTicketLimit(ticket?.min_per_booking, 1);
            const explicitMax = Number(ticket?.max_per_booking);
            const hasExplicitMultiMax = Number.isFinite(explicitMax) && explicitMax > 1;
            const ticketName = `${ticket?.name || ''}`.toLowerCase();
            return explicitMin > 1 || hasExplicitMultiMax || ticketName.includes('family');
        }

        function renderQuantityStepper(ticket, quantity, labelId = 'attendeeQuantityLabel') {
            const min = ticketMinimum(ticket);
            const max = ticketMaximum(ticket);
            const current = clampTicketQuantity(quantity, ticket);
            return `
                <input class="attendee-quantity" name="quantity" type="hidden" value="${escapeHtml(current)}">
                <div class="quantity-stepper" role="group" aria-labelledby="${escapeHtml(labelId)}">
                    <button class="quantity-stepper-button attendee-quantity-decrease" type="button" aria-label="Reduce attendees" ${current <= min ? 'disabled' : ''}>−</button>
                    <span class="attendee-quantity-value" aria-live="polite">${escapeHtml(current)}</span>
                    <button class="quantity-stepper-button add attendee-quantity-increase" type="button" aria-label="Add attendee" ${current >= max ? 'disabled' : ''}>+</button>
                </div>
            `;
        }

        function updateQuantityStepper(form, ticket, quantity) {
            const min = ticketMinimum(ticket);
            const max = ticketMaximum(ticket);
            const value = clampTicketQuantity(quantity, ticket);
            const display = form.querySelector('.attendee-quantity-value');
            if (display) display.textContent = `${value}`;
            const decrease = form.querySelector('.attendee-quantity-decrease');
            if (decrease) decrease.disabled = value <= min;
            const increase = form.querySelector('.attendee-quantity-increase');
            if (increase) increase.disabled = value >= max;
        }

        function renderRegistrationForm(event) {
            const tickets = Array.isArray(event.ticket_types) ? event.ticket_types : [];
            if (!tickets.length) {
                return '<div class="empty">Ticket types are not available yet for this retreat edition.</div>';
            }
            const draft = pendingRegistrationFor(event);
            const values = draft?.values || {};
            const selectedTicket = eventTicketById(event, values.ticket_type_id) || tickets[0] || {};
            const ticketOptions = tickets.map((ticket) => `<option value="${escapeHtml(ticket.public_id)}" ${`${ticket.public_id}` === `${selectedTicket.public_id}` ? 'selected' : ''}>${escapeHtml(ticket.name || 'Ticket')} - ${escapeHtml(formatMoney(ticket.price, ticket.currency))} · ${escapeHtml(ticketQuantityHint(ticket))}</option>`).join('');
            const initialQuantity = clampTicketQuantity(values.quantity || ticketDefaultQuantity(selectedTicket), selectedTicket);
            const quantityLabelId = `attendeeQuantityLabel-${event.public_id || 'current'}`;
            const showQuantitySelector = ticketShowsQuantitySelector(selectedTicket);
            const invitationCode = values.referral_code || referralInvitationCode();
            const paymentMode = ['outright', 'wallet', 'voucher'].includes(values.payment_mode) ? values.payment_mode : 'outright';
            return `
                <form class="form registration-form" data-event-id="${escapeHtml(event.public_id)}">
                    ${profileNeedsCompletion() ? renderRegistrationProfileNotice() : ''}
                    <div class="form-grid">
                        <div class="field">
                            <label>Ticket type</label>
                            <select class="input" name="ticket_type_id" required>${ticketOptions}</select>
                        </div>
                        <div class="field attendee-quantity-field" ${showQuantitySelector ? '' : 'hidden'}>
                            <label id="${escapeHtml(quantityLabelId)}">Attendees</label>
                            ${renderQuantityStepper(selectedTicket, initialQuantity, quantityLabelId)}
                            <span class="hint attendee-quantity-hint">${escapeHtml(ticketQuantityHint(selectedTicket))}</span>
                        </div>
                        <div class="field">
                            <label>Payment method</label>
                            <select class="input payment-mode" name="payment_mode" required>
                                <option value="outright" ${paymentMode === 'outright' ? 'selected' : ''}>Card payment</option>
                                <option value="wallet" ${paymentMode === 'wallet' ? 'selected' : ''}>Wallet funds</option>
                                <option value="voucher" ${paymentMode === 'voucher' ? 'selected' : ''}>Voucher</option>
                            </select>
                        </div>
                        <div class="field voucher-field" ${paymentMode === 'voucher' ? '' : 'hidden'}>
                            <label>Voucher code</label>
                            <input class="input" name="voucher_code" autocomplete="off" value="${escapeHtml(values.voucher_code || '')}" ${paymentMode === 'voucher' ? 'required' : ''}>
                        </div>
                        <div class="field">
                            <label>Referral code (optional)</label>
                            <input class="input" name="referral_code" maxlength="32" autocomplete="off" value="${escapeHtml(invitationCode)}">
                        </div>
                    </div>
                    <div class="attendee-fields">${renderAttendeeFields(initialQuantity, event, draft?.attendees || [], selectedTicket)}</div>
                    <label class="choice">
                        <input type="checkbox" name="uk_privacy_consent" value="1" required ${values.uk_privacy_consent ? 'checked' : ''}>
                        <span>I agree that MFM Triumphant Church may process my registration, attendee, payment, ticket, and travel-support information for Goshen Retreat administration in line with UK data protection requirements.</span>
                    </label>
                    <button class="button" type="submit">Register for this retreat</button>
                </form>
            `;
        }

        function renderRegistrationProfileNotice() {
            const missing = profileMissingFields();
            const detail = missing.length
                ? `Please add your ${escapeHtml(missing.join(', '))} before payment.`
                : 'Please complete your member profile before payment.';
            return `
                <aside class="registration-profile-notice" aria-label="Profile completion required">
                    <strong>One quick step before payment</strong>
                    <p>${detail} We will keep this registration ready and bring you back as soon as you save.</p>
                    <div class="inline-actions">
                        <button class="button small dark complete-profile-for-registration" type="button">Complete my profile</button>
                    </div>
                </aside>
            `;
        }

        function splitName(name) {
            const parts = `${name || ''}`.trim().split(/\s+/).filter(Boolean);
            return { first: parts[0] || '', last: parts.slice(1).join(' ') };
        }

        function renderAttendeeFields(quantity, event, existingAttendees = [], ticket = null) {
            const total = Math.max(1, Math.min(20, Number(quantity || 1)));
            const fields = registrationFieldsFor(event);
            const currency = ticket?.currency || event?.currency || 'GBP';
            const name = splitName(currentUser?.name || '');
            const cards = [];
            for (let index = 0; index < total; index += 1) {
                const existing = existingAttendees[index] || {};
                cards.push(`
                    <div class="attendee-card" data-attendee-index="${index}">
                        <strong>Attendee ${index + 1}${index === 0 ? ' - account holder' : ''}</strong>
                        <div class="form-grid">
                            <div class="field"><label>First name</label><input class="input attendee-first-name" value="${escapeHtml(existing.first_name ?? (index === 0 ? name.first : ''))}" required></div>
                            <div class="field"><label>Last name</label><input class="input attendee-last-name" value="${escapeHtml(existing.last_name ?? (index === 0 ? name.last : ''))}"></div>
                            <div class="field"><label>Email</label><input class="input attendee-email" type="email" value="${escapeHtml(existing.email ?? (index === 0 ? currentUser?.email || '' : ''))}"></div>
                            <div class="field"><label>Phone</label><input class="input attendee-phone" type="tel" value="${escapeHtml(existing.phone ?? (index === 0 ? currentUser?.phone || '' : ''))}"></div>
                            ${fields.map((field) => renderRegistrationField(field, index, existing.custom_fields || existing, currency)).join('')}
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

        function optionFeeLabel(option, currency = 'GBP') {
            const amount = Number(option?.fee_amount ?? option?.fee ?? 0);
            if (!Number.isFinite(amount) || amount <= 0) return '';
            return ` + ${formatMoney(amount, option?.currency || option?.fee_currency || currency)}`;
        }

        function attendeeFieldIdentity(key) {
            const normalized = `${key || ''}`
                .trim()
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/_+/g, '_')
                .replace(/^_|_$/g, '');
            const aliases = {
                age: 'age_group',
                agegroup: 'age_group',
                free_bus: 'free_church_bus_interest',
                freechurchbus: 'free_church_bus_interest',
                free_church_bus: 'free_church_bus_interest',
                free_church_bus_consent: 'free_church_bus_interest',
                church_bus: 'free_church_bus_interest',
                bus_interest: 'free_church_bus_interest',
                volunteer: 'volunteer_department',
                volunteer_choice: 'volunteer_department',
                volunteer_department_choice: 'volunteer_department',
            };
            return aliases[normalized] || normalized;
        }

        function fieldValueForExisting(existingFields, key) {
            const canonicalKey = attendeeFieldIdentity(key);
            return `${(existingFields || {})[key] ?? (existingFields || {})[canonicalKey] ?? ''}`;
        }

        function renderRegistrationField(field, attendeeIndex, existingFields = {}, currency = 'GBP') {
            const key = `${field.key || ''}`.trim();
            const label = escapeHtml(field.label || key);
            const type = `${field.type || 'text'}`.toLowerCase();
            const required = field.is_required ? 'required' : '';
            const options = Array.isArray(field.options) ? field.options : [];
            const currentValue = fieldValueForExisting(existingFields, key);

            if (['select', 'single_select'].includes(type)) {
                return `<div class="field"><label>${label}</label><select class="input attendee-dynamic-control" data-field-key="${escapeHtml(key)}" ${required}>${options.map((option) => {
                    const value = `${optionValue(option)}`;
                    const selected = value === currentValue ? 'selected' : '';
                    return `<option value="${escapeHtml(value)}" ${selected}>${escapeHtml(`${optionLabel(option)}${optionFeeLabel(option, currency)}`)}</option>`;
                }).join('')}</select></div>`;
            }

            if (type === 'textarea') {
                return `<div class="field"><label>${label}</label><textarea class="input attendee-dynamic-control" data-field-key="${escapeHtml(key)}" ${required}>${escapeHtml(currentValue)}</textarea></div>`;
            }

            if (['image_select', 'color_select'].includes(type)) {
                const radioName = `attendee_${attendeeIndex}_${key}`;
                return `<div class="field"><label>${label}</label><div class="choice-grid">${options.filter((option) => `${optionValue(option)}` !== '').map((option) => {
                    const value = `${optionValue(option)}`;
                    const image = option?.image_url ? `<img src="${escapeHtml(option.image_url)}" alt="">` : '';
                    const swatch = option?.color_hex ? `<span class="swatch" style="background:${escapeHtml(option.color_hex)}"></span>` : '';
                    const checked = value === currentValue ? 'checked' : '';
                    return `<label class="choice"><input class="attendee-dynamic-control" data-field-key="${escapeHtml(key)}" type="radio" name="${escapeHtml(radioName)}" value="${escapeHtml(value)}" ${required} ${checked}>${image || swatch}<span>${escapeHtml(`${optionLabel(option)}${optionFeeLabel(option, currency)}`)}</span></label>`;
                }).join('')}</div></div>`;
            }

            return `<div class="field"><label>${label}</label><input class="input attendee-dynamic-control" data-field-key="${escapeHtml(key)}" value="${escapeHtml(currentValue)}" ${required}></div>`;
        }

        function collectDynamicFields(card) {
            const fields = {};
            card.querySelectorAll('.attendee-dynamic-control[data-field-key]').forEach((input) => {
                const key = input.dataset.fieldKey;
                if (!key) return;
                const canonicalKey = attendeeFieldIdentity(key);
                if (input.type === 'radio') {
                    if (input.checked) {
                        fields[key] = input.value || '';
                        if (canonicalKey !== key) fields[canonicalKey] = input.value || '';
                    }
                    return;
                }
                fields[key] = input.value || '';
                if (canonicalKey !== key) fields[canonicalKey] = input.value || '';
            });
            return fields;
        }

        function collectAttendeeDrafts(form) {
            return [...form.querySelectorAll('.attendee-card')].map((card) => {
                const dynamic = collectDynamicFields(card);
                return {
                    first_name: card.querySelector('.attendee-first-name')?.value || '',
                    last_name: card.querySelector('.attendee-last-name')?.value || '',
                    email: card.querySelector('.attendee-email')?.value || '',
                    phone: card.querySelector('.attendee-phone')?.value || '',
                    custom_fields: dynamic,
                    ...dynamic,
                };
            });
        }

        function syncRegistrationQuantity(form, eventModel, preferredQuantity = null) {
            const select = form.querySelector('[name="ticket_type_id"]');
            const quantityInput = form.querySelector('.attendee-quantity');
            if (!select || !quantityInput) return;

            const ticket = eventTicketById(eventModel, select.value);
            const nextQuantity = clampTicketQuantity(preferredQuantity ?? quantityInput.value, ticket);
            quantityInput.value = `${nextQuantity}`;
            updateQuantityStepper(form, ticket, nextQuantity);

            const quantityField = form.querySelector('.attendee-quantity-field');
            if (quantityField) quantityField.hidden = !ticketShowsQuantitySelector(ticket);

            const hint = form.querySelector('.attendee-quantity-hint');
            if (hint) hint.textContent = ticketQuantityHint(ticket);

            form.querySelector('.attendee-fields').innerHTML = renderAttendeeFields(
                nextQuantity,
                eventModel,
                collectAttendeeDrafts(form),
                ticket,
            );
        }

        async function submitRegistration(form) {
            const eventModel = eventsCache.find((item) => `${item.public_id}` === `${form.dataset.eventId}`);
            if (profileNeedsCompletion()) {
                beginProfileCompletion(form);
                return;
            }
            syncRegistrationQuantity(form, eventModel);
            if (!form.reportValidity()) return;
            const values = payloadFromForm(form);
            const attendees = collectAttendeeDrafts(form).map((attendee) => ({
                ...attendee,
                email: attendee.email || currentUser.email || '',
                phone: attendee.phone || currentUser.phone || '',
            }));
            const payload = authPayload({
                event_id: form.dataset.eventId,
                ticket_type_id: values.ticket_type_id,
                payment_mode: values.payment_mode || 'outright',
                voucher_code: values.voucher_code || '',
                referral_code: values.referral_code || '',
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
                clearPendingRegistration();
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
                if (error?.payload?.missing_profile_fields || /complete the member profile/i.test(error.message || '')) {
                    beginProfileCompletion(form);
                    return;
                }
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
            document.getElementById('homeStats').innerHTML = `
                <div class="stat"><span>Registrations</span><strong>${registrations.length}</strong></div>
                <div class="stat"><span>Tickets</span><strong>${tickets.length}</strong></div>
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
                        <h3>Top up with voucher</h3>
                        <p class="muted">Redeem a wallet funding voucher issued by Goshen admin.</p>
                        <form class="form wallet-voucher-topup-form">
                            <div class="field">
                                <label>Voucher code</label>
                                <input class="input" name="code" autocomplete="one-time-code" minlength="6" maxlength="80" placeholder="Enter voucher code" required>
                            </div>
                            <p class="muted">Wallet funding vouchers add their value to your wallet balance immediately after successful redemption.</p>
                            <button class="button outline" type="submit">Redeem voucher to wallet</button>
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
            const paidAmount = Number(ticket.amount_paid ?? ticket.paid_amount ?? 0);
            const paidLabel = ticket.amount_paid_label || (paidAmount > 0 ? formatMoney(paidAmount, ticket.currency || 'GBP') : 'Not recorded');
            return `
                <article class="record ticket-card">
                    <div class="ticket-summary">
                        ${statusBadge(ticket.status)}
                        <strong>${escapeHtml(ticketNumber)}</strong>
                        <span class="item-meta">${escapeHtml(ticket.attendee_name || 'Attendee')} - ${escapeHtml(ticket.ticket_type || 'Goshen Retreat')}</span>
                        <span class="item-meta">Amount paid: ${escapeHtml(paidLabel)}</span>
                    </div>
                    <div class="ticket-qr-stage">
                        <div class="qr-holder" data-qr-url="${escapeHtml(urls.qr || '')}">QR</div>
                        <p>Scan this QR code at check-in</p>
                    </div>
                    <div class="ticket-details">
                        <div class="ticket-detail"><span>Ticket holder</span><strong>${escapeHtml(ticket.attendee_name || currentUser?.name || 'Attendee')}</strong></div>
                        <div class="ticket-detail"><span>Ticket type</span><strong>${escapeHtml(ticket.ticket_type || 'Goshen Retreat')}</strong></div>
                        <div class="ticket-detail"><span>Issued for</span><strong>${escapeHtml(ticket.event?.name || ticket.event_name || 'Goshen Retreat')}</strong></div>
                        <div class="ticket-detail"><span>Amount paid</span><strong>${escapeHtml(paidLabel)}</strong></div>
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

        function displayProfileValue(value, fallback = 'Not provided') {
            const text = `${value ?? ''}`.trim();
            return text || fallback;
        }

        function titleCaseLabel(value) {
            return displayProfileValue(value)
                .replace(/_/g, ' ')
                .replace(/\b\w/g, (letter) => letter.toUpperCase());
        }

        function profileDetailRow(label, value, fallback = 'Not provided') {
            return `<div class="detail-row"><strong>${escapeHtml(label)}</strong><span>${escapeHtml(displayProfileValue(value, fallback))}</span></div>`;
        }

        function profileDetailCard(label, value, fallback = 'Not provided', wide = false) {
            return `<div class="profile-detail-card ${wide ? 'wide' : ''}"><strong>${escapeHtml(label)}</strong><span>${escapeHtml(displayProfileValue(value, fallback))}</span></div>`;
        }

        function profileAvatarUrl(user) {
            return `${user?.avatar || user?.profile_photo || user?.profile_image || ''}`.trim();
        }

        function profileInitials(user, firstName = '', lastName = '') {
            const source = [
                firstName || user?.first_name,
                lastName || user?.last_name,
            ].filter(Boolean);
            const initials = (source.length ? source : `${user?.name || user?.email || 'Member'}`.split(/\s+/))
                .filter(Boolean)
                .slice(0, 2)
                .map((part) => `${part}`.charAt(0).toUpperCase())
                .join('');
            return initials || 'M';
        }

        function profileAvatarMarkup(user, fullName, firstName = '', lastName = '') {
            const avatar = profileAvatarUrl(user);
            return `
                <div class="profile-avatar-frame" aria-label="Profile photo">
                    ${avatar
                        ? `<img class="profile-avatar-image" src="${escapeHtml(avatar)}" alt="${escapeHtml(fullName)} profile photo" loading="lazy">`
                        : `<div class="profile-avatar-fallback" aria-hidden="true">${escapeHtml(profileInitials(user, firstName, lastName))}</div>`}
                </div>
            `;
        }

        function profilePhotoNotice(user) {
            if (profileAvatarUrl(user)) return '';
            return `
                <div class="profile-photo-notice">
                    <span aria-hidden="true">i</span>
                    <div>
                        Add a profile photo whenever you are ready. It is optional, but it helps the church team identify your profile more easily across the web app and mobile app.
                    </div>
                </div>
            `;
        }

        function referralValue(referral, keys, fallback = '0') {
            for (const key of keys) {
                const value = referral?.[key];
                if (value !== undefined && value !== null && `${value}`.trim() !== '') return value;
            }
            return fallback;
        }

        function referralShareLink(code) {
            return `${window.location.origin}/invite/${encodeURIComponent(normalizeReferralCode(code))}`;
        }

        function referralShareDetails(code) {
            const event = eventsCache[0] || {};
            const title = event.name || 'Goshen Retreat 2026';
            const date = eventDate(event);
            const venue = eventVenue(event);

            return {
                title: `Join me at ${title}`,
                text: `I'm attending ${title}. Come and seek God with me at ${venue} on ${date}. It will be a beautiful time of prayer, worship, and renewal. Use my referral code ${code} when you register.`,
                url: referralShareLink(code),
                image: eventImage(event) || '/icons/goshen-icon-512.png',
                date,
                venue,
            };
        }

        async function copyReferralText(value, successMessage) {
            try {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(value);
                } else {
                    const input = document.createElement('textarea');
                    input.value = value;
                    input.style.position = 'fixed';
                    input.style.opacity = '0';
                    document.body.append(input);
                    input.select();
                    document.execCommand('copy');
                    input.remove();
                }
                notify(successMessage);
            } catch {
                notify('We could not copy that just now. Please try again.', 'error');
            }
        }

        function renderProfileReferralCard() {
            const referral = memberData.referral || currentUser?.referral || null;
            if (!referral) {
                return `
                    <article class="card">
                        <h3>Referral rewards</h3>
                        <p class="muted">Your Goshen referral code will appear here once your retreat profile is loaded.</p>
                    </article>
                `;
            }

            const code = referralValue(referral, ['referral_code', 'code'], 'Not available');
            const total = referralValue(referral, ['total_points', 'total_earned'], 0);
            const pending = referralValue(referral, ['pending_points', 'pending_validation'], 0);
            const available = referralValue(referral, ['available_points', 'validated_points'], 0);
            const converted = referralValue(referral, ['converted_points'], 0);
            const walletAmount = referralValue(referral, ['wallet_amount', 'wallet_amount_available'], 0);
            const currency = referralValue(referral, ['currency'], walletData?.currency || 'GBP');
            const share = referralShareDetails(code);

            return `
                <article class="card">
                    <div class="profile-card-head">
                        <div>
                            <h3>Referral rewards</h3>
                            <p class="muted">Share your Goshen code and track referral points linked to your member account.</p>
                        </div>
                    </div>
                    <div class="profile-triumphant-id" aria-label="Referral code">
                        <span>Your referral code</span>
                        <strong>${escapeHtml(code)}</strong>
                    </div>
                    <div class="referral-share-card">
                        <img class="referral-share-media" src="${escapeHtml(share.image)}" alt="${escapeHtml(eventsCache[0]?.name || 'Goshen Retreat')} feature image" loading="lazy">
                        <div class="referral-share-copy">
                            <strong>${escapeHtml(share.title)}</strong>
                            <span>${escapeHtml(share.date)} · ${escapeHtml(share.venue)}</span>
                        </div>
                        <span class="referral-share-link">${escapeHtml(share.url)}</span>
                        <div class="referral-share-actions">
                            <button class="button small outline referral-copy-code" type="button" data-referral-code="${escapeHtml(code)}"><svg class="nav-icon" aria-hidden="true"><use href="#icon-copy"></use></svg>Copy code</button>
                            <button class="button small outline referral-copy-link" type="button" data-referral-code="${escapeHtml(code)}"><svg class="nav-icon" aria-hidden="true"><use href="#icon-copy"></use></svg>Copy link</button>
                            <button class="button small outline referral-share-network" type="button" data-referral-code="${escapeHtml(code)}" data-share-network="whatsapp"><svg class="nav-icon" aria-hidden="true"><use href="#icon-share"></use></svg>WhatsApp</button>
                            <button class="button small outline referral-share-network" type="button" data-referral-code="${escapeHtml(code)}" data-share-network="facebook"><svg class="nav-icon" aria-hidden="true"><use href="#icon-share"></use></svg>Facebook</button>
                            <button class="button small referral-share-button" type="button" data-referral-code="${escapeHtml(code)}"><svg class="nav-icon" aria-hidden="true"><use href="#icon-share"></use></svg>Share invite</button>
                        </div>
                    </div>
                    <div class="stats-grid">
                        <div class="stat"><span>Total</span><strong>${escapeHtml(total)}</strong></div>
                        <div class="stat"><span>Pending</span><strong>${escapeHtml(pending)}</strong></div>
                        <div class="stat"><span>Available</span><strong>${escapeHtml(available)}</strong></div>
                        <div class="stat"><span>Converted</span><strong>${escapeHtml(converted)}</strong></div>
                    </div>
                    <p class="muted">Wallet value available: ${escapeHtml(formatMoney(walletAmount, currency))}</p>
                </article>
            `;
        }

        function renderProfileView(user, firstName, lastName) {
            const fullName = displayProfileValue(
                user.name || [firstName, user.middle_name, lastName].filter(Boolean).join(' '),
                'Member',
            );
            const triumphantId = displayProfileValue(user.triumphant_id, user.triumphant_id_status_message || 'Your Triumphant ID will be ready after you update your membership status.');
            const residence = [user.country_of_residence, user.state_county_province].filter(Boolean).join(', ');
            return `
                <div class="profile-hero">
                    ${profileAvatarMarkup(user, fullName, firstName, lastName)}
                    <div class="profile-identity">
                        <h3>${escapeHtml(fullName)}</h3>
                        <p>${escapeHtml(user.email || 'Review the member information attached to your Goshen account.')}</p>
                    </div>
                    <div class="profile-triumphant-id" aria-label="Triumphant ID">
                        <span>Triumphant ID</span>
                        <strong>${escapeHtml(triumphantId)}</strong>
                    </div>
                    ${profilePhotoNotice(user)}
                    <div class="profile-actions">
                        <button class="button small profile-edit-button" type="button">Update / edit profile</button>
                    </div>
                </div>
                <div class="profile-detail-grid">
                    ${profileDetailCard('Phone', user.phone)}
                    ${profileDetailCard('Email', user.email)}
                    ${profileDetailCard('Title', user.title || user.profile_title)}
                    ${profileDetailCard('First name', firstName)}
                    ${profileDetailCard('Middle name', user.middle_name)}
                    ${profileDetailCard('Last name', lastName)}
                    ${profileDetailCard('Gender', titleCaseLabel(user.gender))}
                    ${profileDetailCard('Marital status', user.marital_status)}
                    ${profileDetailCard('Member type', titleCaseLabel(user.member_type || 'church_member'))}
                    ${profileDetailCard('Birthday', user.birthday_month_day, 'Not provided')}
                    ${profileDetailCard('Church group', user.group_name, 'Not selected')}
                    ${profileDetailCard('Residence', residence)}
                    ${profileDetailCard('Address', user.address, 'Not provided', true)}
                    ${profileDetailCard('About me', user.about_me, 'Not provided', true)}
                </div>
                ${renderProfileReferralCard()}
            `;
        }

        function renderProfileEditForm(user, firstName, lastName) {
            return `
                <div class="profile-card-head">
                    <div>
                        <h3>Edit profile</h3>
                        <p class="muted">Update the details used for registration, tickets, and payment records.</p>
                    </div>
                    <button class="button small outline profile-cancel-edit" type="button">Cancel</button>
                </div>
                <form class="form profile-update-form" enctype="multipart/form-data">
                    <input type="hidden" name="email" value="${escapeHtml(user.email || '')}">
                    <div class="profile-edit-photo-card">
                        ${profileAvatarMarkup(user, displayProfileValue(user.name || [firstName, user.middle_name, lastName].filter(Boolean).join(' '), 'Member'), firstName, lastName)}
                        <p class="muted">Your profile image is optional. When uploaded, the same photo will appear in a circular frame on the web app and mobile app.</p>
                        <div class="field">
                            <label>Profile photo (optional)</label>
                            <input class="input" name="avatar" type="file" accept="image/*">
                            <small class="muted">On your phone, you can take a new photo or choose one from your gallery. Maximum size: 5MB.</small>
                        </div>
                    </div>
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
                            <select class="input" name="member_type" required ${user.membership_status_change_locked ? 'disabled' : ''}>${optionMarkup([{ value: 'church_member', label: 'Church member' }, { value: 'visitor', label: 'Visitor' }], user.member_type || 'church_member')}</select>
                            <small class="muted">${escapeHtml(user.membership_status_change_message || 'You can update this status once every 30 days.')}</small>
                        </div>
                        <div class="field"><label for="profileBirthdayMonthDay">Birthday (month and day)</label><input class="input" id="profileBirthdayMonthDay" name="birthday_month_day" type="text" inputmode="numeric" maxlength="5" autocomplete="bday" placeholder="MM-DD" value="${escapeHtml(user.birthday_month_day || '')}" pattern="^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$" title="Use MM-DD, for example 07-23." aria-describedby="profileBirthdayMonthDayHint"><small class="muted" id="profileBirthdayMonthDayHint">Enter month and day as MM-DD, for example 07-23. We would love to celebrate your birthday with you.</small></div>
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
                    <div class="profile-actions">
                        <button class="button small" type="submit">Save profile</button>
                        <button class="button small outline profile-cancel-edit" type="button">Cancel</button>
                    </div>
                </form>
            `;
        }

        function renderProfile() {
            const user = currentUser || {};
            const nameParts = splitName(user.name || '');
            const firstName = user.first_name || nameParts.first;
            const lastName = user.last_name || nameParts.last;
            document.getElementById('profileCard').innerHTML = profileEditMode
                ? renderProfileEditForm(user, firstName, lastName)
                : renderProfileView(user, firstName, lastName);
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

        document.querySelectorAll('[data-password-toggle]').forEach((button) => {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;

            button.addEventListener('click', () => {
                const shouldReveal = input.type === 'password';
                input.type = shouldReveal ? 'text' : 'password';
                button.textContent = shouldReveal ? 'Hide' : 'Show';
                button.setAttribute('aria-pressed', shouldReveal ? 'true' : 'false');
                input.focus({ preventScroll: true });
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

        profileCompletionNotice?.addEventListener('click', (event) => {
            const action = event.target.closest('[data-profile-completion-action]');
            if (!action) return;
            if (action.dataset.profileCompletionAction === 'complete') {
                beginProfileCompletion(document.querySelector('.registration-form'));
                return;
            }
            closeProfileCompletionNotice(true);
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
            const birthdayInput = event.target.closest('.profile-update-form [name="birthday_month_day"]');
            if (birthdayInput) {
                validateBirthdayMonthDayInput(birthdayInput, { format: true });
                return;
            }
            const avatarInput = event.target.closest('.profile-update-form input[name="avatar"]');
            if (avatarInput) {
                const file = avatarInput.files?.[0];
                const frame = avatarInput.closest('.profile-update-form')?.querySelector('.profile-avatar-frame');
                if (file && frame) {
                    if (avatarInput.dataset.previewUrl) {
                        URL.revokeObjectURL(avatarInput.dataset.previewUrl);
                    }
                    const previewUrl = URL.createObjectURL(file);
                    const fullName = currentUser?.name || 'Member';
                    frame.innerHTML = `<img class="profile-avatar-image" src="${previewUrl}" alt="${escapeHtml(fullName)} profile photo preview">`;
                    avatarInput.dataset.previewUrl = previewUrl;
                }
                return;
            }
            const quantity = event.target.closest('.attendee-quantity');
            if (quantity) {
                const form = quantity.closest('.registration-form');
                const eventModel = eventsCache.find((item) => `${item.public_id}` === `${form.dataset.eventId}`);
                syncRegistrationQuantity(form, eventModel, quantity.value);
            }
            const mode = event.target.closest('.payment-mode');
            if (mode) {
                const form = mode.closest('.registration-form');
                form.querySelector('.voucher-field').hidden = mode.value !== 'voucher';
                form.querySelector('[name="voucher_code"]').required = mode.value === 'voucher';
            }
        });

        document.getElementById('portalMain').addEventListener('change', (event) => {
            const birthdayInput = event.target.closest('.profile-update-form [name="birthday_month_day"]');
            if (birthdayInput) {
                validateBirthdayMonthDayInput(birthdayInput, { format: true });
                return;
            }
            const ticketSelect = event.target.closest('.registration-form [name="ticket_type_id"]');
            if (!ticketSelect) return;

            const form = ticketSelect.closest('.registration-form');
            const eventModel = eventsCache.find((item) => `${item.public_id}` === `${form.dataset.eventId}`);
            syncRegistrationQuantity(form, eventModel);
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
            const walletVoucherTopUp = event.target.closest('.wallet-voucher-topup-form');
            if (walletVoucherTopUp) {
                event.preventDefault();
                const data = walletFormPayload(walletVoucherTopUp);
                if (!data.code) {
                    notify('Enter a voucher code first.', 'error');
                    return;
                }
                setBusy(walletVoucherTopUp, true);
                try {
                    const payload = await apiPost('/api/goshen-wallet/top-up/voucher', authPayload({ code: data.code }));
                    applyWalletPayload(payload, 'Voucher added to your Goshen wallet.');
                    walletVoucherTopUp.reset();
                } catch (error) {
                    notify(error.message, 'error');
                } finally {
                    setBusy(walletVoucherTopUp, false);
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
                const birthdayInput = profileUpdate.querySelector('[name="birthday_month_day"]');
                if (birthdayInput) validateBirthdayMonthDayInput(birthdayInput);
                if (!profileUpdate.reportValidity()) return;
                const data = profileUpdateFormData(profileUpdate);
                setBusy(profileUpdate, true);
                try {
                    const payload = await apiPostFormData('/api/updateProfile', data);
                    currentUser = { ...currentUser, ...(payload.user || {}), api_token: payload.user?.api_token || currentUser.api_token };
                    localStorage.setItem(storageKey, JSON.stringify(currentUser));
                    updateUserIdentity();
                    profileEditMode = false;
                    renderProfile();
                    await loadMemberRetreatData();
                    if (readPendingRegistration() && !profileNeedsCompletion()) {
                        showPage('retreat');
                        renderEvents();
                        notify('Your profile is ready. We restored your retreat registration so you can finish payment.');
                    } else {
                        notify(payload.message || 'Profile updated successfully.');
                    }
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
            const completeProfileForRegistration = event.target.closest('.complete-profile-for-registration');
            if (completeProfileForRegistration) {
                beginProfileCompletion(completeProfileForRegistration.closest('.registration-form'));
                return;
            }
            const copyReferralCode = event.target.closest('.referral-copy-code');
            if (copyReferralCode) {
                await copyReferralText(normalizeReferralCode(copyReferralCode.dataset.referralCode), 'Referral code copied.');
                return;
            }
            const copyReferralLink = event.target.closest('.referral-copy-link');
            if (copyReferralLink) {
                const code = normalizeReferralCode(copyReferralLink.dataset.referralCode);
                await copyReferralText(referralShareLink(code), 'Invitation link copied.');
                return;
            }
            const shareReferral = event.target.closest('.referral-share-button');
            if (shareReferral) {
                const code = normalizeReferralCode(shareReferral.dataset.referralCode);
                const share = referralShareDetails(code);
                try {
                    if (!navigator.share) {
                        await copyReferralText(share.url, 'Sharing is not available here, so the invitation link was copied.');
                        return;
                    }
                    await navigator.share({ title: share.title, text: share.text, url: share.url });
                } catch (error) {
                    if (error?.name !== 'AbortError') notify('We could not open sharing just now. Please try again.', 'error');
                }
                return;
            }
            const shareReferralNetwork = event.target.closest('.referral-share-network');
            if (shareReferralNetwork) {
                const code = normalizeReferralCode(shareReferralNetwork.dataset.referralCode);
                const share = referralShareDetails(code);
                const shareUrl = shareReferralNetwork.dataset.shareNetwork === 'facebook'
                    ? `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(share.url)}`
                    : `https://wa.me/?text=${encodeURIComponent(`${share.text}\n\n${share.url}`)}`;
                const popup = window.open(shareUrl, '_blank', 'noopener,noreferrer');
                if (popup) popup.opener = null;
                return;
            }
            const editProfile = event.target.closest('.profile-edit-button');
            if (editProfile) {
                profileEditMode = true;
                renderProfile();
                return;
            }
            const cancelProfileEdit = event.target.closest('.profile-cancel-edit');
            if (cancelProfileEdit) {
                profileEditMode = false;
                renderProfile();
                return;
            }
            const quantityButton = event.target.closest('.attendee-quantity-decrease, .attendee-quantity-increase');
            if (quantityButton) {
                const form = quantityButton.closest('.registration-form');
                const quantityInput = form?.querySelector('.attendee-quantity');
                if (!form || !quantityInput) return;

                const eventModel = eventsCache.find((item) => `${item.public_id}` === `${form.dataset.eventId}`);
                const delta = quantityButton.classList.contains('attendee-quantity-increase') ? 1 : -1;
                const current = Number(quantityInput.value || 1);
                syncRegistrationQuantity(form, eventModel, current + delta);
                return;
            }
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
        loadGroups();
        restoreUser();
        initializeMigrationNotice();

        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
        });

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/member-sw.js', { updateViaCache: 'none' })
                    .then((registration) => registration.update().catch(() => {}))
                    .catch(() => {});
            });
        }
    </script>
</body>
</html>
