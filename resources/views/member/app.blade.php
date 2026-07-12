<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0c2230">
    <meta name="robots" content="noindex,nofollow">
    <title>Goshen Retreat</title>
    <link rel="manifest" href="/member-manifest.json">
    <link rel="icon" href="/favicon.png">
    <style>
        :root {
            color-scheme: light;
            --navy: #0c2230;
            --navy-2: #143c4b;
            --gold: #f8b823;
            --green: #0f5a45;
            --ink: #102532;
            --muted: #687783;
            --paper: #f4f8fa;
            --card: #ffffff;
            --line: rgba(16, 37, 50, .1);
            --danger: #c62828;
            --success: #087a55;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--paper);
            color: var(--ink);
        }

        button, input, select, textarea { font: inherit; }

        .shell {
            min-height: 100vh;
            background:
                radial-gradient(circle at 92% 8%, rgba(248, 184, 35, .18), transparent 26rem),
                linear-gradient(180deg, #eef6f8, #f8fafb 42%, #eef6f8);
        }

        .hero {
            padding: max(1.4rem, env(safe-area-inset-top)) 1.15rem 2.2rem;
            background:
                radial-gradient(circle at 86% 18%, rgba(248, 184, 35, .22), transparent 12rem),
                linear-gradient(135deg, var(--navy), var(--green));
            color: white;
            border-bottom-left-radius: 2rem;
            border-bottom-right-radius: 2rem;
            box-shadow: 0 1.25rem 2.5rem rgba(12, 34, 48, .2);
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
            margin-bottom: 1.6rem;
        }

        .brand-main {
            display: flex;
            align-items: center;
            gap: .8rem;
        }

        .brand img,
        .avatar {
            width: 3.15rem;
            height: 3.15rem;
            border-radius: 999px;
            object-fit: cover;
            background: white;
            box-shadow: 0 .75rem 1.5rem rgba(0, 0, 0, .25);
        }

        .avatar {
            display: grid;
            place-items: center;
            color: var(--navy);
            font-weight: 900;
        }

        .eyebrow {
            color: var(--gold);
            font-size: .86rem;
            font-weight: 800;
            letter-spacing: .02em;
        }

        h1 {
            margin: .18rem 0 0;
            font-size: clamp(2.3rem, 9vw, 4.7rem);
            line-height: .96;
            letter-spacing: 0;
        }

        .hero p {
            max-width: 42rem;
            margin: 1rem 0 0;
            color: rgba(255, 255, 255, .8);
            font-size: clamp(1rem, 3.3vw, 1.25rem);
            line-height: 1.6;
        }

        .retreat-hero {
            position: relative;
            overflow: hidden;
        }

        .hero-layout {
            display: grid;
            gap: 1.25rem;
            align-items: center;
        }

        .hero-copy {
            min-width: 0;
            position: relative;
            z-index: 1;
        }

        .hero-meta {
            max-width: 42rem;
            margin-top: .8rem;
            color: rgba(255, 255, 255, .72);
            font-weight: 750;
            line-height: 1.45;
        }

        .hero-media-card {
            min-height: 15rem;
            border-radius: 1.5rem;
            overflow: hidden;
            position: relative;
            border: 1px solid rgba(255, 255, 255, .18);
            background:
                radial-gradient(circle at 28% 20%, rgba(248, 184, 35, .28), transparent 10rem),
                linear-gradient(140deg, rgba(255, 255, 255, .16), rgba(255, 255, 255, .05));
            box-shadow: 0 1.4rem 3rem rgba(0, 0, 0, .18);
        }

        .hero-media-card > img {
            width: 100%;
            height: 100%;
            min-height: 15rem;
            display: block;
            object-fit: cover;
        }

        .retreat-hero.has-feature-image .hero-media-card {
            min-height: clamp(18rem, 42vw, 28rem);
        }

        .retreat-hero.has-feature-image .hero-media-card > img {
            min-height: clamp(18rem, 42vw, 28rem);
        }

        .hero-media-fallback {
            min-height: 15rem;
            display: grid;
            place-items: center;
            align-content: center;
            gap: .65rem;
            padding: 1.4rem;
            text-align: center;
            color: rgba(255, 255, 255, .82);
        }

        .hero-media-fallback img {
            width: 5.25rem;
            height: 5.25rem;
            border-radius: 999px;
            object-fit: cover;
            background: white;
            box-shadow: 0 1rem 1.8rem rgba(0, 0, 0, .22);
        }

        .hero-media-fallback strong {
            display: block;
            font-size: 1.25rem;
            line-height: 1.15;
        }

        .hero-media-fallback span {
            max-width: 18rem;
            font-weight: 700;
            line-height: 1.45;
        }

        .hero-media-caption {
            position: absolute;
            inset-inline: 0;
            bottom: 0;
            display: grid;
            gap: .25rem;
            padding: 3.25rem 1rem 1rem;
            color: white;
            background: linear-gradient(180deg, transparent, rgba(12, 34, 48, .82));
        }

        .hero-media-caption strong,
        .hero-media-caption span {
            overflow-wrap: anywhere;
        }

        .hero-media-caption strong {
            font-size: 1rem;
            line-height: 1.2;
        }

        .hero-media-caption span {
            color: rgba(255, 255, 255, .78);
            font-size: .88rem;
            font-weight: 750;
            line-height: 1.35;
        }

        .countdown {
            display: grid;
            gap: .75rem;
            min-width: 0;
            margin-top: 1rem;
            padding: .85rem;
            border-radius: 1.1rem;
            border: 1px solid rgba(16, 37, 50, .08);
            background: #f8fbfc;
        }

        .hero-countdown {
            max-width: 32rem;
            border-color: rgba(255, 255, 255, .22);
            background: rgba(255, 255, 255, .11);
            backdrop-filter: blur(10px);
        }

        .countdown-label {
            color: var(--navy);
            font-weight: 900;
            line-height: 1.25;
        }

        .hero-countdown .countdown-label {
            color: white;
        }

        .countdown-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .55rem;
        }

        .countdown-grid span {
            min-width: 0;
            padding: .7rem .55rem;
            border-radius: .9rem;
            background: white;
            color: var(--navy);
            text-align: center;
            box-shadow: 0 .7rem 1.4rem rgba(12, 34, 48, .08);
        }

        .hero-countdown .countdown-grid span {
            background: rgba(255, 255, 255, .92);
        }

        .countdown-grid strong {
            display: block;
            font-size: clamp(1.25rem, 4vw, 2rem);
            line-height: 1;
            overflow-wrap: anywhere;
        }

        .countdown-grid small {
            display: block;
            margin-top: .25rem;
            color: var(--muted);
            font-size: .74rem;
            font-weight: 850;
            text-transform: uppercase;
        }

        .countdown-note {
            color: var(--muted);
            font-size: .86rem;
            font-weight: 750;
            line-height: 1.35;
        }

        .hero-countdown .countdown-note {
            color: rgba(255, 255, 255, .78);
        }

        .content {
            width: min(72rem, 100%);
            margin: -1.15rem auto 0;
            padding: 0 1rem 2.5rem;
        }

        .panel {
            background: rgba(255, 255, 255, .92);
            border: 1px solid var(--line);
            border-radius: 1.65rem;
            box-shadow: 0 1.2rem 2.5rem rgba(12, 34, 48, .08);
            backdrop-filter: blur(14px);
            padding: 1.1rem;
            margin-bottom: 1rem;
        }

        .section-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 1rem;
            margin: .2rem .15rem 1rem;
        }

        h2 {
            margin: 0;
            font-size: clamp(1.45rem, 4vw, 2rem);
            line-height: 1.1;
        }

        .hint {
            color: var(--muted);
            font-weight: 650;
            line-height: 1.5;
        }

        .grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(min(18rem, 100%), 1fr));
        }

        .auth-layout {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(min(22rem, 100%), 1fr));
            align-items: start;
        }

        .auth-card {
            background:
                radial-gradient(circle at 96% 4%, rgba(248, 184, 35, .16), transparent 10rem),
                linear-gradient(180deg, #fff, #f7fbfc);
            border: 1px solid var(--line);
            border-radius: 1.35rem;
            padding: 1rem;
        }

        .tabs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: .45rem;
            padding: .35rem;
            border-radius: 1rem;
            background: #eef4f6;
            margin-bottom: 1rem;
        }

        .tab {
            border: 0;
            border-radius: .8rem;
            min-height: 2.55rem;
            background: transparent;
            color: var(--muted);
            font-weight: 900;
            cursor: pointer;
        }

        .tab.active {
            background: var(--navy);
            color: white;
            box-shadow: 0 .8rem 1.5rem rgba(12, 34, 48, .16);
        }

        .form {
            display: grid;
            gap: .75rem;
        }

        .inline-form {
            display: grid;
            gap: .75rem;
            margin-top: 1rem;
            padding: .85rem;
            border-radius: 1rem;
            background: #f8fbfc;
            border: 1px solid rgba(16, 37, 50, .08);
        }

        .two-col {
            display: grid;
            gap: .75rem;
            grid-template-columns: repeat(auto-fit, minmax(min(12rem, 100%), 1fr));
        }

        .attendee-card {
            display: grid;
            gap: .65rem;
            padding: .8rem;
            border-radius: 1rem;
            border: 1px solid rgba(16, 37, 50, .08);
            background: white;
        }

        .attendee-card strong {
            color: var(--navy);
        }

        .consent-card {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: .7rem;
            align-items: flex-start;
            padding: .85rem;
            border-radius: 1rem;
            border: 1px solid rgba(16, 37, 50, .08);
            background: white;
        }

        .consent-card input {
            margin-top: .18rem;
        }

        .consent-card strong {
            display: block;
            color: var(--navy);
            margin-bottom: .25rem;
        }

        .field {
            display: grid;
            gap: .35rem;
        }

        .field label {
            font-weight: 850;
            color: var(--ink);
        }

        .input {
            width: 100%;
            min-height: 3rem;
            border: 1px solid var(--line);
            border-radius: 1rem;
            background: #f7fbfc;
            color: var(--ink);
            padding: .8rem .95rem;
            outline: none;
        }

        .input:focus {
            border-color: rgba(248, 184, 35, .85);
            box-shadow: 0 0 0 .22rem rgba(248, 184, 35, .18);
        }
        .password-control {
            position: relative;
            display: grid;
        }
        .password-control .input {
            padding-right: 5.35rem;
        }
        .password-reveal {
            position: absolute;
            top: 50%;
            right: .45rem;
            transform: translateY(-50%);
            min-height: 2.25rem;
            border: 0;
            border-radius: .8rem;
            padding: 0 .72rem;
            background: white;
            color: var(--green);
            box-shadow: inset 0 0 0 1px var(--line);
            cursor: pointer;
            font-size: .82rem;
            font-weight: 900;
        }
        .password-reveal:focus-visible {
            outline: .2rem solid rgba(248, 184, 35, .32);
            outline-offset: .12rem;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.85rem;
            padding: .75rem 1rem;
            border-radius: 999px;
            border: 0;
            background: var(--navy);
            color: white;
            font: inherit;
            font-weight: 850;
            text-decoration: none;
            cursor: pointer;
        }

        .button.alt {
            background: var(--gold);
            color: var(--navy);
        }

        .button.ghost {
            background: #eef4f6;
            color: var(--navy);
        }

        .button:disabled {
            opacity: .62;
            cursor: wait;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            margin-top: 1rem;
        }

        .notice {
            display: none;
            margin: 0 0 1rem;
            padding: .82rem .9rem;
            border-radius: 1rem;
            border: 1px solid transparent;
            font-weight: 750;
            line-height: 1.45;
        }

        .notice.show { display: block; }
        .notice.error {
            color: var(--danger);
            background: rgba(198, 40, 40, .08);
            border-color: rgba(198, 40, 40, .18);
        }
        .notice.success {
            color: var(--success);
            background: rgba(8, 122, 85, .08);
            border-color: rgba(8, 122, 85, .18);
        }

        .profile-chip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .9rem;
            border-radius: 1.2rem;
            background: #eef6f8;
            border: 1px solid var(--line);
        }

        .profile-left {
            display: flex;
            align-items: center;
            gap: .8rem;
            min-width: 0;
        }

        .profile-left strong,
        .profile-left span {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .profile-warning {
            margin-top: .8rem;
            padding: .85rem .9rem;
            border-radius: 1rem;
            background: rgba(248, 184, 35, .14);
            border: 1px solid rgba(248, 184, 35, .35);
            color: #775000;
            font-weight: 750;
            line-height: 1.45;
        }

        .event-card {
            overflow: hidden;
            border-radius: 1.35rem;
            border: 1px solid var(--line);
            background: var(--card);
        }

        .event-media {
            min-height: 12rem;
            background:
                linear-gradient(135deg, rgba(12, 34, 48, .85), rgba(15, 90, 69, .72)),
                url("/favicon.png") center/5rem no-repeat;
            position: relative;
        }

        .event-media img {
            width: 100%;
            height: 100%;
            min-height: 12rem;
            object-fit: cover;
            display: block;
        }

        .event-body { padding: 1rem; }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .45rem .72rem;
            border-radius: 999px;
            background: rgba(248, 184, 35, .18);
            color: #815500;
            font-size: .82rem;
            font-weight: 850;
        }

        .event-title {
            margin: .8rem 0 .45rem;
            font-size: 1.35rem;
            line-height: 1.15;
            font-weight: 900;
        }

        .event-meta {
            color: var(--muted);
            font-weight: 650;
            line-height: 1.5;
        }

        .event-countdown {
            margin-top: .85rem;
        }

        .past-videos {
            margin-top: 1rem;
            padding-top: .95rem;
            border-top: 1px solid var(--line);
        }

        .video-section-title {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: .75rem;
            margin-bottom: .75rem;
        }

        .video-section-title strong {
            color: var(--navy);
            font-size: .98rem;
            line-height: 1.25;
        }

        .video-section-title span {
            color: var(--muted);
            font-size: .82rem;
            font-weight: 800;
            line-height: 1.35;
            text-align: right;
        }

        .video-rail {
            display: flex;
            gap: .75rem;
            overflow-x: auto;
            overscroll-behavior-inline: contain;
            scroll-snap-type: x proximity;
            scrollbar-width: thin;
            padding: .05rem .05rem .45rem;
        }

        .video-card {
            flex: 0 0 min(18.5rem, 86vw);
            min-width: 0;
            overflow: hidden;
            scroll-snap-align: start;
            border-radius: 1rem;
            border: 1px solid rgba(16, 37, 50, .08);
            background: #fff;
            box-shadow: 0 .8rem 1.6rem rgba(12, 34, 48, .07);
        }

        .video-frame {
            aspect-ratio: 16 / 9;
            background:
                linear-gradient(135deg, rgba(12, 34, 48, .94), rgba(15, 90, 69, .82)),
                url("/favicon.png") center/4rem no-repeat;
        }

        .video-frame iframe {
            width: 100%;
            height: 100%;
            display: block;
            border: 0;
        }

        .video-body {
            display: grid;
            gap: .55rem;
            padding: .8rem;
        }

        .video-body strong {
            color: var(--navy);
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .video-body span {
            color: var(--muted);
            font-size: .86rem;
            font-weight: 700;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .video-body .button {
            min-height: 2.35rem;
            padding: .55rem .8rem;
            font-size: .84rem;
            justify-self: start;
        }

        details {
            margin-top: 1rem;
            border-top: 1px solid var(--line);
            padding-top: .85rem;
        }

        summary {
            cursor: pointer;
            color: var(--navy);
            font-weight: 900;
            list-style: none;
        }

        summary::-webkit-details-marker { display: none; }

        .detail-list {
            display: grid;
            gap: .65rem;
            margin-top: .85rem;
        }

        .mini-row {
            padding: .78rem .85rem;
            border-radius: 1rem;
            background: #f3f7f8;
            border: 1px solid rgba(16, 37, 50, .06);
        }

        .mini-row strong {
            display: block;
            margin-bottom: .2rem;
            font-size: .95rem;
        }

        .mini-row span {
            display: block;
            color: var(--muted);
            font-size: .88rem;
            font-weight: 650;
            line-height: 1.45;
        }

        .ticket-card {
            display: grid;
            gap: .8rem;
        }

        .ticket-status {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .5rem;
            padding: .72rem .8rem;
            border-radius: .95rem;
            border: 1px solid rgba(225, 166, 59, .28);
            background: rgba(225, 166, 59, .12);
            color: var(--navy);
            font-weight: 900;
        }

        .ticket-status.checked {
            border-color: rgba(17, 132, 91, .28);
            background: rgba(17, 132, 91, .12);
        }

        .ticket-status small {
            color: var(--muted);
            font-weight: 800;
        }

        .ticket-qr {
            display: grid;
            grid-template-columns: 6.25rem 1fr;
            gap: .85rem;
            align-items: center;
            padding: .75rem;
            border-radius: 1rem;
            background: white;
            border: 1px solid rgba(16, 37, 50, .08);
        }

        .ticket-qr img {
            width: 6.25rem;
            height: 6.25rem;
            border-radius: .85rem;
            background: white;
            padding: .35rem;
            border: 1px solid rgba(16, 37, 50, .08);
        }

        .ticket-qr code {
            display: block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-top: .35rem;
            color: var(--muted);
            font-size: .75rem;
        }

        .ticket-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .55rem;
            margin-top: .2rem;
        }

        .ticket-actions .button {
            min-height: 2.35rem;
            padding: .55rem .85rem;
            font-size: .85rem;
            text-decoration: none;
        }

        .management-toolbar {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: .75rem;
            align-items: end;
            margin-bottom: 1rem;
        }

        .management-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(10rem, 100%), 1fr));
            gap: .65rem;
            margin-bottom: .85rem;
        }

        .stat-card {
            min-width: 0;
            padding: .9rem;
            border-radius: 1rem;
            background: linear-gradient(180deg, #fff, #f5f9fa);
            border: 1px solid rgba(16, 37, 50, .08);
        }

        .stat-card span {
            display: block;
            color: var(--muted);
            font-size: .82rem;
            font-weight: 800;
            line-height: 1.35;
        }

        .stat-card strong {
            display: block;
            margin-top: .25rem;
            color: var(--navy);
            font-size: clamp(1.25rem, 4vw, 1.8rem);
            line-height: 1.05;
            overflow-wrap: anywhere;
        }

        .stat-card small {
            display: block;
            margin-top: .35rem;
            color: var(--muted);
            font-weight: 750;
            line-height: 1.35;
        }

        .management-breakdowns {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(15rem, 100%), 1fr));
            gap: .75rem;
            margin-bottom: .85rem;
        }

        .breakdown-card {
            min-width: 0;
            padding: .85rem;
            border-radius: 1rem;
            background: #f8fbfc;
            border: 1px solid rgba(16, 37, 50, .07);
        }

        .breakdown-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .6rem;
            margin-bottom: .7rem;
            color: var(--navy);
            font-weight: 900;
        }

        .breakdown-title span {
            color: var(--muted);
            font-size: .8rem;
            font-weight: 800;
        }

        .breakdown-chart {
            display: grid;
            grid-template-columns: 5.4rem minmax(0, 1fr);
            gap: .75rem;
            align-items: center;
            margin-bottom: .75rem;
        }

        .breakdown-donut {
            position: relative;
            display: grid;
            place-items: center;
            width: 5.25rem;
            aspect-ratio: 1;
            border-radius: 50%;
            background: var(--donut-gradient, conic-gradient(var(--gold) 0 100%));
            box-shadow: inset 0 0 0 1px rgba(16, 37, 50, .05);
        }

        .breakdown-donut::after {
            content: "";
            position: absolute;
            inset: 1.05rem;
            border-radius: 50%;
            background: #f8fbfc;
            box-shadow: 0 0 0 1px rgba(16, 37, 50, .04);
        }

        .breakdown-donut span {
            position: relative;
            z-index: 1;
            color: var(--navy);
            font-size: .9rem;
            font-weight: 950;
            line-height: 1;
        }

        .breakdown-legend {
            display: grid;
            gap: .35rem;
            min-width: 0;
        }

        .breakdown-legend-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .55rem;
            min-width: 0;
            color: var(--muted);
            font-size: .78rem;
            font-weight: 800;
            line-height: 1.25;
        }

        .breakdown-legend-item strong {
            display: flex;
            align-items: center;
            min-width: 0;
            gap: .4rem;
            color: var(--ink);
            font-weight: 850;
        }

        .breakdown-legend-item strong span:last-child {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .breakdown-swatch {
            width: .62rem;
            height: .62rem;
            flex: 0 0 auto;
            border-radius: 999px;
            background: var(--swatch, var(--gold));
        }

        .breakdown-row {
            display: grid;
            gap: .32rem;
            margin-top: .62rem;
        }

        .breakdown-line {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: .65rem;
            color: var(--ink);
            font-weight: 800;
            line-height: 1.35;
        }

        .breakdown-line span:first-child {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .breakdown-line span:last-child {
            color: var(--muted);
            white-space: nowrap;
        }

        .breakdown-track {
            height: .52rem;
            overflow: hidden;
            border-radius: 999px;
            background: rgba(12, 34, 48, .08);
        }

        .breakdown-fill {
            display: block;
            height: 100%;
            width: var(--bar-width, 0%);
            border-radius: inherit;
            background: linear-gradient(90deg, var(--gold), var(--green));
        }

        .management-table-wrap {
            overflow-x: auto;
            border: 1px solid rgba(16, 37, 50, .07);
            border-radius: 1rem;
            background: white;
        }

        .management-table {
            width: 100%;
            min-width: 42rem;
            border-collapse: collapse;
            font-size: .88rem;
        }

        .management-table caption {
            padding: .8rem .9rem;
            text-align: left;
            color: var(--navy);
            font-weight: 900;
        }

        .management-table th,
        .management-table td {
            padding: .7rem .85rem;
            text-align: left;
            vertical-align: top;
            border-top: 1px solid rgba(16, 37, 50, .07);
        }

        .management-table th {
            color: var(--muted);
            font-size: .75rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .management-table td {
            color: var(--ink);
            font-weight: 700;
            line-height: 1.4;
        }

        .management-table small {
            display: block;
            margin-top: .2rem;
            color: var(--muted);
            font-weight: 700;
        }

        .table-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem;
            min-width: 8rem;
        }

        @media (max-width: 520px) {
            .breakdown-chart {
                grid-template-columns: 1fr;
            }
        }

        .empty {
            padding: 2rem 1rem;
            text-align: center;
            color: var(--muted);
            font-weight: 700;
        }

        [hidden] { display: none !important; }

        @media (min-width: 720px) {
            .hero { padding-inline: max(2rem, calc((100vw - 72rem) / 2)); }
            .hero-layout {
                grid-template-columns: minmax(0, .9fr) minmax(20rem, 1fr);
            }
            .content { padding-inline: 1.4rem; }
            .panel { padding: 1.35rem; }
        }

        @media (max-width: 520px) {
            .brand {
                align-items: flex-start;
            }

            .brand .button {
                min-height: 2.45rem;
                padding: .58rem .8rem;
                font-size: .86rem;
            }

            .retreat-hero.has-feature-image .hero-media-card,
            .retreat-hero.has-feature-image .hero-media-card > img {
                min-height: 16rem;
            }

            .video-section-title {
                display: grid;
            }

            .video-section-title span {
                text-align: left;
            }

            .management-toolbar {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<main class="shell">
    <section id="retreatHero" class="hero retreat-hero">
        <div class="brand">
            <div class="brand-main">
                <img src="/favicon.png" alt="Goshen logo">
                <div>
                    <div class="eyebrow">MFM Triumphant Church</div>
                    <strong>Member web app</strong>
                </div>
            </div>
            <a class="button alt" href="/admin/goshen-retreat">Admin</a>
        </div>
        <div class="hero-layout">
            <div class="hero-copy">
                <h1 id="retreatHeroTitle">Goshen Retreat</h1>
                <p id="retreatHeroCopy">Register, verify your account, and view published retreat editions, schedules, ticket plans, and church updates from a lightweight installable web experience.</p>
                <div id="retreatHeroMeta" class="hero-meta">Published retreat editions load directly from the Goshen Retreat API.</div>
                <div id="retreatHeroCountdown" class="countdown hero-countdown">
                    <div class="countdown-label">Retreat countdown</div>
                    <div class="countdown-grid">
                        <span><strong>--</strong><small>Days</small></span>
                        <span><strong>--</strong><small>Hours</small></span>
                        <span><strong>--</strong><small>Mins</small></span>
                    </div>
                    <div class="countdown-note">Dates will appear when a retreat edition is published.</div>
                </div>
            </div>
            <div id="retreatHeroMedia" class="hero-media-card">
                <div class="hero-media-fallback">
                    <img src="/favicon.png" alt="">
                    <strong>Goshen Retreat</strong>
                    <span>Feature banner will appear here when published.</span>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="panel">
            <div id="authNotice" class="notice"></div>
            <div id="signedOut" class="auth-layout">
                <div>
                    <div class="section-head">
                        <div>
                            <h2>Member Access</h2>
                            <div class="hint">Use the same verified church account you use in the mobile app.</div>
                        </div>
                    </div>
                    <div class="auth-card">
                        <div class="tabs" role="tablist" aria-label="Member access">
                            <button class="tab active" data-tab="login" type="button">Sign in</button>
                            <button class="tab" data-tab="register" type="button">Register</button>
                            <button class="tab" data-tab="verify" type="button">Verify</button>
                        </div>

                        <form id="loginForm" class="form" autocomplete="on">
                            <div class="field">
                                <label for="loginEmail">Email address</label>
                                <input class="input" id="loginEmail" name="email" type="email" required>
                            </div>
                            <div class="field">
                                <label for="loginPassword">Password</label>
                                <div class="password-control">
                                    <input class="input" id="loginPassword" name="password" type="password" required>
                                    <button class="password-reveal" type="button" data-password-toggle="loginPassword" aria-controls="loginPassword" aria-pressed="false">Show</button>
                                </div>
                            </div>
                            <button class="button alt" type="submit">Sign in securely</button>
                        </form>

                        <form id="registerForm" class="form" autocomplete="on" hidden>
                            <div class="field">
                                <label for="registerName">Full name</label>
                                <input class="input" id="registerName" name="name" required>
                            </div>
                            <div class="field">
                                <label for="registerEmail">Email address</label>
                                <input class="input" id="registerEmail" name="email" type="email" required>
                            </div>
                            <div class="field">
                                <label for="registerPhone">Phone number</label>
                                <input class="input" id="registerPhone" name="phone" type="tel" required>
                            </div>
                            <div class="field">
                                <label for="registerGender">Gender</label>
                                <select class="input" id="registerGender" name="gender" required>
                                    <option value="">Select gender</option>
                                    <option>Male</option>
                                    <option>Female</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="registerGroup">Church group</label>
                                <select class="input" id="registerGroup" name="group_id" required>
                                    <option value="">Loading groups...</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="registerCountry">Country of residence</label>
                                <select class="input" id="registerCountry" name="country_of_residence" required>
                                    <option value="">Select country</option>
                                    <option>Nigeria</option>
                                    <option>United Kingdom</option>
                                    <option>United States</option>
                                    <option>Canada</option>
                                    <option>Ghana</option>
                                    <option>South Africa</option>
                                    <option>Other</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="registerState">State, county, or province</label>
                                <input class="input" id="registerState" name="state_county_province" required placeholder="Example: Lagos, Kent, Ontario">
                            </div>
                            <div class="field">
                                <label for="registerPassword">Password</label>
                                <div class="password-control">
                                    <input class="input" id="registerPassword" name="password" type="password" minlength="8" required>
                                    <button class="password-reveal" type="button" data-password-toggle="registerPassword" aria-controls="registerPassword" aria-pressed="false">Show</button>
                                </div>
                            </div>
                            <button class="button alt" type="submit">Create account</button>
                        </form>

                        <form id="verifyForm" class="form" autocomplete="one-time-code" hidden>
                            <div class="field">
                                <label for="verifyEmail">Email address</label>
                                <input class="input" id="verifyEmail" name="email" type="email" required>
                            </div>
                            <div class="field">
                                <label for="verifyCode">Verification code</label>
                                <input class="input" id="verifyCode" name="code" inputmode="numeric" required>
                            </div>
                            <button class="button alt" type="submit">Verify account</button>
                            <button id="resendCode" class="button ghost" type="button">Resend code</button>
                            <div class="hint">If the email is not in your inbox, check your spam folder. Verification codes expire after the configured security window.</div>
                        </form>
                    </div>
                </div>

                <div class="auth-card">
                    <h2>Why sign in?</h2>
                    <p class="hint">Verified members can safely access church-only retreat actions as they are added: bookings, payment tracking, receipts, and personalized updates.</p>
                    <div class="detail-list">
                        <div class="mini-row"><strong>Protected account</strong><span>Your email must be verified before member-only actions are enabled.</span></div>
                        <div class="mini-row"><strong>Retreat ready</strong><span>Your profile details will help future bookings and ticket checks stay accurate.</span></div>
                        <div class="mini-row"><strong>Installable web app</strong><span>Add this page to your phone for quick staging checks without rebuilding the Flutter APK.</span></div>
                    </div>
                </div>
            </div>

            <div id="signedIn" hidden>
                <div class="profile-chip">
                    <div class="profile-left">
                        <div id="profileAvatar" class="avatar">G</div>
                        <div>
                            <strong id="profileName">Member</strong>
                            <span id="profileEmail" class="hint">Signed in</span>
                            <span id="profileTriumphantId" class="hint"></span>
                        </div>
                    </div>
                    <button id="logoutButton" class="button ghost" type="button">Sign out</button>
                </div>
                <div id="profileReadiness" class="profile-warning" hidden></div>
            </div>
        </div>

        <div id="memberRetreatPanel" class="panel" hidden>
            <div class="section-head">
                <div>
                    <h2>My Goshen Retreat</h2>
                    <div class="hint">Your registrations, payment schedule, tickets, and accommodation allocations will appear here.</div>
                </div>
                <button id="refreshMemberData" class="button ghost" type="button">Refresh</button>
            </div>
            <div id="memberRetreatData" class="detail-list">
                <div class="empty">Sign in to load your retreat records.</div>
            </div>
        </div>

        <div id="goshenManagementPanel" class="panel" hidden>
            <div class="section-head">
                <div>
                    <h2>Registration management</h2>
                    <div class="hint">Live Goshen Retreat registration totals, attendee choices, and recent activity.</div>
                </div>
            </div>
            <div class="management-toolbar">
                <div class="field">
                    <label for="goshenManagementEvent">Retreat edition</label>
                    <select id="goshenManagementEvent" class="input"></select>
                </div>
                <button id="refreshGoshenManagement" class="button ghost" type="button">Refresh</button>
            </div>
            <div id="goshenManagementData" class="detail-list">
                <div class="empty">Sign in with a permitted account to load registration management.</div>
            </div>
        </div>

        <div id="retreatSetupPanel" class="panel" hidden>
            <div class="section-head">
                <div>
                    <h2>Retreat setup</h2>
                    <div class="hint">Quick view of event dates, schedules, ticket types, and registration setup.</div>
                </div>
            </div>
            <div class="management-toolbar">
                <div class="field">
                    <label for="retreatSetupEvent">Retreat edition</label>
                    <select id="retreatSetupEvent" class="input"></select>
                </div>
                <button id="refreshRetreatSetup" class="button ghost" type="button">Refresh setup</button>
            </div>
            <div id="retreatSetupData" class="detail-list">
                <div class="empty">Sign in with a permitted account to load retreat setup.</div>
            </div>
        </div>

        <div id="accommodationManagementPanel" class="panel" hidden>
            <div class="section-head">
                <div>
                    <h2>Accommodation management</h2>
                    <div class="hint">Assign or update rooms and beds for paid attendees with active tickets.</div>
                </div>
            </div>
            <div class="management-toolbar">
                <div class="field">
                    <label for="accommodationManagementEvent">Retreat edition</label>
                    <select id="accommodationManagementEvent" class="input"></select>
                </div>
                <button id="refreshAccommodationManagement" class="button ghost" type="button">Refresh</button>
            </div>
            <div id="accommodationManagementData" class="detail-list">
                <div class="empty">Sign in with a permitted account to load accommodation management.</div>
            </div>
        </div>

        <div id="fundraisingManagementPanel" class="panel" hidden>
            <div class="section-head">
                <div>
                    <h2>Fundraising management</h2>
                    <div class="hint">Campaign totals, contribution status, payment channels, and recent fundraising activity.</div>
                </div>
                <button id="refreshFundraisingManagement" class="button ghost" type="button">Refresh</button>
            </div>
            <div id="fundraisingManagementData" class="detail-list">
                <div class="empty">Sign in with a permitted account to load fundraising management.</div>
            </div>
        </div>

        <div id="surveyManagementPanel" class="panel" hidden>
            <div class="section-head">
                <div>
                    <h2>Survey management</h2>
                    <div class="hint">Goshen Experience response totals, response rate, and attendee breakdowns.</div>
                </div>
            </div>
            <div class="management-toolbar">
                <div class="field">
                    <label for="surveyManagementEvent">Retreat edition</label>
                    <select id="surveyManagementEvent" class="input"></select>
                </div>
                <button id="refreshSurveyManagement" class="button ghost" type="button">Refresh</button>
            </div>
            <div id="surveyManagementData" class="detail-list">
                <div class="empty">Sign in with a permitted account to load survey management.</div>
            </div>
        </div>

        <div id="scannerManagementPanel" class="panel" hidden>
            <div class="section-head">
                <div>
                    <h2>Scanner management</h2>
                    <div class="hint">Check-in progress, attendee demographics, and scanner operator access.</div>
                </div>
            </div>
            <div class="management-toolbar">
                <div class="field">
                    <label for="scannerManagementEvent">Retreat edition</label>
                    <select id="scannerManagementEvent" class="input"></select>
                </div>
                <button id="refreshScannerManagement" class="button ghost" type="button">Refresh</button>
            </div>
            <div id="scannerManagementData" class="detail-list">
                <div class="empty">Sign in with a permitted account to load scanner management.</div>
            </div>
        </div>

        <div id="givingPanel" class="panel" hidden>
            <div class="section-head">
                <div>
                    <h2>Giving</h2>
                    <div class="hint">Give securely through Stripe Checkout. Your gift is recorded after Stripe confirms payment.</div>
                </div>
            </div>
            <form id="givingForm" class="inline-form">
                <div id="givingStatus" class="hint">Checking giving setup...</div>
                <div class="two-col">
                    <div class="field">
                        <label for="givingAmount">Amount</label>
                        <input class="input" id="givingAmount" name="amount" type="number" min="1" step="0.01" required>
                    </div>
                    <div class="field">
                        <label for="givingCurrency">Currency</label>
                        <input class="input" id="givingCurrency" name="currency" value="NGN" maxlength="3" required>
                    </div>
                </div>
                <div class="field">
                    <label for="givingCategory">Giving category</label>
                    <select class="input" id="givingCategory" name="donation_category_id" required>
                        <option value="">Loading categories...</option>
                    </select>
                </div>
                <label class="hint"><input id="givingAnonymous" name="anonymous" type="checkbox"> Give anonymously</label>
                <button id="givingSubmit" class="button alt" type="submit">Give with Stripe</button>
            </form>
            <div class="detail-list" id="givingHistory">
                <div class="empty">Sign in to load your giving history.</div>
            </div>
        </div>

        <div class="panel">
            <div class="section-head">
                <div>
                    <h2>Upcoming Retreats</h2>
                    <div class="hint">Pulled directly from the Goshen retreat API.</div>
                </div>
            </div>
            <div id="events" class="grid">
                <div class="empty">Loading retreat editions...</div>
            </div>
        </div>

        <div class="panel">
            <div class="section-head">
                <div>
                    <h2>Church Updates</h2>
                    <div class="hint">Booking and payments remain in the mobile app/admin while this web shell grows.</div>
                </div>
            </div>
            <div class="actions">
                <a class="button" href="/privacy">Privacy</a>
                <a class="button" href="/terms">Terms</a>
                <a class="button" href="/aboutus">Support</a>
            </div>
        </div>
    </section>
</main>
<script>
    const eventsNode = document.getElementById('events');
    const retreatHero = document.getElementById('retreatHero');
    const retreatHeroTitle = document.getElementById('retreatHeroTitle');
    const retreatHeroCopy = document.getElementById('retreatHeroCopy');
    const retreatHeroMeta = document.getElementById('retreatHeroMeta');
    const retreatHeroCountdown = document.getElementById('retreatHeroCountdown');
    const retreatHeroMedia = document.getElementById('retreatHeroMedia');
    const authNotice = document.getElementById('authNotice');
    const signedOut = document.getElementById('signedOut');
    const signedIn = document.getElementById('signedIn');
    const profileAvatar = document.getElementById('profileAvatar');
    const profileName = document.getElementById('profileName');
    const profileEmail = document.getElementById('profileEmail');
    const profileTriumphantId = document.getElementById('profileTriumphantId');
    const profileReadiness = document.getElementById('profileReadiness');
    const memberRetreatPanel = document.getElementById('memberRetreatPanel');
    const memberRetreatData = document.getElementById('memberRetreatData');
    const goshenManagementPanel = document.getElementById('goshenManagementPanel');
    const goshenManagementEvent = document.getElementById('goshenManagementEvent');
    const goshenManagementData = document.getElementById('goshenManagementData');
    const refreshGoshenManagement = document.getElementById('refreshGoshenManagement');
    const retreatSetupPanel = document.getElementById('retreatSetupPanel');
    const retreatSetupEvent = document.getElementById('retreatSetupEvent');
    const retreatSetupData = document.getElementById('retreatSetupData');
    const refreshRetreatSetup = document.getElementById('refreshRetreatSetup');
    const accommodationManagementPanel = document.getElementById('accommodationManagementPanel');
    const accommodationManagementEvent = document.getElementById('accommodationManagementEvent');
    const accommodationManagementData = document.getElementById('accommodationManagementData');
    const refreshAccommodationManagement = document.getElementById('refreshAccommodationManagement');
    const fundraisingManagementPanel = document.getElementById('fundraisingManagementPanel');
    const fundraisingManagementData = document.getElementById('fundraisingManagementData');
    const refreshFundraisingManagement = document.getElementById('refreshFundraisingManagement');
    const surveyManagementPanel = document.getElementById('surveyManagementPanel');
    const surveyManagementEvent = document.getElementById('surveyManagementEvent');
    const surveyManagementData = document.getElementById('surveyManagementData');
    const refreshSurveyManagement = document.getElementById('refreshSurveyManagement');
    const scannerManagementPanel = document.getElementById('scannerManagementPanel');
    const scannerManagementEvent = document.getElementById('scannerManagementEvent');
    const scannerManagementData = document.getElementById('scannerManagementData');
    const refreshScannerManagement = document.getElementById('refreshScannerManagement');
    const givingPanel = document.getElementById('givingPanel');
    const givingForm = document.getElementById('givingForm');
    const givingStatus = document.getElementById('givingStatus');
    const givingCurrency = document.getElementById('givingCurrency');
    const givingCategory = document.getElementById('givingCategory');
    const givingSubmit = document.getElementById('givingSubmit');
    const givingHistory = document.getElementById('givingHistory');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const verifyForm = document.getElementById('verifyForm');
    const registerGroup = document.getElementById('registerGroup');
    const storageKey = 'goshen_member_user';
    let currentUser = null;
    let eventsCache = [];
    let countdownTimer = null;
    let goshenManagementLoadedEvent = '';
    let goshenManagementLoadingEvent = '';
    let goshenManagementRequestToken = 0;
    let retreatSetupSelectedEvent = '';
    let accommodationManagementLoadedEvent = '';
    let accommodationManagementLoadingEvent = '';
    let accommodationManagementRequestToken = 0;
    let fundraisingManagementLoaded = false;
    let fundraisingManagementLoading = false;
    let fundraisingManagementRequestToken = 0;
    let surveyManagementLoadedEvent = '';
    let surveyManagementLoadingEvent = '';
    let surveyManagementRequestToken = 0;
    let scannerManagementLoadedEvent = '';
    let scannerManagementLoadingEvent = '';
    let scannerManagementRequestToken = 0;

    function escapeHtml(value) {
        return `${value ?? ''}`.replace(/[&<>"']/g, (match) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[match]));
    }

    function notice(message, type = 'success') {
        authNotice.textContent = message;
        authNotice.className = `notice show ${type}`;
    }

    function clearNotice() {
        authNotice.className = 'notice';
        authNotice.textContent = '';
    }

    function setBusy(form, busy) {
        form.querySelectorAll('button').forEach((button) => {
            button.disabled = busy;
        });
    }

    function payloadFromForm(form) {
        return Object.fromEntries(new FormData(form).entries());
    }

    function apiPost(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ data }),
        }).then(async (response) => {
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload.status === 'error') {
                throw new Error(payload.message || payload.msg || 'Request failed. Please try again.');
            }
            return payload;
        });
    }

    async function downloadAuthenticatedDocument(url, data, fallbackFilename) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/octet-stream, application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ data }),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.message || payload.msg || 'Document download failed. Please try again.');
        }

        const blob = await response.blob();
        const disposition = response.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);
        const filename = match ? decodeURIComponent(match[1]) : fallbackFilename;
        const objectUrl = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = objectUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
    }

    async function authenticatedBlobUrl(url, data) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/octet-stream, image/*, application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ data }),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.message || payload.msg || 'Secure image loading failed. Please refresh and try again.');
        }

        return URL.createObjectURL(await response.blob());
    }

    function authPayload(extra = {}) {
        return {
            ...extra,
            email: currentUser?.email || '',
            api_token: currentUser?.api_token || '',
        };
    }

    function truthyFlag(value) {
        return value === true || value === 1 || value === '1' || `${value || ''}`.toLowerCase() === 'true';
    }

    function normalizeRole(value) {
        return `${value || ''}`.trim().toLowerCase().replace(/\s+/g, '_').replace(/-/g, '_');
    }

    function currentUserRoles() {
        const rawRoles = currentUser?.roles || currentUser?.role_names || currentUser?.user_roles || currentUser?.role || [];
        const roles = Array.isArray(rawRoles) ? rawRoles : [rawRoles];

        return roles.map((role) => {
            if (role && typeof role === 'object') {
                return normalizeRole(role.name || role.slug || role.title || role.role || role.id);
            }

            return normalizeRole(role);
        }).filter(Boolean);
    }

    function canManageGoshenRegistration() {
        const directFlags = [
            currentUser?.can_manage_goshen_registration,
            currentUser?.canManageGoshenRegistration,
            currentUser?.goshen?.can_manage_registration,
            currentUser?.permissions?.can_manage_goshen_registration,
            currentUser?.permissions?.canManageGoshenRegistration,
        ];

        if (directFlags.some(truthyFlag)) return true;

        const roles = currentUserRoles();
        return [
            'event_manager',
            'eventmanager',
            'goshen_manager',
            'goshenmanager',
            'retreat_manager',
            'retreatmanager',
            'super_admin',
            'superadmin',
            'admin',
        ].some((role) => roles.includes(role));
    }

    function canManageFundraising() {
        const directFlags = [
            currentUser?.can_manage_fundraising,
            currentUser?.canManageFundraising,
            currentUser?.fundraising?.can_manage,
            currentUser?.permissions?.can_manage_fundraising,
            currentUser?.permissions?.canManageFundraising,
        ];

        if (directFlags.some(truthyFlag)) return true;

        const roles = currentUserRoles();
        return [
            'fundraising_manager',
            'fundraisingmanager',
            'event_manager',
            'eventmanager',
            'goshen_manager',
            'goshenmanager',
            'retreat_manager',
            'retreatmanager',
            'super_admin',
            'superadmin',
            'admin',
        ].some((role) => roles.includes(role));
    }

    function canViewSurveyStats() {
        const directFlags = [
            currentUser?.can_view_goshen_experience_stats,
            currentUser?.canViewGoshenExperienceStats,
            currentUser?.permissions?.can_view_goshen_experience_stats,
            currentUser?.permissions?.canViewGoshenExperienceStats,
        ];

        if (directFlags.some(truthyFlag)) return true;

        const roles = currentUserRoles();
        return [
            'event_manager',
            'eventmanager',
            'goshen_manager',
            'goshenmanager',
            'retreat_manager',
            'retreatmanager',
            'super_admin',
            'superadmin',
            'admin',
        ].some((role) => roles.includes(role));
    }

    function canManageScanners() {
        const directFlags = [
            currentUser?.can_manage_scanners,
            currentUser?.canManageScanners,
            currentUser?.can_manage_goshen_scanners,
            currentUser?.canManageGoshenScanners,
            currentUser?.scanner?.manager_allowed,
            currentUser?.permissions?.can_manage_scanners,
            currentUser?.permissions?.canManageScanners,
        ];

        if (directFlags.some(truthyFlag)) return true;

        const roles = currentUserRoles();
        return [
            'event_manager',
            'eventmanager',
            'goshen_manager',
            'goshenmanager',
            'retreat_manager',
            'retreatmanager',
            'super_admin',
            'superadmin',
            'admin',
        ].some((role) => roles.includes(role));
    }

    function saveUser(user) {
        if (!user || !user.api_token) {
            throw new Error('The server did not return a login session.');
        }

        currentUser = user;
        localStorage.setItem(storageKey, JSON.stringify(user));
        renderAuth();
    }

    function renderAuth() {
        if (!currentUser) {
            signedOut.hidden = false;
            signedIn.hidden = true;
            memberRetreatPanel.hidden = true;
            goshenManagementPanel.hidden = true;
            goshenManagementLoadedEvent = '';
            goshenManagementLoadingEvent = '';
            goshenManagementData.innerHTML = '<div class="empty">Sign in with a permitted account to load registration management.</div>';
            retreatSetupPanel.hidden = true;
            retreatSetupSelectedEvent = '';
            retreatSetupData.innerHTML = '<div class="empty">Sign in with a permitted account to load retreat setup.</div>';
            accommodationManagementPanel.hidden = true;
            accommodationManagementLoadedEvent = '';
            accommodationManagementLoadingEvent = '';
            accommodationManagementData.innerHTML = '<div class="empty">Sign in with a permitted account to load accommodation management.</div>';
            fundraisingManagementPanel.hidden = true;
            fundraisingManagementLoaded = false;
            fundraisingManagementLoading = false;
            fundraisingManagementData.innerHTML = '<div class="empty">Sign in with a permitted account to load fundraising management.</div>';
            surveyManagementPanel.hidden = true;
            surveyManagementLoadedEvent = '';
            surveyManagementLoadingEvent = '';
            surveyManagementData.innerHTML = '<div class="empty">Sign in with a permitted account to load survey management.</div>';
            scannerManagementPanel.hidden = true;
            scannerManagementLoadedEvent = '';
            scannerManagementLoadingEvent = '';
            scannerManagementData.innerHTML = '<div class="empty">Sign in with a permitted account to load scanner management.</div>';
            givingPanel.hidden = true;
            renderEvents(eventsCache);
            return;
        }

        const initials = (currentUser.name || currentUser.email || 'G').trim().slice(0, 1).toUpperCase();
        profileAvatar.textContent = initials;
        if (currentUser.avatar) {
            profileAvatar.style.background = `url("${currentUser.avatar}") center/cover`;
            profileAvatar.textContent = '';
        } else {
            profileAvatar.style.background = '#fff';
        }

        profileName.textContent = currentUser.name || 'Member';
        profileEmail.textContent = currentUser.email || 'Signed in';
        profileTriumphantId.textContent = currentUser.triumphant_id
            ? `Triumphant ID: ${currentUser.triumphant_id}`
            : '';
        renderProfileReadiness();
        signedOut.hidden = true;
        signedIn.hidden = false;
        memberRetreatPanel.hidden = false;
        givingPanel.hidden = false;
        renderEvents(eventsCache);
        renderGoshenManagementPanelState();
        renderRetreatSetupPanelState();
        renderAccommodationManagementPanelState();
        renderFundraisingManagementPanelState();
        renderSurveyManagementPanelState();
        renderScannerManagementPanelState();
        loadMemberRetreatData();
        loadGivingStatus();
        loadGivingHistory();
    }

    function renderProfileReadiness() {
        const missing = Array.isArray(currentUser?.profile_missing_fields)
            ? currentUser.profile_missing_fields
            : [];

        if (!missing.length) {
            profileReadiness.hidden = true;
            profileReadiness.textContent = '';
            return;
        }

        profileReadiness.hidden = false;
        profileReadiness.textContent = `Complete your member profile before registering: ${missing.join(', ')}.`;
    }

    function restoreUser() {
        const raw = localStorage.getItem(storageKey);
        if (!raw) return;

        try {
            const saved = JSON.parse(raw);
            if (!saved?.api_token) return;

            apiPost('/api/member/me', { api_token: saved.api_token })
                .then((payload) => {
                    if (!payload.user?.id) throw new Error('Missing member account');
                    saveUser({ ...payload.user, api_token: saved.api_token });
                })
                .catch(() => {
                    currentUser = null;
                    localStorage.removeItem(storageKey);
                    renderAuth();
                    notice('We could not verify your saved session. Please sign in again.', 'error');
                });
        } catch {
            localStorage.removeItem(storageKey);
        }
    }

    document.querySelectorAll('.tab').forEach((tab) => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.tab').forEach((item) => item.classList.remove('active'));
            tab.classList.add('active');
            loginForm.hidden = tab.dataset.tab !== 'login';
            registerForm.hidden = tab.dataset.tab !== 'register';
            verifyForm.hidden = tab.dataset.tab !== 'verify';
            clearNotice();
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

    loginForm.addEventListener('submit', (event) => {
        event.preventDefault();
        clearNotice();
        setBusy(loginForm, true);
        apiPost('/api/loginUser', payloadFromForm(loginForm))
            .then((payload) => {
                saveUser(payload.user);
                notice(`Welcome back, ${payload.user.name || 'member'}.`);
            })
            .catch((error) => notice(error.message, 'error'))
            .finally(() => setBusy(loginForm, false));
    });

    registerForm.addEventListener('submit', (event) => {
        event.preventDefault();
        clearNotice();
        setBusy(registerForm, true);
        apiPost('/api/registerUser', payloadFromForm(registerForm))
            .then((payload) => {
                document.getElementById('verifyEmail').value = registerForm.email.value;
                document.querySelector('[data-tab="verify"]').click();
                notice(payload.message || 'Account created. Enter the verification code sent to your email.');
            })
            .catch((error) => notice(error.message, 'error'))
            .finally(() => setBusy(registerForm, false));
    });

    verifyForm.addEventListener('submit', (event) => {
        event.preventDefault();
        clearNotice();
        setBusy(verifyForm, true);
        apiPost('/api/verifyMobileEmail', payloadFromForm(verifyForm))
            .then((payload) => {
                saveUser(payload.user);
                notice('Your email is verified. Welcome to the Goshen member app.');
            })
            .catch((error) => notice(error.message, 'error'))
            .finally(() => setBusy(verifyForm, false));
    });

    document.getElementById('resendCode').addEventListener('click', () => {
        clearNotice();
        const email = document.getElementById('verifyEmail').value;
        if (!email) {
            notice('Enter your email address first.', 'error');
            return;
        }
        apiPost('/api/resendMobileVerification', { email })
            .then((payload) => notice(payload.message || 'A fresh verification code has been sent.'))
            .catch((error) => notice(error.message, 'error'));
    });

    document.getElementById('logoutButton').addEventListener('click', () => {
        currentUser = null;
        localStorage.removeItem(storageKey);
        renderAuth();
        notice('You have been signed out.');
    });

    document.getElementById('refreshMemberData').addEventListener('click', () => {
        loadMemberRetreatData();
    });

    refreshGoshenManagement.addEventListener('click', () => {
        loadGoshenManagementSummary(true);
    });

    refreshRetreatSetup.addEventListener('click', () => {
        loadEvents(true);
    });

    refreshAccommodationManagement.addEventListener('click', () => {
        loadAccommodationManagement(true);
    });

    refreshFundraisingManagement.addEventListener('click', () => {
        loadFundraisingManagementSummary(true);
    });

    refreshSurveyManagement.addEventListener('click', () => {
        loadSurveyManagementStats(true);
    });

    refreshScannerManagement.addEventListener('click', () => {
        loadScannerManagement(true);
    });

    goshenManagementEvent.addEventListener('change', () => {
        loadGoshenManagementSummary(true);
    });

    retreatSetupEvent.addEventListener('change', () => {
        retreatSetupSelectedEvent = retreatSetupEvent.value || '';
        renderRetreatSetupPanelState();
    });

    accommodationManagementEvent.addEventListener('change', () => {
        loadAccommodationManagement(true);
    });

    goshenManagementData.addEventListener('click', (event) => {
        const button = event.target.closest('.registration-toggle-button');
        if (!button) return;

        const open = button.dataset.open === '1';
        updateManagedRegistrationStatus(open, button);
    });

    accommodationManagementData.addEventListener('click', (event) => {
        const button = event.target.closest('.accommodation-save-button');
        if (!button) return;

        updateAccommodationAllocation(button);
    });

    fundraisingManagementData.addEventListener('click', (event) => {
        const button = event.target.closest('.fundraising-campaign-status-button');
        if (!button) return;

        updateFundraisingCampaignStatus(button);
    });

    surveyManagementEvent.addEventListener('change', () => {
        loadSurveyManagementStats(true);
    });

    surveyManagementData.addEventListener('click', (event) => {
        const button = event.target.closest('.survey-setting-button');
        if (!button) return;

        updateSurveySetting(button);
    });

    scannerManagementEvent.addEventListener('change', () => {
        loadScannerManagement(true);
    });

    scannerManagementData.addEventListener('click', (event) => {
        const button = event.target.closest('.scanner-toggle-button');
        if (!button) return;

        updateScannerOperatorAccess(button);
    });

    function loadGivingStatus() {
        givingStatus.textContent = 'Checking giving setup...';
        givingSubmit.disabled = true;
        givingCategory.innerHTML = '<option value="">Loading categories...</option>';
        fetch('/api/giving/stripe/status', { headers: { 'Accept': 'application/json' } })
            .then((response) => response.json())
            .then((payload) => {
                givingCurrency.value = (payload.currency || 'NGN').toUpperCase();
                const categories = Array.isArray(payload.categories) ? payload.categories : [];
                givingCategory.innerHTML = categories.length
                    ? categories.map((category) => `<option value="${escapeHtml(category.id)}">${escapeHtml(category.name)}</option>`).join('')
                    : '<option value="">No active Giving categories</option>';
                if (!payload.enabled) {
                    givingStatus.textContent = 'Online giving is temporarily disabled.';
                    return;
                }
                if (!payload.configured) {
                    givingStatus.textContent = 'Online giving is enabled, but Stripe Checkout still needs configuration.';
                    return;
                }
                if (!categories.length) {
                    givingStatus.textContent = 'Online giving needs at least one active category before payments can start.';
                    return;
                }
                givingStatus.textContent = 'Stripe Checkout is ready for secure giving.';
                givingSubmit.disabled = false;
            })
            .catch(() => {
                givingStatus.textContent = 'Unable to confirm giving setup right now.';
            });
    }

    function loadGivingHistory() {
        if (!currentUser?.api_token) return;

        givingHistory.innerHTML = '<div class="empty">Loading your giving history...</div>';
        apiPost('/api/giving/stripe/history', authPayload())
            .then((payload) => {
                const donations = Array.isArray(payload.data) ? payload.data : [];
                givingHistory.innerHTML = donations.length
                    ? donations.map(renderGivingHistoryItem).join('')
                    : '<div class="empty">No giving record is linked to this account yet.</div>';
            })
            .catch((error) => {
                givingHistory.innerHTML = `<div class="empty">${escapeHtml(error.message || 'Unable to load your giving history right now.')}</div>`;
            });
    }

    givingForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!currentUser) {
            notice('Please sign in before giving through this web app.', 'error');
            return;
        }

        const data = payloadFromForm(givingForm);
        setBusy(givingForm, true);
        apiPost('/api/giving/stripe/checkout', authPayload({
            amount: data.amount,
            currency: data.currency,
            donation_category_id: data.donation_category_id,
            purpose: givingCategory.options[givingCategory.selectedIndex]?.text || 'Goshen giving',
            anonymous: document.getElementById('givingAnonymous').checked,
            name: currentUser.name || '',
            email: currentUser.email || '',
            phone: currentUser.phone || '',
        }))
            .then((payload) => {
                const checkoutUrl = payload.checkout?.checkout_url;
                if (!checkoutUrl) {
                    throw new Error('Secure checkout is not available yet.');
                }
                window.open(checkoutUrl, '_blank', 'noopener,noreferrer');
                notice('Stripe Checkout opened. Return here after completing your gift.');
                loadGivingHistory();
            })
            .catch((error) => notice(error.message, 'error'))
            .finally(() => setBusy(givingForm, false));
    });

    function eventDate(event) {
        const countdownTarget = retreatCountdownTarget(event);
        const raw = countdownTarget?.date || event.starts_at || event.start_date || event.date || null;
        if (!raw) return 'Date to be announced';
        const parsed = new Date(raw);
        if (Number.isNaN(parsed.getTime())) return escapeHtml(raw);
        return parsed.toLocaleDateString(undefined, {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    }

    function imageValue(value) {
        if (!value) return '';
        if (typeof value === 'string') return value.trim();
        if (typeof value === 'object') {
            return `${value.url || value.src || value.path || ''}`.trim();
        }
        return '';
    }

    function eventImage(event) {
        return [
            event.feature_image_url,
            event.featureImageUrl,
            event.feature_image,
            event.featureImage,
            event.banner_url,
            event.bannerUrl,
            event.image_url,
            event.imageUrl,
            event.cover_photo,
            event.coverPhoto,
            event.thumbnail,
        ].map(imageValue).find(Boolean) || '';
    }

    function plainText(value) {
        return `${value ?? ''}`
            .replace(/<[^>]*>/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function clipText(value, maxLength = 190) {
        const text = plainText(value);
        if (text.length <= maxLength) return text;
        return `${text.slice(0, maxLength - 1).trim()}...`;
    }

    function venueText(event) {
        const parts = [
            event.venue_name || event.venueName || event.venue || event.location,
            event.venue_address || event.venueAddress,
        ].map((part) => `${part || ''}`.trim()).filter(Boolean);

        return parts.length ? parts.join(', ') : 'Venue to be announced';
    }

    function parseDateValue(value) {
        if (!value) return null;
        const parsed = new Date(value);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    function firstDateCandidate(candidates) {
        for (const candidate of candidates) {
            const date = parseDateValue(candidate?.value);
            if (date) {
                return { ...candidate, date };
            }
        }
        return null;
    }

    function retreatCountdownTarget(event) {
        const schedules = Array.isArray(event?.schedules) ? event.schedules : [];
        const firstSchedule = schedules
            .map((schedule) => ({
                value: schedule.starts_at,
                date: parseDateValue(schedule.starts_at),
                label: 'Retreat starts in',
                doneLabel: 'Retreat has started',
            }))
            .filter((schedule) => schedule.date)
            .sort((a, b) => a.date - b.date)[0];

        if (firstSchedule) return firstSchedule;

        return firstDateCandidate([
            {
                value: event?.starts_at || event?.start_date || event?.date,
                label: 'Retreat starts in',
                doneLabel: 'Retreat has started',
            },
            {
                value: event?.sales_start_at || event?.registration?.sales_start_at,
                label: 'Registration opens in',
                doneLabel: 'Registration is open',
            },
            {
                value: event?.sales_end_at || event?.registration?.sales_end_at,
                label: 'Registration closes in',
                doneLabel: 'Registration has closed',
            },
        ]);
    }

    function countdownMarkup(target, className = '') {
        if (!target) {
            return `
                <div class="countdown ${className}">
                    <div class="countdown-label">Retreat countdown</div>
                    <div class="countdown-grid">
                        <span><strong>--</strong><small>Days</small></span>
                        <span><strong>--</strong><small>Hours</small></span>
                        <span><strong>--</strong><small>Mins</small></span>
                    </div>
                    <div class="countdown-note">Dates will appear when this retreat edition is updated.</div>
                </div>
            `;
        }

        const note = `Target: ${formatDateTime(target.date)}`;

        return `
            <div class="countdown ${className}"
                data-countdown-target="${escapeHtml(target.date.toISOString())}"
                data-countdown-label="${escapeHtml(target.label)}"
                data-countdown-done-label="${escapeHtml(target.doneLabel)}"
                data-countdown-note="${escapeHtml(note)}">
                <div class="countdown-label">${escapeHtml(target.label)}</div>
                <div class="countdown-grid">
                    <span><strong data-countdown-value="days">--</strong><small>Days</small></span>
                    <span><strong data-countdown-value="hours">--</strong><small>Hours</small></span>
                    <span><strong data-countdown-value="minutes">--</strong><small>Mins</small></span>
                </div>
                <div class="countdown-note">${escapeHtml(note)}</div>
            </div>
        `;
    }

    function applyHeroCountdown(target) {
        retreatHeroCountdown.className = 'countdown hero-countdown';
        ['data-countdown-target', 'data-countdown-label', 'data-countdown-done-label', 'data-countdown-note']
            .forEach((attribute) => retreatHeroCountdown.removeAttribute(attribute));

        if (!target) {
            retreatHeroCountdown.innerHTML = `
                <div class="countdown-label">Retreat countdown</div>
                <div class="countdown-grid">
                    <span><strong>--</strong><small>Days</small></span>
                    <span><strong>--</strong><small>Hours</small></span>
                    <span><strong>--</strong><small>Mins</small></span>
                </div>
                <div class="countdown-note">Dates will appear when a retreat edition is published.</div>
            `;
            return;
        }

        const note = `Target: ${formatDateTime(target.date)}`;
        retreatHeroCountdown.dataset.countdownTarget = target.date.toISOString();
        retreatHeroCountdown.dataset.countdownLabel = target.label;
        retreatHeroCountdown.dataset.countdownDoneLabel = target.doneLabel;
        retreatHeroCountdown.dataset.countdownNote = note;
        retreatHeroCountdown.innerHTML = `
            <div class="countdown-label">${escapeHtml(target.label)}</div>
            <div class="countdown-grid">
                <span><strong data-countdown-value="days">--</strong><small>Days</small></span>
                <span><strong data-countdown-value="hours">--</strong><small>Hours</small></span>
                <span><strong data-countdown-value="minutes">--</strong><small>Mins</small></span>
            </div>
            <div class="countdown-note">${escapeHtml(note)}</div>
        `;
    }

    function updateCountdowns() {
        document.querySelectorAll('[data-countdown-target]').forEach((node) => {
            const target = parseDateValue(node.dataset.countdownTarget);
            if (!target) return;

            const diff = target.getTime() - Date.now();
            const remaining = Math.max(0, diff);
            const days = Math.floor(remaining / 86400000);
            const hours = Math.floor((remaining % 86400000) / 3600000);
            const minutes = Math.floor((remaining % 3600000) / 60000);
            const label = node.querySelector('.countdown-label');
            const note = node.querySelector('.countdown-note');
            const values = {
                days,
                hours,
                minutes,
            };

            if (label) {
                label.textContent = diff > 0
                    ? (node.dataset.countdownLabel || 'Starts in')
                    : (node.dataset.countdownDoneLabel || 'Started');
            }

            Object.entries(values).forEach(([key, value]) => {
                const valueNode = node.querySelector(`[data-countdown-value="${key}"]`);
                if (valueNode) valueNode.textContent = `${value}`;
            });

            if (note) {
                note.textContent = node.dataset.countdownNote || `Target: ${formatDateTime(target)}`;
            }
        });
    }

    function startCountdowns() {
        updateCountdowns();
        if (!countdownTimer) {
            countdownTimer = window.setInterval(updateCountdowns, 60000);
        }
    }

    function renderFeatureHero(event) {
        if (!event) {
            retreatHero.classList.remove('has-feature-image');
            retreatHeroTitle.textContent = 'Goshen Retreat';
            retreatHeroCopy.textContent = 'Register, verify your account, and view published retreat editions, schedules, ticket plans, and church updates from a lightweight installable web experience.';
            retreatHeroMeta.textContent = 'Published retreat editions load directly from the Goshen Retreat API.';
            applyHeroCountdown(null);
            retreatHeroMedia.innerHTML = `
                <div class="hero-media-fallback">
                    <img src="/favicon.png" alt="">
                    <strong>Goshen Retreat</strong>
                    <span>Feature banner will appear here when published.</span>
                </div>
            `;
            return;
        }

        const title = plainText(event.title || event.name || 'Goshen Retreat');
        const description = clipText(event.description) || 'Register, review the schedule, and prepare for the next Goshen Retreat edition.';
        const venue = venueText(event);
        const target = retreatCountdownTarget(event);
        const image = eventImage(event);

        retreatHero.classList.toggle('has-feature-image', Boolean(image));
        retreatHeroTitle.textContent = title;
        retreatHeroCopy.textContent = description;
        retreatHeroMeta.textContent = `${eventDate(event)} · ${venue}`;
        applyHeroCountdown(target);
        retreatHeroMedia.innerHTML = image
            ? `
                <img src="${escapeHtml(image)}" alt="${escapeHtml(title)} feature image" loading="eager">
                <div class="hero-media-caption">
                    <strong>${escapeHtml(title)}</strong>
                    <span>${escapeHtml(venue)}</span>
                </div>
            `
            : `
                <div class="hero-media-fallback">
                    <img src="/favicon.png" alt="">
                    <strong>${escapeHtml(title)}</strong>
                    <span>${escapeHtml(venue)}</span>
                </div>
            `;

        startCountdowns();
    }

    function youtubeIdFromText(value, allowRawId = false) {
        const raw = `${value || ''}`.trim();
        if (!raw) return '';
        if (allowRawId && /^[A-Za-z0-9_-]{11}$/.test(raw)) return raw;

        const withProtocol = /^[a-z][a-z0-9+.-]*:\/\//i.test(raw) ? raw : `https://${raw}`;

        try {
            const url = new URL(withProtocol);
            const host = url.hostname.toLowerCase().replace(/^www\./, '').replace(/^m\./, '');
            const isYoutube = host === 'youtu.be'
                || host === 'youtube.com'
                || host.endsWith('.youtube.com')
                || host === 'youtube-nocookie.com'
                || host.endsWith('.youtube-nocookie.com');

            if (!isYoutube) return '';

            if (host === 'youtu.be') {
                return `${url.pathname.split('/').filter(Boolean)[0] || ''}`.trim();
            }

            const watchId = url.searchParams.get('v');
            if (watchId) return watchId.trim();

            const parts = url.pathname.split('/').filter(Boolean);
            const markerIndex = parts.findIndex((part) => ['embed', 'shorts', 'live', 'v'].includes(part));
            return markerIndex >= 0 ? `${parts[markerIndex + 1] || ''}`.trim() : '';
        } catch (error) {
            return '';
        }
    }

    function normalizeYoutubeVideo(video) {
        const item = typeof video === 'string' ? { youtube_url: video } : (video || {});
        const youtube = item.youtube && typeof item.youtube === 'object' ? item.youtube : {};
        const explicitId = item.youtube_id || item.youtubeId || item.youtube_video_id || item.youtubeVideoId || youtube.id || youtube.video_id || youtube.videoId;
        const youtubeUrl = item.youtube_url || item.youtubeUrl || item.youtube_link || item.youtubeLink || youtube.url || youtube.link || youtube.watch_url || youtube.watchUrl;
        const fallbackUrl = item.url || item.link || item.video_url || item.videoUrl || item.embed_url || item.embedUrl || item.watch_url || item.watchUrl;
        const id = youtubeIdFromText(explicitId, true)
            || youtubeIdFromText(youtubeUrl, true)
            || youtubeIdFromText(fallbackUrl, false);

        if (!/^[A-Za-z0-9_-]{11}$/.test(id)) return null;

        return {
            id,
            title: plainText(item.title || item.name || item.caption || 'Goshen Retreat video'),
            description: clipText(item.description || item.summary || '', 90),
            date: item.published_at || item.publishedAt || item.recorded_at || item.recordedAt || item.created_at || item.createdAt || '',
            href: `https://www.youtube.com/watch?v=${encodeURIComponent(id)}`,
            embed: `https://www.youtube-nocookie.com/embed/${encodeURIComponent(id)}`,
        };
    }

    function pastVideos(event) {
        const rawVideos = [
            event.past_videos,
            event.pastVideos,
            event.youtube_videos,
            event.youtubeVideos,
            event.videos,
        ].find(Array.isArray) || [];
        const seen = new Set();

        return rawVideos
            .map(normalizeYoutubeVideo)
            .filter((video) => {
                if (!video || seen.has(video.id)) return false;
                seen.add(video.id);
                return true;
            });
    }

    function renderPastVideos(event) {
        const videos = pastVideos(event);
        if (!videos.length) return '';

        return `
            <section class="past-videos" aria-label="Past Goshen videos">
                <div class="video-section-title">
                    <strong>Past Goshen videos</strong>
                    <span>${videos.length} YouTube ${videos.length === 1 ? 'video' : 'videos'}</span>
                </div>
                <div class="video-rail">
                    ${videos.map((video, index) => `
                        <article class="video-card">
                            <div class="video-frame">
                                <iframe
                                    src="${escapeHtml(video.embed)}"
                                    title="${escapeHtml(video.title)}"
                                    loading="lazy"
                                    referrerpolicy="strict-origin-when-cross-origin"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                    allowfullscreen></iframe>
                            </div>
                            <div class="video-body">
                                <strong>${escapeHtml(video.title || `Goshen Retreat video ${index + 1}`)}</strong>
                                ${video.description ? `<span>${escapeHtml(video.description)}</span>` : ''}
                                ${video.date ? `<span>${escapeHtml(formatDateTime(video.date))}</span>` : ''}
                                <a class="button ghost" href="${escapeHtml(video.href)}" target="_blank" rel="noopener noreferrer">Watch on YouTube</a>
                            </div>
                        </article>
                    `).join('')}
                </div>
            </section>
        `;
    }

    function formatMoney(value, currency) {
        const amount = Number(value ?? 0);
        if (!Number.isFinite(amount)) return `${currency || ''} ${value || ''}`.trim();
        return `${currency || ''} ${amount.toLocaleString(undefined, {
            minimumFractionDigits: amount % 1 === 0 ? 0 : 2,
            maximumFractionDigits: 2,
        })}`.trim();
    }

    function formatDateTime(value) {
        if (!value) return 'Time to be announced';
        const parsed = new Date(value);
        if (Number.isNaN(parsed.getTime())) return escapeHtml(value);
        return parsed.toLocaleString(undefined, {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    }

    function renderSchedules(event) {
        const schedules = Array.isArray(event.schedules) ? event.schedules : [];
        if (!schedules.length) {
            return '<div class="mini-row"><strong>Schedule</strong><span>Programme schedule will be published soon.</span></div>';
        }

        return schedules.map((schedule) => `
            <div class="mini-row">
                <strong>${escapeHtml(schedule.title || `Day ${schedule.day_number || ''}`)}</strong>
                <span>${formatDateTime(schedule.starts_at)} - ${formatDateTime(schedule.ends_at)}</span>
                ${schedule.capacity ? `<span>Capacity: ${escapeHtml(schedule.capacity)}</span>` : ''}
            </div>
        `).join('');
    }

    function renderTickets(event) {
        const tickets = Array.isArray(event.ticket_types) ? event.ticket_types : [];
        if (!tickets.length) {
            return '<div class="mini-row"><strong>Tickets</strong><span>Ticket types are not available yet.</span></div>';
        }

        return tickets.map((ticket) => `
            <div class="mini-row">
                <strong>${escapeHtml(ticket.name || 'Ticket')}</strong>
                <span>${formatMoney(ticket.price, ticket.currency)}${ticket.capacity ? ` · Capacity ${escapeHtml(ticket.capacity)}` : ''}</span>
                <span>Per booking: ${escapeHtml(ticket.min_per_booking || 1)} - ${escapeHtml(ticket.max_per_booking || 'unlimited')}</span>
            </div>
        `).join('');
    }

    function renderEvents(events) {
        eventsCache = events;
        if (!events.length) {
            renderFeatureHero(null);
            eventsNode.innerHTML = '<div class="empty">No published retreat edition is available yet.</div>';
            renderGoshenManagementPanelState();
            renderRetreatSetupPanelState();
            renderAccommodationManagementPanelState();
            renderSurveyManagementPanelState();
            renderScannerManagementPanelState();
            return;
        }

        renderFeatureHero(events[0]);
        eventsNode.innerHTML = events.map((event) => {
            const image = eventImage(event);
            const imageHtml = image ? `<img src="${escapeHtml(image)}" alt="">` : '';
            const title = escapeHtml(event.title || event.name || 'Goshen Retreat');
            const venue = escapeHtml(venueText(event));
            return `
                <article class="event-card">
                    <div class="event-media">${imageHtml}</div>
                    <div class="event-body">
                        <span class="pill">${eventDate(event)}</span>
                        <div class="event-title">${title}</div>
                        <div class="event-meta">${venue}</div>
                        ${countdownMarkup(retreatCountdownTarget(event), 'event-countdown')}
                        <details>
                            <summary>View retreat details</summary>
                            <div class="detail-list">
                                ${renderSchedules(event)}
                                ${renderTickets(event)}
                            </div>
                        </details>
                        ${renderPastVideos(event)}
                        ${renderBookingForm(event)}
                    </div>
                </article>
            `;
        }).join('');
        startCountdowns();
        renderGoshenManagementPanelState();
        renderRetreatSetupPanelState();
        renderAccommodationManagementPanelState();
        renderSurveyManagementPanelState();
        renderScannerManagementPanelState();
    }

    function renderBookingForm(event) {
        const tickets = Array.isArray(event.ticket_types) ? event.ticket_types : [];

        if (!currentUser) {
            return '<div class="inline-form"><strong>Ready to register?</strong><span class="hint">Sign in or create a verified member account above to start your Goshen Retreat registration.</span></div>';
        }

        if (!tickets.length) {
            return '<div class="inline-form"><strong>Registration</strong><span class="hint">Ticket types are not available yet for this retreat edition.</span></div>';
        }

        const ticketOptions = tickets.map((ticket) => (
            `<option value="${escapeHtml(ticket.public_id)}">${escapeHtml(ticket.name)} - ${formatMoney(ticket.price, ticket.currency)}</option>`
        )).join('');

        return `
            <form class="inline-form booking-form" data-event-id="${escapeHtml(event.public_id)}">
                <strong>Start registration</strong>
                <div class="two-col">
                    <div class="field">
                        <label>Ticket type</label>
                        <select class="input" name="ticket_type_id" required>${ticketOptions}</select>
                    </div>
                    <div class="field">
                        <label>Attendees</label>
                        <input class="input" name="quantity" type="number" min="1" max="20" value="1" required>
                    </div>
                </div>
                <div class="attendee-fields">${renderAttendeeFields(1, event)}</div>
                <label class="consent-card">
                    <input type="checkbox" name="uk_privacy_consent" value="1" required>
                    <span>
                        <strong>Privacy consent</strong>
                        I agree that MFM Triumphant Church may process my registration, attendee, payment, ticket, and travel-support information for Goshen Retreat administration in line with UK data protection requirements.
                    </span>
                </label>
                <button class="button alt" type="submit">Register for this retreat</button>
                <span class="hint">Add each person attending so every attendee receives a distinct retreat record and ticket.</span>
            </form>
        `;
    }

    function splitName(name) {
        const parts = `${name || ''}`.trim().split(/\s+/).filter(Boolean);
        return {
            first: parts[0] || '',
            last: parts.slice(1).join(' '),
        };
    }

    function registrationFieldsFor(event) {
        const fields = Array.isArray(event?.registration_form?.attendee_fields)
            ? event.registration_form.attendee_fields
            : (Array.isArray(event?.attendee_fields) ? event.attendee_fields : []);

        if (fields.length) {
            return fields
                .filter((field) => field && field.key && field.label)
                .sort((a, b) => Number(a.sort_order || 0) - Number(b.sort_order || 0));
        }

        return [
            { key: 'company', label: 'Company', type: 'text', is_required: false, options: [], sort_order: 10 },
            { key: 'designation', label: 'Designation', type: 'select', is_required: true, options: [
                { label: 'Please Select', value: '' },
                { label: 'Member', value: 'member' },
                { label: 'Worker', value: 'worker' },
                { label: 'Minister', value: 'minister' },
                { label: 'Pastor', value: 'pastor' },
                { label: 'Guest', value: 'guest' },
                { label: 'Other', value: 'other' },
            ], sort_order: 20 },
            { key: 'gender', label: 'Gender', type: 'select', is_required: true, options: [
                { label: 'Please Select', value: '' },
                { label: 'Male', value: 'male' },
                { label: 'Female', value: 'female' },
            ], sort_order: 30 },
            { key: 'age_group', label: 'Age group', type: 'select', is_required: true, options: [
                { label: 'Please Select', value: '' },
                { label: 'Child', value: 'child' },
                { label: 'Teen', value: 'teen' },
                { label: 'Young adult', value: 'young_adult' },
                { label: 'Adult', value: 'adult' },
                { label: 'Senior', value: 'senior' },
            ], sort_order: 40 },
            { key: 'free_church_bus_interest', label: 'Interested in joining FREE church bus', type: 'select', is_required: true, options: [
                { label: 'Please Select', value: '' },
                { label: 'Yes', value: 'yes' },
                { label: 'No thanks', value: 'no_thanks' },
            ], sort_order: 50 },
            { key: 'volunteer_department', label: 'What department would you like to volunteer in?', type: 'select', is_required: true, options: [
                { label: 'Please Select', value: '' },
                { label: 'Children department', value: 'children_department' },
                { label: 'Intercessory', value: 'intercessory' },
                { label: 'Media', value: 'media' },
                { label: 'Protocol', value: 'protocol' },
                { label: 'Sanctuary', value: 'sanctuary' },
                { label: 'No Chance at the moment', value: 'no_chance_at_the_moment' },
            ], sort_order: 60 },
        ];
    }

    function renderAttendeeFields(quantity, event) {
        const total = Math.max(1, Math.min(20, Number(quantity || 1)));
        const currentName = splitName(currentUser?.name || '');
        const registrationFields = registrationFieldsFor(event);
        const cards = [];

        for (let index = 0; index < total; index += 1) {
            const first = index === 0 ? currentName.first : '';
            const last = index === 0 ? currentName.last : '';
            const email = index === 0 ? currentUser?.email || '' : '';
            const phone = index === 0 ? currentUser?.phone || '' : '';
            cards.push(`
                <div class="attendee-card" data-attendee-index="${index}">
                    <strong>Attendee ${index + 1}${index === 0 ? ' - account holder' : ''}</strong>
                    <div class="two-col">
                        <div class="field">
                            <label>First name</label>
                            <input class="input attendee-first-name" value="${escapeHtml(first)}" required>
                        </div>
                        <div class="field">
                            <label>Last name</label>
                            <input class="input attendee-last-name" value="${escapeHtml(last)}">
                        </div>
                        <div class="field">
                            <label>Email</label>
                            <input class="input attendee-email" type="email" value="${escapeHtml(email)}">
                        </div>
                        <div class="field">
                            <label>Phone</label>
                            <input class="input attendee-phone" type="tel" value="${escapeHtml(phone)}">
                        </div>
                        ${registrationFields.map((field) => renderRegistrationField(field, index)).join('')}
                    </div>
                </div>
            `);
        }

        return cards.join('');
    }

    function renderRegistrationField(field, attendeeIndex) {
        const key = `${field.key || ''}`.trim();
        const label = escapeHtml(field.label || key);
        const type = `${field.type || 'text'}`.toLowerCase();
        const required = field.is_required ? 'required' : '';
        const options = Array.isArray(field.options) ? field.options : [];

        if (type === 'textarea') {
            return `
                <div class="field">
                    <label>${label}</label>
                    <textarea class="input attendee-dynamic-control" data-field-key="${escapeHtml(key)}" ${required}></textarea>
                </div>
            `;
        }

        if (['select', 'single_select'].includes(type)) {
            return `
                <div class="field">
                    <label>${label}</label>
                    <select class="input attendee-dynamic-control" data-field-key="${escapeHtml(key)}" ${required}>
                        ${options.map((option) => `<option value="${escapeHtml(option.value ?? '')}">${escapeHtml(option.label || option.value || 'Option')}</option>`).join('')}
                    </select>
                </div>
            `;
        }

        if (['image_select', 'color_select'].includes(type)) {
            const radioName = `attendee_${attendeeIndex}_${key}`;
            const choices = options
                .filter((option) => `${option.value ?? ''}` !== '')
                .map((option) => {
                    const value = escapeHtml(option.value ?? '');
                    const image = option.image_url ? `<img src="${escapeHtml(option.image_url)}" alt="">` : '';
                    const swatch = option.color_hex ? `<span style="width:22px;height:22px;border-radius:999px;border:1px solid #d6e0e6;background:${escapeHtml(option.color_hex)};display:inline-block"></span>` : '';

                    return `
                        <label style="display:flex;align-items:center;gap:8px;border:1px solid #d6e0e6;border-radius:12px;padding:8px;min-height:48px">
                            <input class="attendee-dynamic-control" data-field-key="${escapeHtml(key)}" type="radio" name="${escapeHtml(radioName)}" value="${value}" ${required}>
                            ${image ? `<span style="width:56px;height:44px;border-radius:8px;overflow:hidden;display:inline-flex">${image}</span>` : swatch}
                            <span>${escapeHtml(option.label || option.value || 'Option')}</span>
                        </label>
                    `;
                })
                .join('');

            return `
                <div class="field">
                    <label>${label}</label>
                    <div style="display:grid;gap:8px">${choices}</div>
                </div>
            `;
        }

        return `
            <div class="field">
                <label>${label}</label>
                <input class="input attendee-dynamic-control" data-field-key="${escapeHtml(key)}" value="" ${required}>
            </div>
        `;
    }

    eventsNode.addEventListener('input', (event) => {
        const quantityInput = event.target.closest('.booking-form input[name="quantity"]');
        if (!quantityInput) return;

        const form = quantityInput.closest('.booking-form');
        const attendeeFields = form.querySelector('.attendee-fields');
        const eventModel = eventsCache.find((item) => item.public_id === form.dataset.eventId) || null;
        attendeeFields.innerHTML = renderAttendeeFields(quantityInput.value, eventModel);
    });

    eventsNode.addEventListener('submit', (event) => {
        const form = event.target.closest('.booking-form');
        if (!form) return;

        event.preventDefault();
        if (!currentUser) {
            notice('Please sign in before registering for Goshen Retreat.', 'error');
            return;
        }
        if (!form.reportValidity()) {
            return;
        }

        const values = payloadFromForm(form);
        const attendeeCards = [...form.querySelectorAll('.attendee-card')];
        const attendees = attendeeCards.map((card) => {
            const dynamicFields = collectAttendeeDynamicFields(card);

            return {
                first_name: card.querySelector('.attendee-first-name')?.value || '',
                last_name: card.querySelector('.attendee-last-name')?.value || '',
                email: card.querySelector('.attendee-email')?.value || currentUser.email || '',
                phone: card.querySelector('.attendee-phone')?.value || currentUser.phone || '',
                ...dynamicFields,
                custom_fields: dynamicFields,
            };
        });
        const privacyConsent = form.querySelector('input[name="uk_privacy_consent"]')?.checked === true;

        const payload = authPayload({
            event_id: form.dataset.eventId,
            ticket_type_id: values.ticket_type_id,
            payment_mode: 'outright',
            quantity: Number(values.quantity || 1),
            free_church_bus_consent: attendees.some((attendee) => attendee.free_church_bus_interest === 'yes'),
            uk_privacy_consent: privacyConsent,
            privacy_policy_version: 'uk-gdpr-2026-06',
            attendees,
        });

        setBusy(form, true);
        apiPost('/api/goshen-retreat/bookings', payload)
            .then((response) => {
                notice(response.message || 'Your Goshen Retreat registration has been started.');
                loadMemberRetreatData();
            })
            .catch((error) => notice(error.message, 'error'))
            .finally(() => setBusy(form, false));
    });

    function collectAttendeeDynamicFields(card) {
        const fields = {};
        card.querySelectorAll('.attendee-dynamic-control[data-field-key]').forEach((input) => {
            const key = input.dataset.fieldKey || '';
            if (!key) return;

            if (input.type === 'radio') {
                if (input.checked) {
                    fields[key] = input.value || '';
                }
                return;
            }

            fields[key] = input.value || '';
        });

        return fields;
    }

    function renderMoney(amount, currency) {
        return formatMoney(amount, currency || 'NGN');
    }

    function statusLabel(value) {
        return `${value || 'pending'}`.replace(/_/g, ' ');
    }

    function loadMemberRetreatData() {
        if (!currentUser?.api_token) return;

        memberRetreatPanel.hidden = false;
        memberRetreatData.innerHTML = '<div class="empty">Loading your retreat records...</div>';
        apiPost('/api/goshen-retreat/me', authPayload())
            .then((payload) => {
                const data = payload.data || {};
                const registrations = Array.isArray(data.registrations) ? data.registrations : [];
                const paymentHistory = Array.isArray(data.payment_history) ? data.payment_history : [];
                const tickets = Array.isArray(data.tickets) ? data.tickets : [];
                const allocations = Array.isArray(data.accommodation_allocations) ? data.accommodation_allocations : [];
                if (data.user) {
                    currentUser = { ...currentUser, ...data.user };
                    localStorage.setItem(storageKey, JSON.stringify(currentUser));
                    renderProfileReadiness();
                    renderGoshenManagementPanelState();
                    renderRetreatSetupPanelState();
                    renderAccommodationManagementPanelState();
                    renderFundraisingManagementPanelState();
                    renderSurveyManagementPanelState();
                    renderScannerManagementPanelState();
                }

                if (!registrations.length && !paymentHistory.length && !tickets.length && !allocations.length) {
                    memberRetreatData.innerHTML = '<div class="empty">You do not have a Goshen Retreat registration yet.</div>';
                    return;
                }

                memberRetreatData.innerHTML = [
                    ...registrations.map(renderRegistration),
                    ...paymentHistory.map(renderPaymentTransaction),
                    ...tickets.map(renderTicket),
                    ...allocations.map(renderAllocation),
                ].join('');
                loadTicketQrImages();
            })
            .catch((error) => {
                memberRetreatData.innerHTML = `<div class="empty">${escapeHtml(error.message || 'Unable to load your retreat records right now.')}</div>`;
            });
    }

    function renderGoshenManagementPanelState() {
        if (!currentUser || !canManageGoshenRegistration()) {
            goshenManagementPanel.hidden = true;
            return;
        }

        goshenManagementPanel.hidden = false;
        renderGoshenManagementEventOptions();

        if (!eventsCache.length) {
            goshenManagementData.innerHTML = '<div class="empty">Retreat editions are still loading.</div>';
            return;
        }

        loadGoshenManagementSummary(false);
    }

    function renderGoshenManagementEventOptions() {
        const events = Array.isArray(eventsCache) ? eventsCache : [];
        if (!events.length) {
            goshenManagementEvent.innerHTML = '<option value="">No retreat edition available</option>';
            goshenManagementEvent.disabled = true;
            refreshGoshenManagement.disabled = true;
            return;
        }

        const previous = goshenManagementEvent.value || goshenManagementLoadedEvent;
        goshenManagementEvent.disabled = false;
        refreshGoshenManagement.disabled = false;
        goshenManagementEvent.innerHTML = events.map((event) => {
            const id = event.public_id || event.id || '';
            const name = event.title || event.name || 'Goshen Retreat';
            return `<option value="${escapeHtml(id)}">${escapeHtml(name)}</option>`;
        }).join('');

        const hasPrevious = events.some((event) => `${event.public_id || event.id || ''}` === `${previous || ''}`);
        goshenManagementEvent.value = hasPrevious ? previous : `${events[0].public_id || events[0].id || ''}`;
    }

    function loadGoshenManagementSummary(force = false) {
        if (!currentUser?.api_token || !canManageGoshenRegistration()) return;

        const eventId = goshenManagementEvent.value || eventsCache[0]?.public_id || eventsCache[0]?.id || '';
        if (!eventId) {
            goshenManagementData.innerHTML = '<div class="empty">No retreat edition is available for management yet.</div>';
            return;
        }

        if (!force && (goshenManagementLoadedEvent === eventId || goshenManagementLoadingEvent === eventId)) return;

        const token = ++goshenManagementRequestToken;
        goshenManagementLoadingEvent = eventId;
        goshenManagementData.innerHTML = '<div class="empty">Loading registration management summary...</div>';
        refreshGoshenManagement.disabled = true;

        apiPost(`/api/goshen-retreat/events/${encodeURIComponent(eventId)}/management-summary`, authPayload())
            .then((payload) => {
                if (token !== goshenManagementRequestToken) return;

                goshenManagementLoadedEvent = eventId;
                goshenManagementData.innerHTML = renderGoshenManagementSummary(payload.data || {});
            })
            .catch((error) => {
                if (token !== goshenManagementRequestToken) return;

                goshenManagementLoadedEvent = '';
                goshenManagementData.innerHTML = `<div class="empty">${escapeHtml(error.message || 'Unable to load registration management right now.')}</div>`;
            })
            .finally(() => {
                if (token !== goshenManagementRequestToken) return;

                goshenManagementLoadingEvent = '';
                refreshGoshenManagement.disabled = false;
            });
    }

    function updateManagedRegistrationStatus(open, button) {
        const eventId = goshenManagementEvent.value || goshenManagementLoadedEvent || eventsCache[0]?.public_id || eventsCache[0]?.id || '';
        if (!eventId) {
            notice('Select a retreat edition before updating registration.', 'error');
            return;
        }

        let reason = '';
        if (!open) {
            reason = window.prompt(
                'Reason shown to members when registration is closed',
                'Registration has been closed by the event manager.'
            );
            if (reason === null) return;
            reason = reason.trim() || 'Registration has been closed by the event manager.';
        } else if (!window.confirm('Reopen registration for this retreat edition?')) {
            return;
        }

        const originalLabel = button.textContent;
        button.disabled = true;
        button.textContent = 'Updating...';
        apiPost(`/api/goshen-retreat/events/${encodeURIComponent(eventId)}/registration-status`, authPayload({
            registration_open: open,
            ...(reason ? { reason } : {}),
        }))
            .then((payload) => {
                notice(payload.message || (open ? 'Registration reopened.' : 'Registration closed.'));
                goshenManagementLoadedEvent = '';
                loadGoshenManagementSummary(true);
            })
            .catch((error) => notice(error.message, 'error'))
            .finally(() => {
                button.disabled = false;
                button.textContent = originalLabel;
            });
    }

    function renderRetreatSetupPanelState() {
        if (!currentUser || !canManageGoshenRegistration()) {
            retreatSetupPanel.hidden = true;
            return;
        }

        retreatSetupPanel.hidden = false;
        renderRetreatSetupEventOptions();

        if (!eventsCache.length) {
            retreatSetupData.innerHTML = '<div class="empty">Retreat editions are still loading.</div>';
            return;
        }

        const event = selectedRetreatSetupEvent();
        retreatSetupData.innerHTML = event
            ? renderRetreatSetupSummary(event)
            : '<div class="empty">Select a retreat edition to view setup.</div>';
    }

    function renderRetreatSetupEventOptions() {
        const events = Array.isArray(eventsCache) ? eventsCache : [];
        if (!events.length) {
            retreatSetupEvent.innerHTML = '<option value="">No retreat edition available</option>';
            retreatSetupEvent.disabled = true;
            refreshRetreatSetup.disabled = true;
            return;
        }

        const previous = retreatSetupEvent.value || retreatSetupSelectedEvent;
        retreatSetupEvent.disabled = false;
        refreshRetreatSetup.disabled = false;
        retreatSetupEvent.innerHTML = events.map((event) => {
            const id = event.public_id || event.id || '';
            const name = event.title || event.name || 'Goshen Retreat';
            return `<option value="${escapeHtml(id)}">${escapeHtml(name)}</option>`;
        }).join('');

        const hasPrevious = events.some((event) => `${event.public_id || event.id || ''}` === `${previous || ''}`);
        retreatSetupEvent.value = hasPrevious ? previous : `${events[0].public_id || events[0].id || ''}`;
        retreatSetupSelectedEvent = retreatSetupEvent.value || '';
    }

    function selectedRetreatSetupEvent() {
        const selected = retreatSetupEvent.value || retreatSetupSelectedEvent || eventsCache[0]?.public_id || eventsCache[0]?.id || '';
        return (Array.isArray(eventsCache) ? eventsCache : []).find((event) => `${event.public_id || event.id || ''}` === `${selected}`) || null;
    }

    function renderRetreatSetupSummary(event) {
        const registration = event.registration || {};
        const schedules = Array.isArray(event.schedules) ? event.schedules : [];
        const tickets = Array.isArray(event.ticket_types) ? event.ticket_types : [];
        const discount = event.pay_in_full_discount || {};
        const registrationOpen = truthyFlag(registration.open ?? event.registration_open);
        const discountActive = truthyFlag(discount.enabled) && truthyFlag(discount.active) && Number(discount.value || 0) > 0;
        const salesWindow = [
            event.sales_start_at ? `Opens ${formatDateTime(event.sales_start_at)}` : '',
            event.sales_end_at ? `Closes ${formatDateTime(event.sales_end_at)}` : '',
        ].filter(Boolean).join(' · ') || 'Sales window not configured';

        return [
            `<div class="mini-row"><strong>${escapeHtml(event.name || 'Goshen Retreat')}</strong><span>${escapeHtml(registrationOpen ? 'Registration open' : 'Registration closed')} · ${escapeHtml(registration.override || 'auto')}</span></div>`,
            event.description ? `<div class="mini-row"><strong>Description</strong><span>${escapeHtml(event.description)}</span></div>` : '',
            `<div class="management-stats">
                <div class="stat-card"><span>Schedules</span><strong>${escapeHtml(schedules.length)}</strong><small>${escapeHtml(eventDate(event))}</small></div>
                <div class="stat-card"><span>Ticket types</span><strong>${escapeHtml(tickets.length)}</strong><small>${escapeHtml(tickets[0] ? formatMoney(tickets[0].price, tickets[0].currency) : 'No active ticket')}</small></div>
                <div class="stat-card"><span>Sales window</span><strong>${escapeHtml(registrationOpen ? 'Open' : 'Closed')}</strong><small>${escapeHtml(salesWindow)}</small></div>
                <div class="stat-card"><span>Discount</span><strong>${escapeHtml(discountActive ? 'Active' : 'Inactive')}</strong><small>${escapeHtml(discountActive ? `${discount.label || 'Pay in full'} · ${discount.value}${discount.type === 'fixed' ? '' : '%'}` : 'No active pay-in-full discount')}</small></div>
            </div>`,
            event.venue_name || event.venue_address || event.support_email
                ? `<div class="mini-row"><strong>Venue and support</strong><span>${escapeHtml([event.venue_name, event.venue_address, event.support_email ? `Support: ${event.support_email}` : ''].filter(Boolean).join(' · '))}</span></div>`
                : '',
            renderRetreatSetupSchedulesTable(schedules),
            renderRetreatSetupTicketTypesTable(tickets),
        ].join('');
    }

    function renderRetreatSetupSchedulesTable(rows) {
        const schedules = Array.isArray(rows) ? rows : [];
        if (!schedules.length) {
            return '<div class="empty">No schedule has been published for this retreat edition.</div>';
        }

        return `
            <div class="management-table-wrap">
                <table class="management-table">
                    <caption>Schedules</caption>
                    <thead>
                        <tr><th>Day</th><th>Title</th><th>Starts</th><th>Ends</th><th>Capacity</th></tr>
                    </thead>
                    <tbody>
                        ${schedules.map((row) => `
                            <tr>
                                <td>${escapeHtml(row.day_number || '')}</td>
                                <td>${escapeHtml(row.title || `Day ${row.day_number || ''}`)}</td>
                                <td>${row.starts_at ? escapeHtml(formatDateTime(row.starts_at)) : 'Not set'}</td>
                                <td>${row.ends_at ? escapeHtml(formatDateTime(row.ends_at)) : 'Not set'}</td>
                                <td>${escapeHtml(row.capacity || 'Unlimited')}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function renderRetreatSetupTicketTypesTable(rows) {
        const tickets = Array.isArray(rows) ? rows : [];
        if (!tickets.length) {
            return '<div class="empty">No active ticket type is available for this retreat edition.</div>';
        }

        return `
            <div class="management-table-wrap">
                <table class="management-table">
                    <caption>Ticket types</caption>
                    <thead>
                        <tr><th>Ticket</th><th>Price</th><th>Capacity</th><th>Per booking</th></tr>
                    </thead>
                    <tbody>
                        ${tickets.map((ticket) => `
                            <tr>
                                <td>${escapeHtml(ticket.name || 'Ticket')}</td>
                                <td>${escapeHtml(formatMoney(ticket.price, ticket.currency))}</td>
                                <td>${escapeHtml(ticket.capacity || 'Unlimited')}</td>
                                <td>${escapeHtml(ticket.min_per_booking || 1)} - ${escapeHtml(ticket.max_per_booking || 'unlimited')}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function renderAccommodationManagementPanelState() {
        if (!currentUser || !canManageGoshenRegistration()) {
            accommodationManagementPanel.hidden = true;
            return;
        }

        accommodationManagementPanel.hidden = false;
        renderAccommodationManagementEventOptions();

        if (!eventsCache.length) {
            accommodationManagementData.innerHTML = '<div class="empty">Retreat editions are still loading.</div>';
            return;
        }

        loadAccommodationManagement(false);
    }

    function renderAccommodationManagementEventOptions() {
        const events = Array.isArray(eventsCache) ? eventsCache : [];
        if (!events.length) {
            accommodationManagementEvent.innerHTML = '<option value="">No retreat edition available</option>';
            accommodationManagementEvent.disabled = true;
            refreshAccommodationManagement.disabled = true;
            return;
        }

        const previous = accommodationManagementEvent.value || accommodationManagementLoadedEvent;
        accommodationManagementEvent.disabled = false;
        refreshAccommodationManagement.disabled = false;
        accommodationManagementEvent.innerHTML = events.map((event) => {
            const id = event.public_id || event.id || '';
            const name = event.title || event.name || 'Goshen Retreat';
            return `<option value="${escapeHtml(id)}">${escapeHtml(name)}</option>`;
        }).join('');

        const hasPrevious = events.some((event) => `${event.public_id || event.id || ''}` === `${previous || ''}`);
        accommodationManagementEvent.value = hasPrevious ? previous : `${events[0].public_id || events[0].id || ''}`;
    }

    function loadAccommodationManagement(force = false) {
        if (!currentUser?.api_token || !canManageGoshenRegistration()) return;

        const eventId = accommodationManagementEvent.value || eventsCache[0]?.public_id || eventsCache[0]?.id || '';
        if (!eventId) {
            accommodationManagementData.innerHTML = '<div class="empty">No retreat edition is available for accommodation management yet.</div>';
            return;
        }

        if (!force && (accommodationManagementLoadedEvent === `${eventId}` || accommodationManagementLoadingEvent === `${eventId}`)) return;

        const token = ++accommodationManagementRequestToken;
        accommodationManagementLoadingEvent = `${eventId}`;
        accommodationManagementData.innerHTML = '<div class="empty">Loading accommodation management...</div>';
        refreshAccommodationManagement.disabled = true;

        apiPost(`/api/goshen-retreat/events/${encodeURIComponent(eventId)}/accommodation-management`, authPayload())
            .then((payload) => {
                if (token !== accommodationManagementRequestToken) return;

                accommodationManagementLoadedEvent = `${eventId}`;
                accommodationManagementData.innerHTML = renderAccommodationManagementSummary(payload.data || {});
            })
            .catch((error) => {
                if (token !== accommodationManagementRequestToken) return;

                accommodationManagementLoadedEvent = '';
                accommodationManagementData.innerHTML = `<div class="empty">${escapeHtml(error.message || 'Unable to load accommodation management right now.')}</div>`;
            })
            .finally(() => {
                if (token !== accommodationManagementRequestToken) return;

                accommodationManagementLoadingEvent = '';
                refreshAccommodationManagement.disabled = false;
            });
    }

    function renderAccommodationManagementSummary(data) {
        const event = data.event || {};
        const totals = data.totals || {};
        const eligible = Array.isArray(data.eligible_attendees) ? data.eligible_attendees : [];
        const allocations = Array.isArray(data.allocations) ? data.allocations : [];
        const generatedAt = data.generated_at ? `Updated ${formatDateTime(data.generated_at)}` : 'Live accommodation summary';

        return [
            `<div class="mini-row"><strong>${escapeHtml(event.name || 'Goshen accommodation')}</strong><span>${escapeHtml(generatedAt)}</span></div>`,
            renderAccommodationManagementStats(totals),
            `<div class="management-breakdowns">
                ${renderBreakdownCard('Allocation status', data.status_breakdown, 'GBP')}
            </div>`,
            renderAccommodationEligibleTable(eligible),
            renderAccommodationAllocationsTable(allocations),
        ].join('');
    }

    function renderAccommodationManagementStats(totals) {
        const eligible = Number(totals.eligible_attendees || 0);
        const allocated = Number(totals.allocated || 0);
        const unallocated = Number(totals.unallocated || Math.max(0, eligible - allocated));
        const progress = eligible > 0 ? (allocated / eligible) * 100 : 0;
        const cards = [
            ['Eligible attendees', eligible, `${allocated} allocated · ${unallocated} remaining`],
            ['Assigned', totals.assigned ?? 0, 'Current assigned allocations'],
            ['Changed', totals.changed ?? 0, 'Room or bed updates'],
            ['Removed', totals.removed ?? 0, `${progress.toFixed(progress % 1 === 0 ? 0 : 1)}% allocated`],
        ];

        return `<div class="management-stats">${cards.map(([label, value, detail]) => `
            <div class="stat-card">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value)}</strong>
                <small>${escapeHtml(detail)}</small>
            </div>
        `).join('')}</div>`;
    }

    function renderAccommodationEligibleTable(rows) {
        const items = Array.isArray(rows) ? rows.slice(0, 80) : [];
        if (!items.length) {
            return '<div class="empty">No paid attendees with active tickets are eligible for accommodation yet.</div>';
        }

        return `
            <div class="management-table-wrap">
                <table class="management-table">
                    <caption>Eligible attendees</caption>
                    <thead>
                        <tr><th>Attendee</th><th>Ticket</th><th>Booking</th><th>Allocation</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        ${items.map((row) => {
                            const allocation = row.current_allocation || null;
                            const attendeeId = valueFrom(row, ['id'], '');
                            const allocationId = valueFrom(allocation || {}, ['id'], '');
                            const ticketId = valueFrom(row, ['ticket_id'], '');
                            const name = valueFrom(row, ['name'], 'Unnamed attendee');
                            const email = valueFrom(row, ['email'], '');
                            const phone = valueFrom(row, ['phone'], '');
                            const ticket = valueFrom(row, ['ticket_number', 'ticket_type'], 'Ticket');
                            const booking = statusLabel(valueFrom(row, ['booking_status_label', 'booking_status'], 'Unknown'));
                            const building = valueFrom(allocation || {}, ['building'], '');
                            const room = valueFrom(allocation || {}, ['room'], '');
                            const bed = valueFrom(allocation || {}, ['bed'], '');
                            const note = valueFrom(allocation || {}, ['check_in_note'], '');
                            const status = valueFrom(allocation || {}, ['status'], 'assigned');
                            const location = [building, room ? `Room ${room}` : '', bed ? `Bed ${bed}` : ''].filter(Boolean).join(' · ') || 'Not allocated';

                            return `
                                <tr>
                                    <td>${escapeHtml(name)}${email || phone ? `<small>${escapeHtml([email, phone].filter(Boolean).join(' · '))}</small>` : ''}</td>
                                    <td>${escapeHtml(ticket)}</td>
                                    <td>${escapeHtml(booking)}</td>
                                    <td>${escapeHtml(location)}${allocation ? `<small>${escapeHtml(statusLabel(status))}</small>` : ''}</td>
                                    <td>
                                        <button class="button ghost accommodation-save-button" type="button"
                                            data-attendee-id="${escapeHtml(attendeeId)}"
                                            data-allocation-id="${escapeHtml(allocationId)}"
                                            data-ticket-id="${escapeHtml(ticketId)}"
                                            data-status="${escapeHtml(status)}"
                                            data-building="${escapeHtml(building)}"
                                            data-room="${escapeHtml(room)}"
                                            data-bed="${escapeHtml(bed)}"
                                            data-note="${escapeHtml(note)}">
                                            ${allocation ? 'Edit' : 'Assign'}
                                        </button>
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function renderAccommodationAllocationsTable(rows) {
        const items = Array.isArray(rows) ? rows.slice(0, 80) : [];
        if (!items.length) {
            return '<div class="empty">No accommodation allocations have been saved yet.</div>';
        }

        return `
            <div class="management-table-wrap">
                <table class="management-table">
                    <caption>Current allocations</caption>
                    <thead>
                        <tr><th>Attendee</th><th>Ticket</th><th>Status</th><th>Building</th><th>Room</th><th>Bed</th><th>Updated</th></tr>
                    </thead>
                    <tbody>
                        ${items.map((row) => {
                            const attendee = row.attendee || {};
                            const name = valueFrom(row, ['attendee_name'], valueFrom(attendee, ['name'], 'Unnamed attendee'));
                            const email = valueFrom(row, ['attendee_email'], '');
                            const ticket = valueFrom(row, ['ticket_number', 'ticket_type'], 'Ticket');
                            const status = statusLabel(valueFrom(row, ['status'], 'assigned'));
                            const updated = valueFrom(row, ['updated_at', 'assigned_at'], '');

                            return `
                                <tr>
                                    <td>${escapeHtml(name)}${email ? `<small>${escapeHtml(email)}</small>` : ''}</td>
                                    <td>${escapeHtml(ticket)}</td>
                                    <td>${escapeHtml(status)}</td>
                                    <td>${escapeHtml(valueFrom(row, ['building'], ''))}</td>
                                    <td>${escapeHtml(valueFrom(row, ['room'], ''))}</td>
                                    <td>${escapeHtml(valueFrom(row, ['bed'], ''))}</td>
                                    <td>${updated ? escapeHtml(formatDateTime(updated)) : 'Not available'}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function updateAccommodationAllocation(button) {
        if (!canManageGoshenRegistration()) {
            notice('Your account is not authorized to manage accommodation allocations.', 'error');
            return;
        }

        const eventId = accommodationManagementEvent.value || accommodationManagementLoadedEvent || eventsCache[0]?.public_id || eventsCache[0]?.id || '';
        const attendeeId = button.dataset.attendeeId || '';
        const allocationId = button.dataset.allocationId || '';
        const ticketId = button.dataset.ticketId || '';
        if (!eventId || !attendeeId) {
            notice('This accommodation action is missing required data.', 'error');
            return;
        }

        const status = window.prompt('Allocation status: assigned, changed, or removed', button.dataset.status || 'assigned');
        if (status === null) return;
        const normalizedStatus = status.trim().toLowerCase();
        if (!['assigned', 'changed', 'removed'].includes(normalizedStatus)) {
            notice('Use one of these statuses: assigned, changed, or removed.', 'error');
            return;
        }

        const building = window.prompt('Building', button.dataset.building || '');
        if (building === null) return;
        const room = window.prompt('Room', button.dataset.room || '');
        if (room === null) return;
        const bed = window.prompt('Bed', button.dataset.bed || '');
        if (bed === null) return;
        const note = window.prompt('Check-in note', button.dataset.note || '');
        if (note === null) return;

        const original = button.textContent;
        button.disabled = true;
        button.textContent = 'Saving...';

        const payload = {
            status: normalizedStatus,
            building: building.trim(),
            room: room.trim(),
            bed: bed.trim(),
            check_in_note: note.trim(),
            ...(ticketId ? { ticket_id: Number(ticketId) } : {}),
        };
        const url = allocationId
            ? `/api/goshen-retreat/accommodation-allocations/${encodeURIComponent(allocationId)}`
            : '/api/goshen-retreat/accommodation-allocations';
        const data = allocationId
            ? payload
            : { ...payload, event_id: eventId, attendee_id: Number(attendeeId) };

        apiPost(url, authPayload(data))
            .then((payload) => {
                notice(payload.message || 'Accommodation allocation saved.');
                accommodationManagementLoadedEvent = '';
                loadAccommodationManagement(true);
            })
            .catch((error) => notice(error.message || 'Unable to save accommodation allocation.', 'error'))
            .finally(() => {
                button.disabled = false;
                button.textContent = original;
            });
    }

    function renderFundraisingManagementPanelState() {
        if (!currentUser || !canManageFundraising()) {
            fundraisingManagementPanel.hidden = true;
            return;
        }

        fundraisingManagementPanel.hidden = false;
        loadFundraisingManagementSummary(false);
    }

    function loadFundraisingManagementSummary(force = false) {
        if (!currentUser?.api_token || !canManageFundraising()) return;
        if (!force && (fundraisingManagementLoaded || fundraisingManagementLoading)) return;

        const token = ++fundraisingManagementRequestToken;
        fundraisingManagementLoading = true;
        fundraisingManagementData.innerHTML = '<div class="empty">Loading fundraising management summary...</div>';
        refreshFundraisingManagement.disabled = true;

        apiPost('/api/fundraising/management/summary', authPayload())
            .then((payload) => {
                if (token !== fundraisingManagementRequestToken) return;

                fundraisingManagementLoaded = true;
                fundraisingManagementData.innerHTML = renderFundraisingManagementSummary(payload.data || {});
            })
            .catch((error) => {
                if (token !== fundraisingManagementRequestToken) return;

                fundraisingManagementLoaded = false;
                fundraisingManagementData.innerHTML = `<div class="empty">${escapeHtml(error.message || 'Unable to load fundraising management right now.')}</div>`;
            })
            .finally(() => {
                if (token !== fundraisingManagementRequestToken) return;

                fundraisingManagementLoading = false;
                refreshFundraisingManagement.disabled = false;
            });
    }

    function renderFundraisingManagementSummary(data) {
        const totals = data.totals || {};
        const breakdowns = data.breakdowns || {};
        const campaigns = Array.isArray(data.campaigns) ? data.campaigns : [];
        const contributions = Array.isArray(data.recent_contributions) ? data.recent_contributions : [];
        const currency = totals.currency || campaigns[0]?.currency || contributions[0]?.currency || 'GBP';
        const generatedAt = data.generated_at ? `Updated ${formatDateTime(data.generated_at)}` : 'Live fundraising summary';

        return [
            `<div class="mini-row"><strong>Fundraising overview</strong><span>${escapeHtml(generatedAt)}</span></div>`,
            renderFundraisingManagementStats(totals, currency),
            `<div class="management-breakdowns">
                ${renderBreakdownCard('Campaign status', breakdowns.campaign_status, currency)}
                ${renderBreakdownCard('Contribution status', breakdowns.contribution_status, currency)}
                ${renderBreakdownCard('Payment channels', breakdowns.payment_provider, currency)}
                ${renderBreakdownCard('Campaign progress', breakdowns.campaign_progress, currency)}
            </div>`,
            renderFundraisingCampaignsTable(campaigns, currency),
            renderFundraisingContributionsTable(contributions, currency),
        ].join('');
    }

    function renderFundraisingManagementStats(totals, currency) {
        const cards = [
            ['Campaigns', totals.campaigns ?? 0, `${totals.active_campaigns ?? 0} active · ${totals.draft_campaigns ?? 0} draft`],
            ['Raised', formatMoney(totals.raised_amount ?? 0, currency), `Goal ${formatMoney(totals.goal_amount ?? 0, currency)}`],
            ['All-time support', formatMoney(totals.all_time_raised_amount ?? 0, currency), `${totals.succeeded_contributions ?? 0} succeeded · ${totals.pending_contributions ?? 0} pending`],
            ['Channels', `Wallet ${formatMoney(totals.wallet_amount ?? 0, currency)}`, `Card ${formatMoney(totals.stripe_amount ?? 0, currency)} · Pending ${formatMoney(totals.pending_amount ?? 0, currency)}`],
        ];

        return `<div class="management-stats">${cards.map(([label, value, detail]) => `
            <div class="stat-card">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value)}</strong>
                <small>${escapeHtml(detail)}</small>
            </div>
        `).join('')}</div>`;
    }

    function renderFundraisingCampaignsTable(rows, currency) {
        const items = Array.isArray(rows) ? rows.slice(0, 10) : [];
        if (!items.length) {
            return '<div class="empty">No fundraising campaigns yet.</div>';
        }

        return `
            <div class="management-table-wrap">
                <table class="management-table">
                    <caption>Recent campaigns</caption>
                    <thead>
                        <tr><th>Campaign</th><th>Status</th><th>Raised</th><th>Progress</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        ${items.map((row) => {
                            const title = valueFrom(row, ['title'], 'Campaign');
                            const cause = valueFrom(row, ['cause'], '');
                            const status = valueFrom(row, ['status_label', 'status'], 'draft');
                            const identifier = valueFrom(row, ['slug', 'id'], '');
                            const raised = valueFrom(row, ['raised_amount'], 0);
                            const goal = valueFrom(row, ['goal_amount'], 0);
                            const rowCurrency = valueFrom(row, ['currency'], currency);
                            const progress = Number(valueFrom(row, ['progress_percentage'], 0));
                            const safeProgress = Number.isFinite(progress) ? Math.max(0, Math.min(100, progress)) : 0;
                            const donors = Number(valueFrom(row, ['donor_count'], 0));
                            const media = Number(valueFrom(row, ['media_count'], 0));
                            const actions = Array.isArray(row.available_actions) ? row.available_actions.filter(Boolean) : [];
                            const actionButtons = actions.length && identifier
                                ? actions.map((action) => `
                                    <button class="button ghost fundraising-campaign-status-button" type="button" data-campaign="${escapeHtml(identifier)}" data-status="${escapeHtml(action)}">
                                        ${escapeHtml(fundraisingCampaignActionLabel(action))}
                                    </button>
                                `).join('')
                                : '<small>No actions</small>';

                            return `
                                <tr>
                                    <td>${escapeHtml(title)}${cause ? `<small>${escapeHtml(cause)}</small>` : ''}</td>
                                    <td>${escapeHtml(statusLabel(status))}<small>${escapeHtml(media)} media item${media === 1 ? '' : 's'}</small></td>
                                    <td>${escapeHtml(formatMoney(raised, rowCurrency))}<small>Goal ${escapeHtml(formatMoney(goal, rowCurrency))}</small></td>
                                    <td>${escapeHtml(safeProgress.toFixed(safeProgress % 1 === 0 ? 0 : 1))}%<small>${escapeHtml(donors)} supporter${donors === 1 ? '' : 's'}</small></td>
                                    <td><div class="table-actions">${actionButtons}</div></td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function fundraisingCampaignActionLabel(action) {
        const normalized = `${action || ''}`.trim().toLowerCase();
        if (normalized === 'active') return 'Publish';
        if (normalized === 'paused') return 'Pause';
        if (normalized === 'closed') return 'Close';

        return statusLabel(normalized || 'update');
    }

    function updateFundraisingCampaignStatus(button) {
        if (!canManageFundraising()) {
            notice('Your account is not authorized to manage fundraising.', 'error');
            return;
        }

        const campaign = button.dataset.campaign || '';
        const status = button.dataset.status || '';
        if (!campaign || !status) {
            notice('This fundraising campaign action is missing required data.', 'error');
            return;
        }

        const label = fundraisingCampaignActionLabel(status).toLowerCase();
        if (!window.confirm(`Do you want to ${label} this fundraising campaign?`)) {
            return;
        }

        const original = button.textContent;
        button.disabled = true;
        button.textContent = 'Updating...';

        apiPost(`/api/fundraising/management/campaigns/${encodeURIComponent(campaign)}/status`, authPayload({ status }))
            .then((payload) => {
                notice(payload.message || 'Fundraising campaign status updated.');
                fundraisingManagementLoaded = false;
                return loadFundraisingManagementSummary(true);
            })
            .catch((error) => notice(error.message || 'Unable to update fundraising campaign status.', 'error'))
            .finally(() => {
                button.disabled = false;
                button.textContent = original;
            });
    }

    function renderFundraisingContributionsTable(rows, currency) {
        const items = Array.isArray(rows) ? rows.slice(0, 10) : [];
        if (!items.length) {
            return '<div class="empty">No fundraising contributions yet.</div>';
        }

        return `
            <div class="management-table-wrap">
                <table class="management-table">
                    <caption>Recent contributions</caption>
                    <thead>
                        <tr><th>Supporter</th><th>Campaign</th><th>Amount</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        ${items.map((row) => {
                            const supporter = valueFrom(row, ['display_name'], 'Supporter');
                            const campaign = valueFrom(row, ['campaign_title'], 'Fundraising campaign');
                            const amount = valueFrom(row, ['amount'], 0);
                            const rowCurrency = valueFrom(row, ['currency'], currency);
                            const provider = valueFrom(row, ['payment_provider_label', 'payment_provider'], 'Payment');
                            const status = valueFrom(row, ['status_label', 'status'], 'pending');
                            const date = valueFrom(row, ['succeeded_at', 'created_at'], '');

                            return `
                                <tr>
                                    <td>${escapeHtml(supporter)}${row.anonymous ? '<small>Anonymous public display</small>' : ''}</td>
                                    <td>${escapeHtml(campaign)}</td>
                                    <td>${escapeHtml(formatMoney(amount, rowCurrency))}<small>${escapeHtml(provider)}</small></td>
                                    <td>${escapeHtml(statusLabel(status))}${date ? `<small>${escapeHtml(formatDateTime(date))}</small>` : ''}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function renderScannerManagementPanelState() {
        if (!currentUser || !canManageScanners()) {
            scannerManagementPanel.hidden = true;
            return;
        }

        scannerManagementPanel.hidden = false;
        renderScannerManagementEventOptions();

        if (!eventsCache.length) {
            scannerManagementData.innerHTML = '<div class="empty">Retreat editions are still loading.</div>';
            return;
        }

        loadScannerManagement(false);
    }

    function renderScannerManagementEventOptions() {
        const events = Array.isArray(eventsCache) ? eventsCache : [];
        if (!events.length) {
            scannerManagementEvent.innerHTML = '<option value="">No retreat edition available</option>';
            scannerManagementEvent.disabled = true;
            refreshScannerManagement.disabled = true;
            return;
        }

        const previous = scannerManagementEvent.value || scannerManagementLoadedEvent;
        scannerManagementEvent.disabled = false;
        refreshScannerManagement.disabled = false;
        scannerManagementEvent.innerHTML = events.map((event) => {
            const id = event.public_id || event.id || '';
            const name = event.title || event.name || 'Goshen Retreat';
            return `<option value="${escapeHtml(id)}">${escapeHtml(name)}</option>`;
        }).join('');

        const hasPrevious = events.some((event) => `${event.public_id || event.id || ''}` === `${previous || ''}`);
        scannerManagementEvent.value = hasPrevious ? previous : `${events[0].public_id || events[0].id || ''}`;
    }

    function loadScannerManagement(force = false) {
        if (!currentUser?.api_token || !canManageScanners()) return;

        const eventId = scannerManagementEvent.value || eventsCache[0]?.public_id || eventsCache[0]?.id || '';
        if (!eventId) {
            scannerManagementData.innerHTML = '<div class="empty">No retreat edition is available for scanner management yet.</div>';
            return;
        }

        if (!force && (scannerManagementLoadedEvent === `${eventId}` || scannerManagementLoadingEvent === `${eventId}`)) return;

        const token = ++scannerManagementRequestToken;
        scannerManagementLoadingEvent = `${eventId}`;
        scannerManagementData.innerHTML = '<div class="empty">Loading scanner management...</div>';
        refreshScannerManagement.disabled = true;

        Promise.all([
            apiPost(`/api/goshen-retreat/events/${encodeURIComponent(eventId)}/scanner-stats`, authPayload()),
            apiPost('/api/goshen-retreat/scanner/operators', authPayload()),
        ])
            .then(([statsPayload, operatorsPayload]) => {
                if (token !== scannerManagementRequestToken) return;

                scannerManagementLoadedEvent = `${eventId}`;
                scannerManagementData.innerHTML = renderScannerManagementSummary(
                    statsPayload.data || {},
                    operatorsPayload.data?.operators || [],
                );
            })
            .catch((error) => {
                if (token !== scannerManagementRequestToken) return;

                scannerManagementLoadedEvent = '';
                scannerManagementData.innerHTML = `<div class="empty">${escapeHtml(error.message || 'Unable to load scanner management right now.')}</div>`;
            })
            .finally(() => {
                if (token !== scannerManagementRequestToken) return;

                scannerManagementLoadingEvent = '';
                refreshScannerManagement.disabled = false;
            });
    }

    function renderScannerManagementSummary(stats, operators) {
        const event = stats.event || {};
        const generatedAt = stats.generated_at ? `Updated ${formatDateTime(stats.generated_at)}` : 'Live scanner summary';

        return [
            `<div class="mini-row"><strong>${escapeHtml(event.name || 'Goshen scanner management')}</strong><span>${escapeHtml(generatedAt)}</span></div>`,
            renderScannerManagementStats(stats, operators),
            `<div class="management-breakdowns">
                ${renderScannerBreakdownTable('Gender check-in', stats.gender_breakdown)}
                ${renderScannerBreakdownTable('Age group check-in', stats.age_group_breakdown)}
            </div>`,
            renderScannerOperatorsTable(operators),
        ].join('');
    }

    function renderScannerManagementStats(stats, operators) {
        const registered = Number(stats.registered || 0);
        const checkedIn = Number(stats.checked_in || 0);
        const notYet = Number(stats.not_yet_checked_in || Math.max(0, registered - checkedIn));
        const progress = registered > 0 ? (checkedIn / registered) * 100 : 0;
        const activeOperators = operators.filter((operator) => !truthyFlag(operator.scanner_suspended)).length;
        const suspendedOperators = operators.length - activeOperators;
        const cards = [
            ['Tickets', registered, `${notYet} not yet checked in`],
            ['Checked in', checkedIn, `${progress.toFixed(progress % 1 === 0 ? 0 : 1)}% complete`],
            ['Operators', activeOperators, `${suspendedOperators} suspended · ${operators.length} total`],
            ['Scanner access', registered > 0 ? 'Ready' : 'Waiting', registered > 0 ? 'Manifest and stats are available' : 'No eligible tickets yet'],
        ];

        return `<div class="management-stats">${cards.map(([label, value, detail]) => `
            <div class="stat-card">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value)}</strong>
                <small>${escapeHtml(detail)}</small>
            </div>
        `).join('')}</div>`;
    }

    function renderScannerBreakdownTable(title, rows) {
        const items = Array.isArray(rows) ? rows : [];
        if (!items.length) {
            return `
                <div class="breakdown-card">
                    <div class="breakdown-title">${escapeHtml(title)}<span>0 total</span></div>
                    <div class="hint">No scanner breakdown is available yet.</div>
                </div>
            `;
        }

        return `
            <div class="breakdown-card">
                <div class="breakdown-title">${escapeHtml(title)}<span>${escapeHtml(items.reduce((sum, row) => sum + Number(row.registered || 0), 0))} registered</span></div>
                ${renderBreakdownDonut(title, items.map((row) => ({
                    label: row.label || row.code || 'Not specified',
                    key: row.code || row.label || 'not_specified',
                    count: Number(row.registered || 0),
                })), items.reduce((sum, row) => sum + Number(row.registered || 0), 0))}
                ${items.map((row) => {
                    const registered = Number(row.registered || 0);
                    const checkedIn = Number(row.checked_in || 0);
                    const percent = registered > 0 ? Math.max(0, Math.min(100, (checkedIn / registered) * 100)) : 0;

                    return `
                        <div class="breakdown-row">
                            <div class="breakdown-line">
                                <span>${escapeHtml(row.label || row.code || 'Not specified')}</span>
                                <span>${escapeHtml(checkedIn)} / ${escapeHtml(registered)} · ${escapeHtml(percent.toFixed(percent % 1 === 0 ? 0 : 1))}%</span>
                            </div>
                            <div class="breakdown-track" aria-hidden="true"><span class="breakdown-fill" style="--bar-width: ${percent}%;"></span></div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    function renderScannerOperatorsTable(rows) {
        const operators = Array.isArray(rows) ? rows : [];
        if (!operators.length) {
            return '<div class="empty">No scanner operators are configured yet.</div>';
        }

        return `
            <div class="management-table-wrap">
                <table class="management-table">
                    <caption>Scanner operators</caption>
                    <thead>
                        <tr><th>Operator</th><th>Roles</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        ${operators.map((operator) => {
                            const suspended = truthyFlag(operator.scanner_suspended);
                            const operatorId = valueFrom(operator, ['id'], '');
                            const name = valueFrom(operator, ['name'], 'Scanner');
                            const email = valueFrom(operator, ['email'], '');
                            const roles = Array.isArray(operator.roles) ? operator.roles.map((role) => `${role}`.replaceAll('_', ' ')).join(', ') : '';
                            const lastSeen = valueFrom(operator, ['last_seen_at'], '');
                            const reason = valueFrom(operator, ['scanner_suspension_reason'], '');
                            const isSelf = `${operatorId}` === `${currentUser?.id || ''}`;
                            const button = isSelf
                                ? '<small>You cannot suspend yourself</small>'
                                : `<button class="button ghost scanner-toggle-button" type="button" data-user-id="${escapeHtml(operatorId)}" data-name="${escapeHtml(name)}" data-suspend="${suspended ? '0' : '1'}">${suspended ? 'Resume' : 'Suspend'}</button>`;

                            return `
                                <tr>
                                    <td>${escapeHtml(name)}${email ? `<small>${escapeHtml(email)}</small>` : ''}</td>
                                    <td>${escapeHtml(roles || 'Scanner')}</td>
                                    <td>${suspended ? 'Suspended' : 'Active'}${reason ? `<small>${escapeHtml(reason)}</small>` : (lastSeen ? `<small>Last active ${escapeHtml(formatDateTime(lastSeen))}</small>` : '')}</td>
                                    <td>${button}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function updateScannerOperatorAccess(button) {
        const userId = button.dataset.userId || '';
        const suspend = button.dataset.suspend === '1';
        const name = button.dataset.name || 'this scanner';
        if (!userId) return;

        let reason = '';
        if (suspend) {
            reason = window.prompt(`Reason for suspending ${name}:`, 'Scanner activity paused by event manager');
            if (reason === null) return;
        } else if (!window.confirm(`Resume scanner access for ${name}?`)) {
            return;
        }

        button.disabled = true;
        apiPost(`/api/goshen-retreat/scanner/operators/${encodeURIComponent(userId)}/toggle`, authPayload({
            suspend,
            reason: reason || '',
        }))
            .then((payload) => {
                notice(payload.message || (suspend ? 'Scanner activity suspended.' : 'Scanner activity resumed.'));
                scannerManagementLoadedEvent = '';
                loadScannerManagement(true);
            })
            .catch((error) => notice(error.message, 'error'))
            .finally(() => {
                button.disabled = false;
            });
    }

    function renderSurveyManagementPanelState() {
        if (!currentUser || !canViewSurveyStats()) {
            surveyManagementPanel.hidden = true;
            return;
        }

        surveyManagementPanel.hidden = false;
        renderSurveyManagementEventOptions();

        if (!eventsCache.length) {
            surveyManagementData.innerHTML = '<div class="empty">Retreat editions are still loading.</div>';
            return;
        }

        loadSurveyManagementStats(false);
    }

    function renderSurveyManagementEventOptions() {
        const events = Array.isArray(eventsCache) ? eventsCache : [];
        if (!events.length) {
            surveyManagementEvent.innerHTML = '<option value="">No retreat edition available</option>';
            surveyManagementEvent.disabled = true;
            refreshSurveyManagement.disabled = true;
            return;
        }

        const previous = surveyManagementEvent.value || surveyManagementLoadedEvent;
        surveyManagementEvent.disabled = false;
        refreshSurveyManagement.disabled = false;
        surveyManagementEvent.innerHTML = events.map((event) => {
            const id = event.public_id || event.id || '';
            const name = event.title || event.name || 'Goshen Retreat';
            return `<option value="${escapeHtml(id)}">${escapeHtml(name)}</option>`;
        }).join('');

        const hasPrevious = events.some((event) => `${event.public_id || event.id || ''}` === `${previous || ''}`);
        surveyManagementEvent.value = hasPrevious ? previous : `${events[0].public_id || events[0].id || ''}`;
    }

    function loadSurveyManagementStats(force = false) {
        if (!currentUser?.api_token || !canViewSurveyStats()) return;

        const eventId = surveyManagementEvent.value || eventsCache[0]?.public_id || eventsCache[0]?.id || '';
        if (!eventId) {
            surveyManagementData.innerHTML = '<div class="empty">No retreat edition is available for survey stats yet.</div>';
            return;
        }

        if (!force && (surveyManagementLoadedEvent === `${eventId}` || surveyManagementLoadingEvent === `${eventId}`)) return;

        const token = ++surveyManagementRequestToken;
        surveyManagementLoadingEvent = `${eventId}`;
        surveyManagementData.innerHTML = '<div class="empty">Loading survey management stats...</div>';
        refreshSurveyManagement.disabled = true;

        apiPost(`/api/goshen-retreat/experience/events/${encodeURIComponent(eventId)}/stats`, authPayload())
            .then((payload) => {
                if (token !== surveyManagementRequestToken) return;

                surveyManagementLoadedEvent = `${eventId}`;
                surveyManagementData.innerHTML = renderSurveyManagementStats(payload.data || {});
            })
            .catch((error) => {
                if (token !== surveyManagementRequestToken) return;

                surveyManagementLoadedEvent = '';
                surveyManagementData.innerHTML = `<div class="empty">${escapeHtml(error.message || 'Unable to load survey management right now.')}</div>`;
            })
            .finally(() => {
                if (token !== surveyManagementRequestToken) return;

                surveyManagementLoadingEvent = '';
                refreshSurveyManagement.disabled = false;
            });
    }

    function renderSurveyManagementStats(data) {
        const event = data.event || {};
        const checkedIn = Number(data.checked_in_attendees || 0);
        const responses = Number(data.responses || 0);
        const responseRate = Number(data.response_rate || 0);
        const surveys = Array.isArray(data.surveys) ? data.surveys : [];
        const questionStats = Array.isArray(data.question_stats) ? data.question_stats : [];
        const recentResponses = Array.isArray(data.recent_responses) ? data.recent_responses : [];

        return [
            `<div class="mini-row"><strong>${escapeHtml(event.name || 'Goshen Experience survey')}</strong><span>${escapeHtml(responseRate.toFixed(responseRate % 1 === 0 ? 0 : 1))}% response rate</span></div>`,
            renderSurveyManagementStatCards(data),
            `<div class="management-breakdowns">
                ${renderBreakdownCard('Gender', surveyRows(data.by_gender), 'GBP')}
                ${renderBreakdownCard('Age group', surveyRows(data.by_age_group), 'GBP')}
                ${renderBreakdownCard('Country', surveyRows(data.by_country), 'GBP')}
                ${renderBreakdownCard('State / province', surveyRows(data.by_state), 'GBP')}
            </div>`,
            `<div class="mini-row"><strong>Response progress</strong><span>${escapeHtml(responses)} responses from ${escapeHtml(checkedIn)} checked-in attendee${checkedIn === 1 ? '' : 's'}.</span></div>`,
            renderSurveyList(surveys),
            renderSurveyQuestionStats(questionStats),
            renderSurveyRecentResponses(recentResponses, responses),
        ].join('');
    }

    function renderSurveyList(surveys) {
        const items = Array.isArray(surveys) ? surveys.slice(0, 8) : [];
        if (!items.length) {
            return '<div class="mini-row"><strong>Survey management</strong><span>No surveys are linked yet.</span></div>';
        }

        return `
            <div class="management-table-wrap">
                <table class="management-table">
                    <caption>Survey management</caption>
                    <thead>
                        <tr><th>Survey</th><th>Responses</th><th>Settings</th></tr>
                    </thead>
                    <tbody>
                        ${items.map((survey) => {
                            const title = survey.title || 'Goshen Experience';
                            const questions = Number(survey.questions_count || 0);
                            const responses = Number(survey.responses_count || 0);
                            return `
                                <tr>
                                    <td>${escapeHtml(title)}<small>${truthyFlag(survey.is_active) ? 'Active' : 'Inactive'}</small></td>
                                    <td>${escapeHtml(responses)}<small>${escapeHtml(questions)} question${questions === 1 ? '' : 's'}</small></td>
                                    <td><div class="table-actions">
                                        ${renderSurveySettingButton(survey, 'is_active', 'Active')}
                                        ${renderSurveySettingButton(survey, 'allow_audio', 'Audio')}
                                        ${renderSurveySettingButton(survey, 'allow_video', 'Video')}
                                        ${renderSurveySettingButton(survey, 'allow_all_authenticated_users', 'Open to all')}
                                    </div></td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
                ${surveys.length > items.length ? `<div class="hint">Showing ${escapeHtml(items.length)} of ${escapeHtml(surveys.length)} surveys.</div>` : ''}
            </div>
        `;
    }

    function renderSurveySettingButton(survey, key, label) {
        const surveyId = survey.id || '';
        const enabled = truthyFlag(survey[key]);
        const nextValue = enabled ? '0' : '1';
        const buttonText = `${label}: ${enabled ? 'On' : 'Off'}`;

        if (!surveyId) {
            return `<small>${escapeHtml(buttonText)}</small>`;
        }

        return `
            <button class="button ghost survey-setting-button" type="button" data-survey-id="${escapeHtml(surveyId)}" data-setting="${escapeHtml(key)}" data-value="${nextValue}">
                ${escapeHtml(buttonText)}
            </button>
        `;
    }

    function updateSurveySetting(button) {
        if (!canViewSurveyStats()) {
            notice('Your account is not authorized to manage surveys.', 'error');
            return;
        }

        const surveyId = button.dataset.surveyId || '';
        const setting = button.dataset.setting || '';
        const enabled = button.dataset.value === '1';
        if (!surveyId || !setting) {
            notice('This survey setting is missing required data.', 'error');
            return;
        }

        if (!window.confirm('Apply this survey setting change?')) {
            return;
        }

        const original = button.textContent;
        button.disabled = true;
        button.textContent = 'Updating...';

        apiPost(`/api/goshen-retreat/experience/surveys/${encodeURIComponent(surveyId)}/settings`, authPayload({
            [setting]: enabled,
        }))
            .then((payload) => {
                notice(payload.message || 'Survey settings updated.');
                surveyManagementLoadedEvent = '';
                return loadSurveyManagementStats(true);
            })
            .catch((error) => notice(error.message || 'Unable to update survey settings.', 'error'))
            .finally(() => {
                button.disabled = false;
                button.textContent = original;
            });
    }

    function renderSurveyQuestionStats(questions) {
        const items = Array.isArray(questions) ? questions.slice(0, 8) : [];
        if (!items.length) {
            return '<div class="mini-row"><strong>Question results</strong><span>No question results yet.</span></div>';
        }

        return `
            <div class="management-table-wrap">
                <table class="management-table">
                    <caption>Question results</caption>
                    <thead>
                        <tr><th>Question</th><th>Responses</th><th>Top answers</th><th>Samples</th></tr>
                    </thead>
                    <tbody>
                        ${items.map((question) => {
                            const breakdown = Array.isArray(question.breakdown) ? question.breakdown.slice(0, 4) : [];
                            const samples = Array.isArray(question.samples) ? question.samples.slice(0, 2) : [];
                            const topAnswers = breakdown.length
                                ? breakdown.map((row) => {
                                    const visual = row.image_url
                                        ? `<img src="${escapeHtml(row.image_url)}" alt="" style="width:28px;height:28px;object-fit:cover;border-radius:6px;vertical-align:middle;margin-right:6px;">`
                                        : (row.color_hex ? `<span class="breakdown-swatch" style="--swatch:${escapeHtml(row.color_hex)};"></span>` : '');
                                    return `<small>${visual}${escapeHtml(row.label || row.key || 'Answer')} - ${escapeHtml(row.count || 0)}</small>`;
                                }).join('')
                                : '<small>No grouped answers</small>';
                            const sampleHtml = samples.length
                                ? samples.map((sample) => `<small>${escapeHtml(sample)}</small>`).join('')
                                : '<small>No text samples</small>';
                            return `
                                <tr>
                                    <td>${escapeHtml(question.prompt || 'Question')}<small>${escapeHtml((question.type || 'text').replaceAll('_', ' '))}</small></td>
                                    <td>${escapeHtml(question.responses || 0)}</td>
                                    <td>${topAnswers}</td>
                                    <td>${sampleHtml}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
                ${questions.length > items.length ? `<div class="hint">Showing ${escapeHtml(items.length)} of ${escapeHtml(questions.length)} questions.</div>` : ''}
            </div>
        `;
    }

    function renderSurveyRecentResponses(recentResponses, totalResponses) {
        const items = Array.isArray(recentResponses) ? recentResponses.slice(0, 10) : [];
        if (!items.length) {
            return '<div class="mini-row"><strong>Recent responses</strong><span>No survey responses yet.</span></div>';
        }

        return `
            <div class="management-table-wrap">
                <table class="management-table">
                    <caption>Recent survey responses</caption>
                    <thead>
                        <tr><th>Member</th><th>Story / media</th><th>Answers</th><th>Submitted</th></tr>
                    </thead>
                    <tbody>
                        ${items.map((response) => {
                            const member = response.member_name || 'Member';
                            const email = response.member_email || '';
                            const story = response.story || '';
                            const answers = Array.isArray(response.answers) ? response.answers.slice(0, 4) : [];
                            const answerHtml = answers.length
                                ? answers.map((answer) => `<small><strong>${escapeHtml(answer.prompt || 'Question')}:</strong> ${escapeHtml(answer.answer || '')}</small>`).join('')
                                : '<small>No structured answers</small>';
                            const media = [
                                response.audio_url ? `<audio controls preload="none" src="${escapeHtml(response.audio_url)}" style="width:180px;max-width:100%;"></audio>` : '',
                                response.video_url ? `<a class="button ghost" href="${escapeHtml(response.video_url)}" target="_blank" rel="noopener">Open video</a>` : '',
                            ].filter(Boolean).join('');
                            return `
                                <tr>
                                    <td>${escapeHtml(member)}${email ? `<small>${escapeHtml(email)}</small>` : ''}</td>
                                    <td>${story ? escapeHtml(story) : '<small>No written story</small>'}${media ? `<small>${media}</small>` : ''}</td>
                                    <td>${answerHtml}</td>
                                    <td>${response.submitted_at ? escapeHtml(formatDateTime(response.submitted_at)) : 'Not recorded'}<small>${escapeHtml(response.survey_title || 'Goshen Experience')}</small></td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
                ${Number(totalResponses || 0) > items.length ? `<div class="hint">Showing latest ${escapeHtml(items.length)} of ${escapeHtml(totalResponses)} responses.</div>` : ''}
            </div>
        `;
    }

    function renderSurveyManagementStatCards(data) {
        const checkedIn = Number(data.checked_in_attendees || 0);
        const responses = Number(data.responses || 0);
        const responseRate = Number(data.response_rate || 0);
        const countries = Object.keys(data.by_country || {}).length;
        const cards = [
            ['Checked in', checkedIn, 'Eligible attendee base'],
            ['Responses', responses, 'Submitted Goshen Experience responses'],
            ['Response rate', `${responseRate.toFixed(responseRate % 1 === 0 ? 0 : 1)}%`, 'Responses divided by checked-in attendees'],
            ['Countries', countries, 'Countries represented in responses'],
        ];

        return `<div class="management-stats">${cards.map(([label, value, detail]) => `
            <div class="stat-card">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value)}</strong>
                <small>${escapeHtml(detail)}</small>
            </div>
        `).join('')}</div>`;
    }

    function surveyRows(values) {
        const entries = values && typeof values === 'object' ? Object.entries(values) : [];
        return entries
            .map(([key, value]) => ({
                key,
                label: `${key || 'Not specified'}`,
                count: Number(value || 0),
            }))
            .filter((row) => row.count > 0)
            .sort((a, b) => b.count - a.count);
    }

    function renderGoshenManagementSummary(data) {
        const event = data.event || {};
        const totals = data.totals || {};
        const breakdowns = data.breakdowns || {};
        const registrations = Array.isArray(data.registrations) ? data.registrations : [];
        const attendees = Array.isArray(data.attendees) ? data.attendees : [];
        const currency = data.currency || event.currency || registrations[0]?.currency || attendees[0]?.currency || 'GBP';

        return [
            `<div class="mini-row"><strong>${escapeHtml(event.name || event.title || 'Goshen Retreat')}</strong><span>${escapeHtml(registrationStatusText(event.registration))}</span></div>`,
            renderRegistrationManagementControl(event),
            renderGoshenManagementStats(totals, currency),
            `<div class="management-breakdowns">
                ${renderBreakdownCard('Gender', breakdowns.gender, currency)}
                ${renderBreakdownCard('Age group', breakdowns.age_group, currency)}
                ${renderBreakdownCard('Free church bus', breakdowns.free_church_bus_interest, currency)}
                ${renderBreakdownCard('Volunteer department', breakdowns.volunteer_department, currency)}
                ${renderBreakdownCard('Ticket type', breakdowns.ticket_type, currency)}
                ${renderBreakdownCard('Company', breakdowns.company, currency)}
                ${renderBreakdownCard('Designation', breakdowns.designation, currency)}
                ${renderBreakdownCard('Privacy consent', breakdowns.privacy_consent, currency)}
            </div>`,
            renderRecentRegistrationsTable(registrations, currency),
            renderRecentAttendeesTable(attendees),
        ].join('');
    }

    function renderRegistrationManagementControl(event) {
        const registration = event.registration || {};
        const open = truthyFlag(registration.is_open ?? registration.open ?? registration.enabled);
        const nextOpen = !open;
        const buttonLabel = open ? 'Close registration' : 'Reopen registration';
        const message = registration.message || (open
            ? 'Members can currently start a new registration.'
            : 'Members cannot start a new registration until this is reopened.');

        return `
            <div class="mini-row">
                <strong>Registration control</strong>
                <span>${escapeHtml(message)}</span>
                <button class="button ${open ? 'ghost' : 'alt'} registration-toggle-button" type="button" data-open="${nextOpen ? '1' : '0'}">${escapeHtml(buttonLabel)}</button>
            </div>
        `;
    }

    function registrationStatusText(registration) {
        if (!registration || typeof registration !== 'object') return 'Registration status will appear here.';

        const open = truthyFlag(registration.is_open ?? registration.open ?? registration.enabled);
        const label = open ? 'Registration open' : 'Registration closed';
        const message = registration.message || registration.status_message || '';

        return message ? `${label} · ${message}` : label;
    }

    function renderGoshenManagementStats(totals, currency) {
        const cards = [
            ['Registrations', totals.registrations ?? 0, `${totals.attendees ?? 0} attendees`],
            ['Paid', totals.paid_registrations ?? 0, `${totals.pending_registrations ?? 0} pending · ${totals.cancelled_registrations ?? 0} cancelled`],
            ['Total value', formatMoney(totals.total_amount ?? 0, currency), `Paid ${formatMoney(totals.paid_amount ?? 0, currency)}`],
            ['Outstanding', formatMoney(totals.balance_amount ?? 0, currency), `Wallet ${formatMoney(totals.wallet_paid_amount ?? 0, currency)} · Online ${formatMoney(totals.online_paid_amount ?? 0, currency)}`],
        ];

        return `<div class="management-stats">${cards.map(([label, value, detail]) => `
            <div class="stat-card">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value)}</strong>
                <small>${escapeHtml(detail)}</small>
            </div>
        `).join('')}</div>`;
    }

    function renderBreakdownCard(title, rows, currency = 'GBP') {
        const items = Array.isArray(rows) ? rows : [];
        const total = items.reduce((sum, row) => sum + Number(row.count || 0), 0);

        if (!items.length) {
            return `
                <div class="breakdown-card">
                    <div class="breakdown-title">${escapeHtml(title)}<span>0 total</span></div>
                    <div class="hint">No responses yet.</div>
                </div>
            `;
        }

        return `
            <div class="breakdown-card">
                <div class="breakdown-title">${escapeHtml(title)}<span>${escapeHtml(total)} total</span></div>
                ${renderBreakdownDonut(title, items, total, currency)}
                ${items.map((row) => renderBreakdownRow(row, total, currency)).join('')}
            </div>
        `;
    }

    function renderBreakdownDonut(title, rows, total, currency = 'GBP') {
        const items = Array.isArray(rows) ? rows : [];
        if (!items.length || Number(total || 0) <= 0) return '';

        const colors = ['#f8b823', '#11845b', '#0c2230', '#0ea5e9', '#7c3aed', '#d97706', '#dc2626', '#4f7f93'];
        let cursor = 0;
        const segments = [];
        const legendRows = [];

        items.forEach((row, index) => {
            const count = Number(row.count || 0);
            if (!Number.isFinite(count) || count <= 0) return;

            const percent = Math.max(0, Math.min(100, Number(row.percentage ?? ((count / total) * 100))));
            const color = colors[index % colors.length];
            const start = cursor;
            const end = Math.min(100, cursor + percent);
            if (end > start) {
                segments.push(`${color} ${start}% ${end}%`);
                cursor = end;
            }

            if (legendRows.length < 5) {
                legendRows.push({
                    label: row.label || row.key || 'Not specified',
                    count,
                    percent,
                    color,
                    amount: row.amount !== null && row.amount !== undefined ? formatMoney(row.amount, row.currency || currency) : '',
                });
            }
        });

        if (!segments.length) return '';
        if (cursor < 100) segments.push(`rgba(12, 34, 48, .08) ${cursor}% 100%`);
        const gradient = `conic-gradient(${segments.join(', ')})`;
        const topPercent = legendRows[0]?.percent ?? 0;

        return `
            <div class="breakdown-chart" aria-label="${escapeHtml(`${title} donut chart`)}">
                <div class="breakdown-donut" style="--donut-gradient: ${escapeHtml(gradient)};"><span>${escapeHtml(topPercent.toFixed(topPercent % 1 === 0 ? 0 : 1))}%</span></div>
                <div class="breakdown-legend">
                    ${legendRows.map((row) => `
                        <div class="breakdown-legend-item">
                            <strong><span class="breakdown-swatch" style="--swatch: ${escapeHtml(row.color)};"></span><span>${escapeHtml(row.label)}</span></strong>
                            <span>${escapeHtml(row.count)} · ${escapeHtml(row.percent.toFixed(row.percent % 1 === 0 ? 0 : 1))}%${row.amount ? ` · ${escapeHtml(row.amount)}` : ''}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    function renderBreakdownRow(row, total, currency = 'GBP') {
        const count = Number(row.count || 0);
        const rawPercent = Number(row.percentage ?? (total > 0 ? (count / total) * 100 : 0));
        const percent = Math.max(0, Math.min(100, Number.isFinite(rawPercent) ? rawPercent : 0));
        const amount = row.amount !== null && row.amount !== undefined ? ` · ${formatMoney(row.amount, row.currency || currency)}` : '';

        return `
            <div class="breakdown-row">
                <div class="breakdown-line">
                    <span>${escapeHtml(row.label || row.key || 'Not specified')}</span>
                    <span>${escapeHtml(count)} · ${escapeHtml(percent.toFixed(percent % 1 === 0 ? 0 : 1))}%${escapeHtml(amount)}</span>
                </div>
                <div class="breakdown-track" aria-hidden="true"><span class="breakdown-fill" style="--bar-width: ${percent}%;"></span></div>
            </div>
        `;
    }

    function valueFrom(row, keys, fallback = '') {
        for (const key of keys) {
            const value = key.split('.').reduce((carry, part) => carry && carry[part] !== undefined ? carry[part] : undefined, row);
            if (value !== undefined && value !== null && `${value}` !== '') return value;
        }

        return fallback;
    }

    function renderRecentRegistrationsTable(rows, currency) {
        const items = Array.isArray(rows) ? rows.slice(0, 8) : [];
        if (!items.length) {
            return '<div class="empty">No recent registrations yet.</div>';
        }

        return `
            <div class="management-table-wrap">
                <table class="management-table">
                    <caption>Recent registrations</caption>
                    <thead>
                        <tr><th>Booking</th><th>Member</th><th>Status</th><th>Payment</th></tr>
                    </thead>
                    <tbody>
                        ${items.map((row) => {
                            const reference = valueFrom(row, ['reference', 'booking_reference', 'public_id'], 'Registration');
                            const createdAt = valueFrom(row, ['created_at', 'createdAt'], '');
                            const name = valueFrom(row, ['customer_name', 'member_name', 'name', 'user.name'], 'Member');
                            const email = valueFrom(row, ['customer_email', 'member_email', 'email', 'user.email'], '');
                            const status = statusLabel(valueFrom(row, ['status', 'booking_status'], 'pending'));
                            const attendeesCount = valueFrom(row, ['attendees_count', 'attendee_count', 'quantity'], 0);
                            const paid = valueFrom(row, ['paid_amount', 'paid_total'], 0);
                            const total = valueFrom(row, ['total_amount', 'total'], 0);
                            const balance = valueFrom(row, ['balance_amount', 'balance'], 0);

                            return `
                                <tr>
                                    <td>${escapeHtml(reference)}${createdAt ? `<small>${escapeHtml(formatDateTime(createdAt))}</small>` : ''}</td>
                                    <td>${escapeHtml(name)}${email ? `<small>${escapeHtml(email)}</small>` : ''}</td>
                                    <td>${escapeHtml(status)}<small>${escapeHtml(attendeesCount)} attendee${Number(attendeesCount) === 1 ? '' : 's'}</small></td>
                                    <td>${escapeHtml(formatMoney(paid, currency))} / ${escapeHtml(formatMoney(total, currency))}<small>Balance ${escapeHtml(formatMoney(balance, currency))}</small></td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function renderRecentAttendeesTable(rows) {
        const items = Array.isArray(rows) ? rows.slice(0, 10) : [];
        if (!items.length) {
            return '<div class="empty">No attendee responses yet.</div>';
        }

        return `
            <div class="management-table-wrap">
                <table class="management-table">
                    <caption>Recent attendees</caption>
                    <thead>
                        <tr><th>Attendee</th><th>Contact</th><th>Gender / age</th><th>Bus</th><th>Volunteer</th><th>Company / role</th></tr>
                    </thead>
                    <tbody>
                        ${items.map((row) => {
                            const name = valueFrom(row, ['name', 'full_name', 'attendee_name'], 'Attendee');
                            const ticket = valueFrom(row, ['ticket_type', 'ticket_name', 'ticket.type.name'], '');
                            const email = valueFrom(row, ['email', 'attendee_email'], '');
                            const phone = valueFrom(row, ['phone', 'phone_number'], '');
                            const company = valueFrom(row, ['company'], '');
                            const designation = valueFrom(row, ['designation'], '');
                            const gender = valueFrom(row, ['gender_label'], '') || statusLabel(valueFrom(row, ['gender'], 'not specified'));
                            const age = valueFrom(row, ['age_group_label'], '') || attendeeAgeGroupLabel(valueFrom(row, ['age_group'], 'not_specified'));
                            const bus = freeChurchBusLabel(valueFrom(row, ['free_church_bus_interest'], 'no_thanks')).replace('Interested in FREE church bus: ', '');
                            const volunteer = volunteerDepartmentLabel(valueFrom(row, ['volunteer_department'], 'no_chance_at_the_moment')).replace('Volunteer: ', '');
                            const contact = [email, phone].filter(Boolean).join(' - ');
                            const companyRole = [company, designation].filter(Boolean).join(' - ');

                            return `
                                <tr>
                                    <td>${escapeHtml(name)}${ticket ? `<small>${escapeHtml(ticket)}</small>` : ''}</td>
                                    <td>${escapeHtml(contact || 'Not provided')}</td>
                                    <td>${escapeHtml(gender)}<small>${escapeHtml(age)}</small></td>
                                    <td>${escapeHtml(bus)}</td>
                                    <td>${escapeHtml(volunteer)}</td>
                                    <td>${escapeHtml(companyRole || 'Not provided')}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function renderRegistration(registration) {
        const paymentsDue = Array.isArray(registration.installments) ? registration.installments : [];
        const attendees = Array.isArray(registration.attendees) ? registration.attendees : [];
        const pending = paymentsDue.filter((item) => !['paid', 'completed', 'succeeded'].includes(`${item.status || ''}`.toLowerCase()));
        const paymentRows = paymentsDue.length
            ? paymentsDue.map((item) => {
                const isPending = !['paid', 'completed', 'succeeded'].includes(`${item.status || ''}`.toLowerCase());
                const payButton = isPending && item.public_id
                    ? `<button class="button ghost checkout-button" type="button" data-booking-id="${escapeHtml(registration.public_id)}" data-payment-id="${escapeHtml(item.public_id)}">Pay balance</button>`
                    : '';
                return `<span>${renderMoney(item.amount, item.currency)} · ${escapeHtml(statusLabel(item.status))}</span>${payButton}`;
            }).join('')
            : '<span>No payment is due.</span>';
        const attendeeRows = attendees.length
            ? attendees.map((attendee, index) => {
                const name = attendee.name || `Attendee ${index + 1}`;
                const details = [
                    attendee.gender ? statusLabel(attendee.gender) : '',
                    attendee.age_group ? attendeeAgeGroupLabel(attendee.age_group) : '',
                    attendee.free_church_bus_interest ? freeChurchBusLabel(attendee.free_church_bus_interest) : '',
                    attendee.volunteer_department ? volunteerDepartmentLabel(attendee.volunteer_department) : '',
                ].filter(Boolean).join(' · ');

                return `<span>${escapeHtml(index + 1)}. ${escapeHtml(name)}${details ? ` · ${escapeHtml(details)}` : ''}</span>`;
            }).join('')
            : '';

        return `
            <div class="mini-row">
                <strong>${escapeHtml(registration.event?.name || 'Goshen Retreat registration')}</strong>
                <span>Status: ${escapeHtml(statusLabel(registration.status))} · Paid ${renderMoney(registration.paid_total, registration.currency)} of ${renderMoney(registration.total, registration.currency)}</span>
                ${attendeeRows}
                ${paymentRows}
                ${pending.length ? '<span>Secure checkout opens in your browser for pending payment.</span>' : ''}
            </div>
        `;
    }

    function attendeeAgeGroupLabel(value) {
        const labels = {
            child: 'Child',
            teen: 'Teen',
            young_adult: 'Young adult',
            adult: 'Adult',
            senior: 'Senior',
            not_specified: 'Age group not specified',
        };

        return labels[`${value || ''}`.toLowerCase()] || statusLabel(value);
    }

    function freeChurchBusLabel(value) {
        return `${value || ''}`.toLowerCase() === 'yes'
            ? 'Interested in FREE church bus: Yes'
            : 'Interested in FREE church bus: No thanks';
    }

    function volunteerDepartmentLabel(value) {
        const labels = {
            children_department: 'Volunteer: Children department',
            intercessory: 'Volunteer: Intercessory',
            media: 'Volunteer: Media',
            protocol: 'Volunteer: Protocol',
            sanctuary: 'Volunteer: Sanctuary',
            no_chance_at_the_moment: 'Volunteer: No Chance at the moment',
        };

        return labels[`${value || ''}`.toLowerCase()] || statusLabel(value);
    }

    function renderPaymentTransaction(transaction) {
        const sequence = 'Payment';
        const reference = transaction.reference ? ` · Ref: ${transaction.reference}` : '';

        return `
            <div class="mini-row">
                <strong>${escapeHtml(sequence)} history</strong>
                <span>${escapeHtml(transaction.event?.name || 'Goshen Retreat')} · ${renderMoney(transaction.amount, transaction.currency)}</span>
                <span>Status: ${escapeHtml(statusLabel(transaction.status))}${escapeHtml(reference)}</span>
                <span>${escapeHtml(formatDateTime(transaction.created_at))}</span>
            </div>
        `;
    }

    memberRetreatData.addEventListener('click', (event) => {
        const button = event.target.closest('.checkout-button');
        if (!button) return;

        if (!currentUser) {
            notice('Please sign in before paying for this registration.', 'error');
            return;
        }

        const url = `/api/goshen-retreat/bookings/${encodeURIComponent(button.dataset.bookingId)}/payments/${encodeURIComponent(button.dataset.paymentId)}/checkout`;
        button.disabled = true;
        apiPost(url, authPayload())
            .then((payload) => {
                const checkoutUrl = payload.checkout?.checkout_url;
                if (!checkoutUrl) {
                    throw new Error('Checkout is not available for this payment yet.');
                }
                window.open(checkoutUrl, '_blank', 'noopener,noreferrer');
                notice(payload.message || 'Checkout is ready. Complete payment in the secure browser tab.');
            })
            .catch((error) => notice(error.message, 'error'))
            .finally(() => {
                button.disabled = false;
            });
    });

    function renderTicket(ticket) {
        const qrValue = ticket.qr_encoded || ticket.public_id || '';
        const documentUrls = ticket.document_urls && typeof ticket.document_urls === 'object'
            ? ticket.document_urls
            : {};
        const qrImageUrl = documentUrls.qr || '';
        const checkedIn = ticket.status === 'checked_in' || Boolean(ticket.checked_in_at || ticket.last_checked_in_at);
        const checkedInLabel = ticket.checked_in_at || ticket.last_checked_in_at
            ? formatDateTime(ticket.checked_in_at || ticket.last_checked_in_at)
            : '';
        const documentActions = checkedIn ? '' : [
            documentUrls.pdf ? `<button class="button ghost ticket-document-button" type="button" data-url="${escapeHtml(documentUrls.pdf)}" data-type="pdf" data-ticket="${escapeHtml(ticket.ticket_number || ticket.public_id || 'ticket')}">Download PDF ticket</button>` : '',
            documentUrls.ics ? `<button class="button ghost ticket-document-button" type="button" data-url="${escapeHtml(documentUrls.ics)}" data-type="ics" data-ticket="${escapeHtml(ticket.ticket_number || ticket.public_id || 'ticket')}">Add to calendar</button>` : '',
            documentUrls.qr ? `<button class="button ghost ticket-document-button" type="button" data-url="${escapeHtml(documentUrls.qr)}" data-type="qr" data-ticket="${escapeHtml(ticket.ticket_number || ticket.public_id || 'ticket')}">Download QR</button>` : '',
        ].filter(Boolean).join('');

        return `
            <div class="mini-row ticket-card">
                <strong>Ticket ${escapeHtml(ticket.ticket_number || ticket.public_id || '')}</strong>
                <span>${escapeHtml(ticket.event_name || 'Goshen Retreat')} · ${escapeHtml(ticket.attendee_name || 'Attendee')}</span>
                <div class="ticket-status ${checkedIn ? 'checked' : ''}">
                    ${checkedIn ? 'Checked in' : 'Not checked in yet'}
                    <small>${checkedIn ? escapeHtml(checkedInLabel) : 'Keep this QR ready for entrance validation.'}</small>
                </div>
                ${documentActions ? `<div class="ticket-actions">${documentActions}</div>` : ''}
                ${qrImageUrl && !checkedIn ? `
                    <div class="ticket-qr">
                        <img data-secure-src="${escapeHtml(qrImageUrl)}" alt="Ticket QR code" referrerpolicy="no-referrer">
                        <div>
                            <strong>Check-in QR</strong>
                            <span>Show this code at the retreat entrance when requested.</span>
                            <code>${escapeHtml(qrValue)}</code>
                            <button class="button ghost copy-qr-button" type="button" data-qr="${escapeHtml(qrValue)}">Copy QR payload</button>
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
    }

    function loadTicketQrImages() {
        if (!currentUser?.api_token) return;

        memberRetreatData.querySelectorAll('img[data-secure-src]').forEach((image) => {
            const url = image.dataset.secureSrc;
            if (!url || image.dataset.loaded === '1') return;

            authenticatedBlobUrl(url, authPayload())
                .then((objectUrl) => {
                    if (image.dataset.objectUrl) {
                        URL.revokeObjectURL(image.dataset.objectUrl);
                    }
                    image.src = objectUrl;
                    image.dataset.objectUrl = objectUrl;
                    image.dataset.loaded = '1';
                })
                .catch(() => {
                    image.alt = 'Ticket QR could not be loaded. Use Download QR to retry.';
                    image.removeAttribute('data-secure-src');
                });
        });
    }

    memberRetreatData.addEventListener('click', (event) => {
        const button = event.target.closest('.copy-qr-button');
        if (!button) return;

        navigator.clipboard?.writeText(button.dataset.qr || '')
            .then(() => notice('Ticket QR payload copied.'))
            .catch(() => notice('Unable to copy this QR payload on this browser.', 'error'));
    });

    memberRetreatData.addEventListener('click', (event) => {
        const button = event.target.closest('.ticket-document-button');
        if (!button) return;

        if (!currentUser?.api_token) {
            notice('Please sign in again before downloading ticket documents.', 'error');
            return;
        }

        const type = button.dataset.type || 'ticket';
        const ticketName = (button.dataset.ticket || 'ticket').replace(/[^a-z0-9_-]+/gi, '-');
        const extension = type === 'qr'
            ? ((button.dataset.url || '').includes('.svg') ? 'svg' : 'png')
            : type;
        button.disabled = true;
        downloadAuthenticatedDocument(button.dataset.url, authPayload(), `${ticketName}.${extension}`)
            .then(() => notice('Ticket document downloaded.'))
            .catch((error) => notice(error.message, 'error'))
            .finally(() => {
                button.disabled = false;
            });
    });

    function renderAllocation(allocation) {
        const details = [
            allocation.building ? `Building: ${allocation.building}` : '',
            allocation.room ? `Room: ${allocation.room}` : '',
            allocation.bed ? `Bed: ${allocation.bed}` : '',
            allocation.ticket_number ? `Ticket: ${allocation.ticket_number}` : '',
            allocation.check_in_note ? `Note: ${allocation.check_in_note}` : '',
        ].filter(Boolean);
        const visibleDetails = allocation.attendee_visible_details && typeof allocation.attendee_visible_details === 'object'
            ? Object.entries(allocation.attendee_visible_details)
                .filter(([, value]) => value !== null && value !== undefined && `${value}` !== '')
                .map(([key, value]) => `${key.replace(/_/g, ' ')}: ${value}`)
            : [];

        return `
            <div class="mini-row">
                <strong>Accommodation allocation</strong>
                <span>${escapeHtml(allocation.event?.name || 'Goshen Retreat')} · ${escapeHtml(statusLabel(allocation.status || 'assigned'))}</span>
                ${(details.length || visibleDetails.length)
                    ? [...details, ...visibleDetails].map((detail) => `<span>${escapeHtml(detail)}</span>`).join('')
                    : '<span>Allocation details will be shown here.</span>'}
                ${allocation.assigned_at ? `<span>Assigned ${escapeHtml(formatDateTime(allocation.assigned_at))}</span>` : ''}
            </div>
        `;
    }

    function renderGivingHistoryItem(donation) {
        const category = donation.category?.name || donation.purpose || 'Giving';
        const date = donation.paid_at || donation.created_at;

        return `
            <div class="mini-row">
                <strong>${escapeHtml(category)}</strong>
                <span>${renderMoney(donation.amount, donation.currency)} · ${escapeHtml(statusLabel(donation.status))}</span>
                <span>${donation.reference ? `Reference: ${escapeHtml(donation.reference)}` : 'Reference will appear after checkout starts.'}</span>
                <span>${escapeHtml(formatDateTime(date))}${donation.anonymous ? ' · Anonymous' : ''}</span>
            </div>
        `;
    }

    function loadGroups() {
        fetch('/api/church_groups', { headers: { 'Accept': 'application/json' } })
            .then((response) => response.ok ? response.json() : Promise.reject(response))
            .then((payload) => {
                const groups = Array.isArray(payload.data) ? payload.data : (Array.isArray(payload.groups) ? payload.groups : []);
                const activeGroups = groups.filter((group) => group.is_active !== false);
                registerGroup.innerHTML = '<option value="">Select group</option>' + activeGroups
                    .map((group) => `<option value="${escapeHtml(group.id)}">${escapeHtml(group.name)}</option>`)
                    .join('');
            })
            .catch(() => {
                registerGroup.innerHTML = '<option value="">Groups could not be loaded</option>';
            });
    }

    function loadEvents(force = false) {
        if (force) {
            refreshRetreatSetup.disabled = true;
            retreatSetupData.innerHTML = '<div class="empty">Refreshing retreat setup...</div>';
        }

        return fetch('/api/goshen-retreat/events', { headers: { 'Accept': 'application/json' } })
            .then((response) => response.ok ? response.json() : Promise.reject(response))
            .then((payload) => {
                renderEvents(Array.isArray(payload.data) ? payload.data : []);
                return payload;
            })
            .catch(() => {
                eventsNode.innerHTML = '<div class="empty">Retreat information could not be loaded right now.</div>';
                if (force) {
                    retreatSetupData.innerHTML = '<div class="empty">Retreat setup could not be refreshed right now.</div>';
                }
            })
            .finally(() => {
                refreshRetreatSetup.disabled = false;
            });
    }

    loadEvents();

    loadGroups();
    restoreUser();
    renderAuth();

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/member-sw.js', { updateViaCache: 'none' })
            .then((registration) => registration.update().catch(() => {}))
            .catch(() => {});
    }
</script>
</body>
</html>
