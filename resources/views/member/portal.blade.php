<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0c2230">
    <title>Goshen Retreat Portal</title>
    <link rel="manifest" href="/member-manifest.json">
    <link rel="icon" href="/favicon.png">
    <style>
        :root {
            color-scheme: light;
            --ink: #0c2230;
            --muted: #65747c;
            --line: #dce7eb;
            --wash: #f3f8fa;
            --card: #ffffff;
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

        * { box-sizing: border-box; }
        html, body { min-height: 100%; }
        body {
            margin: 0;
            background: var(--wash);
            color: var(--ink);
            overflow-x: hidden;
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
            background: #fff;
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
            background: #edf4f6;
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
            background: #f8fbfc;
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
        .button.subtle { background: #eef5f7; color: var(--ink); }
        .button.outline {
            background: #fff;
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
            background: rgba(243, 248, 250, .92);
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
        .nav-mark {
            width: 26px;
            height: 26px;
            border-radius: 9px;
            border: 1px solid currentColor;
            display: inline-block;
            opacity: .9;
            position: relative;
        }
        .nav-mark::after {
            content: "";
            position: absolute;
            inset: 8px;
            border-radius: 99px;
            background: currentColor;
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
            background: #f8fbfc;
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
            background: #f8fbfc;
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
            background: #fff;
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
            background: #f8fbfc;
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

        .empty {
            border: 1px dashed var(--line);
            border-radius: 22px;
            padding: 22px;
            background: #fff;
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
            background: #f8fbfc;
            border-color: var(--line);
        }
        .drawer .user-chip span { color: var(--muted); }
        .drawer .nav-item { color: var(--ink); }
        .drawer .nav-item.active, .drawer .nav-item:hover {
            background: #eef7f3;
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
            background: rgba(255,255,255,.94);
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

        @media (min-width: 620px) {
            .form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .stats-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            .grid.two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
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
            <nav class="nav-list">
                <button class="nav-item active" type="button" data-nav-page="home"><span class="nav-mark"></span>Home</button>
                <button class="nav-item" type="button" data-nav-page="retreat"><span class="nav-mark"></span>Retreat Registration</button>
                <button class="nav-item" type="button" data-nav-page="tickets"><span class="nav-mark"></span>My Ticket</button>
                <button class="nav-item" type="button" data-nav-page="payments"><span class="nav-mark"></span>Payments</button>
                <button class="nav-item" type="button" data-nav-page="receipts"><span class="nav-mark"></span>Receipts</button>
                <button class="nav-item" type="button" data-nav-page="updates"><span class="nav-mark"></span>Updates</button>
                <button class="nav-item" type="button" data-nav-page="profile"><span class="nav-mark"></span>Profile</button>
                <button class="nav-item" type="button" data-nav-page="support"><span class="nav-mark"></span>Support</button>
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
            <button class="icon-button" type="button" data-nav-page="profile" aria-label="Open profile"><span class="nav-mark"></span></button>
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
                <nav class="nav-list">
                    <button class="nav-item active" type="button" data-nav-page="home"><span class="nav-mark"></span>Home</button>
                    <button class="nav-item" type="button" data-nav-page="retreat"><span class="nav-mark"></span>Retreat Registration</button>
                    <button class="nav-item" type="button" data-nav-page="tickets"><span class="nav-mark"></span>My Ticket</button>
                    <button class="nav-item" type="button" data-nav-page="payments"><span class="nav-mark"></span>Payments</button>
                    <button class="nav-item" type="button" data-nav-page="receipts"><span class="nav-mark"></span>Receipts</button>
                    <button class="nav-item" type="button" data-nav-page="updates"><span class="nav-mark"></span>Updates</button>
                    <button class="nav-item" type="button" data-nav-page="profile"><span class="nav-mark"></span>Profile</button>
                    <button class="nav-item" type="button" data-nav-page="support"><span class="nav-mark"></span>Support</button>
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
            <button class="active" type="button" data-nav-page="home"><span class="nav-mark"></span><span>Home</span></button>
            <button type="button" data-nav-page="retreat"><span class="nav-mark"></span><span>Retreat</span></button>
            <button type="button" data-nav-page="tickets"><span class="nav-mark"></span><span>Tickets</span></button>
            <button type="button" data-nav-page="payments"><span class="nav-mark"></span><span>Payments</span></button>
        </nav>
    </div>

    <script>
        const storageKey = 'goshen_member_user';
        const pageTitles = {
            home: 'Home',
            retreat: 'Retreat',
            tickets: 'Tickets',
            payments: 'Payments',
            receipts: 'Receipts',
            updates: 'Updates',
            profile: 'Profile',
            support: 'Support',
        };

        let currentUser = null;
        let eventsCache = [];
        let memberData = { registrations: [], payment_history: [], tickets: [], accommodation_allocations: [] };
        let activePage = 'home';

        const authShell = document.getElementById('authShell');
        const portalShell = document.getElementById('portalShell');
        const authNotice = document.getElementById('authNotice');
        const toast = document.getElementById('toast');
        const drawerBackdrop = document.getElementById('drawerBackdrop');

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

        function notify(message, type = 'ok') {
            toast.textContent = message;
            toast.classList.toggle('error', type === 'error');
            toast.hidden = false;
            clearTimeout(notify.timer);
            notify.timer = setTimeout(() => { toast.hidden = true; }, 5200);
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
            loadUpdates();
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
                select.innerHTML = '<option value="">Please select</option>' + groups
                    .map((group) => `<option value="${escapeHtml(group.id)}">${escapeHtml(group.name)}</option>`)
                    .join('');
            } catch {
                select.innerHTML = '<option value="">Church groups unavailable</option>';
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
                <div class="stat"><span>Updates</span><strong id="updateCount">${document.querySelectorAll('#updatesList .record').length || 0}</strong></div>
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

        function renderTickets() {
            const tickets = memberData.tickets || [];
            document.getElementById('ticketsList').innerHTML = tickets.length
                ? tickets.map(renderTicket).join('')
                : '<div class="empty">No issued Goshen Retreat ticket is linked to your account yet.</div>';
            loadTicketQrImages();
        }

        function renderTicket(ticket) {
            const urls = ticket.document_urls || {};
            return `
                <article class="record">
                    <div class="record-top">
                        <div class="record-title">
                            <strong>${escapeHtml(ticket.ticket_number || ticket.public_id || 'Ticket')}</strong>
                            <span class="item-meta">${escapeHtml(ticket.attendee_name || 'Attendee')} - ${escapeHtml(ticket.ticket_type || 'Goshen Retreat')}</span>
                            ${statusBadge(ticket.status)}
                        </div>
                        <div class="qr-holder" data-qr-url="${escapeHtml(urls.qr || '')}">QR</div>
                    </div>
                    <div class="record-actions">
                        ${urls.pdf ? `<button class="button small subtle ticket-download" type="button" data-url="${escapeHtml(urls.pdf)}" data-filename="${escapeHtml((ticket.ticket_number || ticket.public_id || 'ticket') + '.pdf')}">Download PDF</button>` : ''}
                        ${urls.ics ? `<button class="button small outline ticket-download" type="button" data-url="${escapeHtml(urls.ics)}" data-filename="${escapeHtml((ticket.ticket_number || ticket.public_id || 'ticket') + '.ics')}">Add to calendar</button>` : ''}
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

        function renderProfile() {
            const user = currentUser || {};
            document.getElementById('profileCard').innerHTML = `
                <h3>${escapeHtml(user.name || 'Member')}</h3>
                <div class="detail-list">
                    <div class="detail-row"><strong>Triumphant ID</strong><span>${escapeHtml(user.triumphant_id || 'Not assigned yet')}</span></div>
                    <div class="detail-row"><strong>Email</strong><span>${escapeHtml(user.email || '')}</span></div>
                    <div class="detail-row"><strong>Phone</strong><span>${escapeHtml(user.phone || '')}</span></div>
                    <div class="detail-row"><strong>Church group</strong><span>${escapeHtml(user.group_name || 'Not selected')}</span></div>
                    <div class="detail-row"><strong>Residence</strong><span>${escapeHtml([user.country_of_residence, user.state_county_province].filter(Boolean).join(', ') || 'Not provided')}</span></div>
                </div>
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

        async function walletPay(bookingId, button) {
            button.disabled = true;
            try {
                const payload = await apiPost(`/api/goshen-retreat/bookings/${encodeURIComponent(bookingId)}/wallet-pay`, authPayload());
                notify(payload.message || 'Wallet payment completed.');
                await loadMemberRetreatData();
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
                headers: { Accept: 'application/octet-stream, application/json', 'Content-Type': 'application/json' },
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
