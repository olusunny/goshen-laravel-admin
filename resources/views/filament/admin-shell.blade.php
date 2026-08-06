<style>
    :root {
        --goshen-admin-sidebar: #f7fbf9;
        --goshen-admin-sidebar-deep: #eef6f2;
        --goshen-admin-sidebar-active: #dff2eb;
        --goshen-admin-sidebar-active-text: #0f513c;
        --goshen-admin-sidebar-hover: rgba(15, 81, 60, .08);
        --goshen-admin-sidebar-line: rgba(15, 81, 60, .16);
        --goshen-admin-sidebar-muted: #5f716b;
        --goshen-admin-sidebar-text: #18342b;
        --goshen-admin-sidebar-search-bg: rgba(255, 255, 255, .84);
        --goshen-admin-sidebar-search-border: rgba(15, 81, 60, .18);
        --goshen-admin-sidebar-search-text: #18342b;
        --goshen-admin-sidebar-search-placeholder: #657770;
        --goshen-admin-amber: #f59e0b;
        --goshen-admin-soft: #eef8f5;
        --goshen-admin-canvas: #f6f8f7;
        --goshen-admin-surface: #ffffff;
        --goshen-admin-surface-muted: #f8faf9;
        --goshen-admin-border: rgba(15, 81, 60, .14);
        --goshen-admin-border-soft: rgba(15, 81, 60, .08);
        --goshen-admin-text: #17352d;
        --goshen-admin-text-muted: #5e716a;
        --goshen-admin-text-subtle: #76857f;
        --goshen-admin-space-1: .25rem;
        --goshen-admin-space-2: .5rem;
        --goshen-admin-space-3: .75rem;
        --goshen-admin-space-4: 1rem;
        --goshen-admin-space-5: 1.25rem;
        --goshen-admin-space-6: 1.5rem;
        --goshen-admin-space-8: 2rem;
        --goshen-admin-radius-sm: .35rem;
        --goshen-admin-radius-md: .55rem;
        --goshen-admin-focus-ring: #a16207;
        --goshen-admin-focus-offset: 2px;
        --goshen-admin-transition-fast: 160ms cubic-bezier(.23, 1, .32, 1);
    }

    .dark {
        --goshen-admin-sidebar: #20342e;
        --goshen-admin-sidebar-deep: #172821;
        --goshen-admin-sidebar-active: #176451;
        --goshen-admin-sidebar-active-text: #fff;
        --goshen-admin-sidebar-hover: rgba(23, 100, 81, .65);
        --goshen-admin-sidebar-line: rgba(232, 244, 239, .24);
        --goshen-admin-sidebar-muted: rgba(232, 244, 239, .68);
        --goshen-admin-sidebar-text: rgba(255, 255, 255, .88);
        --goshen-admin-sidebar-search-bg: rgba(255, 255, 255, .12);
        --goshen-admin-sidebar-search-border: rgba(255, 255, 255, .08);
        --goshen-admin-sidebar-search-text: #fff;
        --goshen-admin-sidebar-search-placeholder: rgba(255, 255, 255, .82);
        --goshen-admin-focus-ring: rgba(253, 230, 138, .95);
        --goshen-admin-canvas: #101b18;
        --goshen-admin-surface: #172521;
        --goshen-admin-surface-muted: #1c2d28;
        --goshen-admin-border: rgba(232, 244, 239, .14);
        --goshen-admin-border-soft: rgba(232, 244, 239, .08);
        --goshen-admin-text: #f3f8f5;
        --goshen-admin-text-muted: #c2d1ca;
        --goshen-admin-text-subtle: #99aca4;
    }

    /* Shared foundations for custom Filament pages. Apply these classes opt-in. */
    .goshen-admin-section {
        display: grid;
        gap: var(--goshen-admin-space-4);
    }

    .goshen-admin-section-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: var(--goshen-admin-space-4);
    }

    .goshen-admin-section-heading {
        min-width: 0;
        color: rgb(15 23 42);
        font-size: 1.125rem;
        font-weight: 750;
        line-height: 1.35;
    }

    .dark .goshen-admin-section-heading {
        color: rgb(248 250 252);
    }

    .goshen-admin-section-copy {
        max-width: 72ch;
        color: rgb(100 116 139);
        font-size: .9375rem;
        line-height: 1.55;
    }

    .dark .goshen-admin-section-copy {
        color: rgb(203 213 225);
    }

    .goshen-admin-state {
        display: grid;
        justify-items: start;
        gap: var(--goshen-admin-space-2);
        padding: var(--goshen-admin-space-5);
        border: 1px solid rgb(226 232 240);
        border-radius: var(--goshen-admin-radius-md);
        background: rgb(248 250 252);
    }

    .dark .goshen-admin-state {
        border-color: rgba(148, 163, 184, .24);
        background: rgba(15, 23, 42, .35);
    }

    .goshen-admin-state-title {
        color: rgb(30 41 59);
        font-size: 1rem;
        font-weight: 750;
        line-height: 1.4;
    }

    .dark .goshen-admin-state-title {
        color: rgb(248 250 252);
    }

    .goshen-admin-state-copy {
        color: rgb(100 116 139);
        font-size: .9375rem;
        line-height: 1.55;
    }

    .dark .goshen-admin-state-copy {
        color: rgb(203 213 225);
    }

    .goshen-admin-table-wrap {
        width: 100%;
        overflow-x: auto;
        overscroll-behavior-inline: contain;
        scrollbar-color: rgb(148 163 184) transparent;
        scrollbar-width: thin;
    }

    .goshen-admin-table-wrap > table {
        width: 100%;
    }

    .goshen-admin-table-wrap[data-goshen-table-min-width='compact'] > table {
        min-width: 42rem;
    }

    .goshen-admin-table-wrap[data-goshen-table-min-width='wide'] > table {
        min-width: 64rem;
    }

    .fi-main-ctn {
        background: var(--goshen-admin-canvas);
    }

    .fi-page {
        max-width: 100rem;
    }

    .fi-page-header-heading,
    .fi-section-header-heading,
    .fi-ta-header-heading {
        color: var(--goshen-admin-text);
        font-weight: 760;
        letter-spacing: 0;
        text-wrap: balance;
    }

    .fi-page-header-description,
    .fi-section-header-description,
    .fi-ta-header-description,
    .fi-fo-field-wrp-hint {
        color: var(--goshen-admin-text-muted);
        text-wrap: pretty;
    }

    .fi-section,
    .fi-ta-ctn {
        border-color: var(--goshen-admin-border);
        border-radius: .5rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .05), 0 10px 24px rgba(15, 23, 42, .035);
    }

    .dark .fi-section,
    .dark .fi-ta-ctn {
        border-color: var(--goshen-admin-border);
        box-shadow: 0 0 0 1px var(--goshen-admin-border-soft);
    }

    .fi-section-header,
    .fi-ta-header-ctn {
        background: var(--goshen-admin-surface-muted);
    }

    .fi-ta-header-toolbar {
        gap: var(--goshen-admin-space-3);
    }

    .fi-ta-table > thead > tr {
        background: var(--goshen-admin-surface-muted);
    }

    .fi-ta-table > tbody > tr {
        transition: background var(--goshen-admin-transition-fast);
    }

    @media (hover: hover) {
        .fi-ta-table > tbody > tr:hover {
            background: color-mix(in srgb, var(--goshen-admin-soft) 58%, transparent);
        }
    }

    .dark .fi-ta-table > tbody > tr:hover {
        background: rgba(110, 231, 183, .055);
    }

    .fi-ta-table :is(.fi-ta-header-cell-label, .fi-ta-cell-content) {
        overflow-wrap: anywhere;
    }

    .fi-ta-table :is(.fi-ta-cell-content, .fi-pagination-overview) {
        font-variant-numeric: tabular-nums;
    }

    .fi-ta-empty-state {
        min-height: 15rem;
        background: var(--goshen-admin-surface-muted);
    }

    .fi-ta-empty-state-heading {
        color: var(--goshen-admin-text);
        font-weight: 750;
    }

    .fi-ta-empty-state-description {
        color: var(--goshen-admin-text-muted);
    }

    .fi-fo-field-wrp-label {
        color: var(--goshen-admin-text);
        font-weight: 700;
    }

    .fi-input-wrp {
        border-radius: var(--goshen-admin-radius-md);
    }

    .fi-input-wrp:focus-within {
        border-color: var(--goshen-admin-focus-ring);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--goshen-admin-focus-ring) 20%, transparent);
    }

    .fi-btn {
        min-height: 2.5rem;
        font-weight: 700;
        transition: background var(--goshen-admin-transition-fast), border-color var(--goshen-admin-transition-fast), color var(--goshen-admin-transition-fast), transform 120ms ease-out;
    }

    .fi-btn:active {
        transform: scale(.98);
    }

    .goshen-dashboard-summary {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--goshen-admin-space-4);
        width: 100%;
    }

    .goshen-dashboard-summary-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--goshen-admin-space-4);
        min-height: 8.5rem;
        padding: var(--goshen-admin-space-5);
        border: 1px solid var(--goshen-admin-border);
        border-radius: .5rem;
        background: var(--goshen-admin-surface);
        box-shadow: 0 1px 2px rgba(15, 23, 42, .05), 0 10px 24px rgba(15, 23, 42, .035);
    }

    .goshen-dashboard-summary-content {
        display: flex;
        align-items: center;
        gap: var(--goshen-admin-space-3);
        min-width: 0;
    }

    .goshen-dashboard-summary-icon {
        display: inline-flex;
        flex: 0 0 auto;
        align-items: center;
        justify-content: center;
        width: 2.75rem;
        height: 2.75rem;
        border-radius: .5rem;
        background: color-mix(in srgb, var(--goshen-dashboard-accent) 14%, transparent);
        color: var(--goshen-dashboard-accent);
    }

    .goshen-dashboard-summary-icon svg {
        width: 1.5rem;
        height: 1.5rem;
    }

    .goshen-dashboard-summary-label {
        color: var(--goshen-admin-text);
        font-size: 1rem;
        font-weight: 750;
        line-height: 1.3;
    }

    .goshen-dashboard-summary-copy {
        max-width: 30ch;
        margin-top: var(--goshen-admin-space-1);
        color: var(--goshen-admin-text-muted);
        font-size: .8125rem;
        line-height: 1.45;
    }

    .goshen-dashboard-summary-metric {
        flex: 0 0 auto;
        padding-inline-start: var(--goshen-admin-space-4);
        border-inline-start: 1px solid var(--goshen-admin-border-soft);
        color: var(--goshen-dashboard-accent);
        font-variant-numeric: tabular-nums;
        text-align: end;
    }

    .goshen-dashboard-summary-metric div {
        font-size: 1.75rem;
        font-weight: 800;
        line-height: 1;
    }

    .goshen-dashboard-summary-metric span {
        color: var(--goshen-admin-text-subtle);
        font-size: .6875rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .goshen-admin-attendee-list {
        display: grid;
        gap: var(--goshen-admin-space-4);
    }

    .goshen-admin-attendee-card {
        border: 1px solid var(--goshen-admin-border);
        border-radius: .5rem;
        padding: var(--goshen-admin-space-5);
        background: var(--goshen-admin-surface-muted);
    }

    .goshen-admin-attendee-heading {
        display: flex;
        align-items: center;
        gap: var(--goshen-admin-space-3);
        margin-bottom: var(--goshen-admin-space-4);
    }

    .goshen-admin-attendee-index {
        display: inline-flex;
        flex: 0 0 auto;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border-radius: 999px;
        background: var(--goshen-admin-amber);
        color: #1c1917;
        font-size: .8125rem;
        font-weight: 800;
    }

    .goshen-admin-attendee-name {
        margin: 0;
        color: var(--goshen-admin-text);
        font-size: 1rem;
        font-weight: 750;
        line-height: 1.35;
    }

    .goshen-admin-attendee-details {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: var(--goshen-admin-space-5);
    }

    .goshen-admin-attendee-column {
        display: grid;
        gap: var(--goshen-admin-space-3);
        margin: 0;
    }

    .goshen-admin-attendee-row {
        display: grid;
        grid-template-columns: minmax(0, .85fr) minmax(0, 1.15fr);
        gap: var(--goshen-admin-space-3);
        align-items: start;
        padding-bottom: var(--goshen-admin-space-3);
        border-bottom: 1px solid var(--goshen-admin-border-soft);
    }

    .goshen-admin-attendee-row dt {
        color: var(--goshen-admin-text-subtle);
        font-size: .8125rem;
        font-weight: 650;
        line-height: 1.4;
    }

    .goshen-admin-attendee-row dd {
        margin: 0;
        overflow-wrap: anywhere;
        color: var(--goshen-admin-text);
        font-size: .875rem;
        font-weight: 700;
        line-height: 1.4;
        text-align: end;
    }

    :where(.fi-main, .fi-sidebar) :where(a, button, input, select, textarea, [tabindex]):focus-visible {
        outline: 2px solid var(--goshen-admin-focus-ring);
        outline-offset: var(--goshen-admin-focus-offset);
    }

    @media (max-width: 640px) {
        .goshen-admin-section-header {
            align-items: stretch;
            flex-direction: column;
            gap: var(--goshen-admin-space-3);
        }

        .goshen-admin-state {
            padding: var(--goshen-admin-space-4);
        }

        .fi-page-header,
        .fi-ta-header {
            gap: var(--goshen-admin-space-3);
        }

        .fi-ta-header :is(.fi-ta-actions, .fi-ta-header-toolbar > *) {
            width: 100%;
        }

        .fi-ta-header .fi-btn,
        .fi-ta-header .fi-input-wrp {
            width: 100%;
        }

        .goshen-admin-attendee-details {
            grid-template-columns: 1fr;
            gap: var(--goshen-admin-space-3);
        }

        .goshen-dashboard-summary {
            grid-template-columns: 1fr;
        }

        .goshen-admin-attendee-row {
            grid-template-columns: 1fr;
            gap: var(--goshen-admin-space-1);
        }

        .goshen-admin-attendee-row dd {
            text-align: start;
        }
    }

    @media (pointer: coarse) {
        .fi-btn,
        .fi-icon-btn,
        .fi-pagination-item-btn,
        .fi-sidebar .fi-sidebar-item-btn {
            min-height: 2.75rem;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        :where(.fi-main, .fi-sidebar) *,
        :where(.fi-main, .fi-sidebar) *::before,
        :where(.fi-main, .fi-sidebar) *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }

    .fi-sidebar.fi-main-sidebar {
        background: var(--goshen-admin-sidebar) !important;
        color: var(--goshen-admin-sidebar-text);
        border-inline-end: 1px solid var(--goshen-admin-sidebar-line);
    }

    .fi-sidebar .fi-sidebar-header-ctn,
    .fi-sidebar .fi-sidebar-footer,
    .fi-sidebar .fi-sidebar-nav {
        background: transparent !important;
    }

    .fi-sidebar .fi-sidebar-header {
        padding: var(--goshen-admin-space-5) 1.2rem .8rem;
    }

    .fi-sidebar .fi-logo {
        color: var(--goshen-admin-sidebar-text);
    }

    .fi-sidebar .fi-sidebar-nav {
        padding: .4rem 1rem 1.25rem;
    }

    .goshen-sidebar-search {
        position: relative;
        display: flex;
        align-items: center;
        gap: .7rem;
        margin: 0 0 1.15rem;
        padding: .82rem .95rem;
        border-radius: var(--goshen-admin-radius-md);
        background: var(--goshen-admin-sidebar-search-bg);
        border: 1px solid var(--goshen-admin-sidebar-search-border);
    }

    .goshen-sidebar-search svg {
        width: 1.15rem;
        height: 1.15rem;
        color: var(--goshen-admin-sidebar-search-text);
        flex: none;
    }

    .goshen-sidebar-search input {
        width: 100%;
        border: 0;
        background: transparent;
        color: var(--goshen-admin-sidebar-search-text);
        font-size: .95rem;
        font-weight: 650;
    }

    .goshen-sidebar-search input::placeholder {
        color: var(--goshen-admin-sidebar-search-placeholder);
    }

    .goshen-sidebar-search:focus-within {
        border-color: var(--goshen-admin-focus-ring);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--goshen-admin-focus-ring) 20%, transparent);
    }

    .fi-sidebar .fi-sidebar-nav-groups {
        gap: .65rem;
    }

    .fi-sidebar .fi-sidebar-group-btn {
        min-height: 2.85rem;
        padding: .55rem .75rem;
        border-radius: .35rem;
        color: var(--goshen-admin-sidebar-text);
    }

    .fi-sidebar .fi-sidebar-group.fi-active > .fi-sidebar-group-btn,
    .fi-sidebar .fi-sidebar-group-btn:hover {
        background: var(--goshen-admin-sidebar-hover);
    }

    .fi-sidebar .fi-sidebar-group-label {
        color: var(--goshen-admin-sidebar-muted);
        font-size: .72rem;
        font-weight: 900;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .fi-sidebar .fi-sidebar-group-collapse-btn {
        color: var(--goshen-admin-sidebar-muted);
    }

    .fi-sidebar .fi-sidebar-group-items,
    .fi-sidebar .fi-sidebar-sub-group-items {
        display: grid;
        gap: .18rem;
        margin-top: .28rem;
    }

    .fi-sidebar .fi-sidebar-item-btn {
        min-height: 2.65rem;
        padding: .52rem .72rem;
        border-radius: .35rem;
        color: var(--goshen-admin-sidebar-text) !important;
        transition: background var(--goshen-admin-transition-fast), color var(--goshen-admin-transition-fast);
    }

    .fi-sidebar .fi-sidebar-item-btn:hover,
    .fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn,
    .fi-sidebar .fi-sidebar-item-has-active-child-items > .fi-sidebar-item-btn {
        background: var(--goshen-admin-sidebar-active);
        color: var(--goshen-admin-sidebar-active-text) !important;
    }

    .fi-sidebar .fi-sidebar-item-icon {
        color: currentColor !important;
    }

    .fi-sidebar .fi-sidebar-item-label {
        color: currentColor !important;
        font-size: .94rem;
        font-weight: 750;
        letter-spacing: 0;
    }

    .fi-sidebar .fi-sidebar-item-grouped-border-part,
    .fi-sidebar .fi-sidebar-item-grouped-border-part-not-first,
    .fi-sidebar .fi-sidebar-item-grouped-border-part-not-last {
        background: var(--goshen-admin-sidebar-line);
    }

    .goshen-nav-hidden {
        display: none !important;
    }

    .fi-sidebar.goshen-searching .fi-sidebar-group-items {
        display: grid !important;
    }

    @media (min-width: 1024px) {
        .fi-sidebar .fi-sidebar-header {
            padding: 1rem .85rem .65rem;
        }

        .fi-sidebar .fi-logo .com-admin-logo-image {
            max-width: 10.5rem;
            max-height: 2.5rem;
        }

        .fi-sidebar .fi-sidebar-nav {
            padding: .35rem .75rem 1rem;
        }

        .goshen-sidebar-search {
            gap: .55rem;
            margin-bottom: .95rem;
            padding: .65rem .75rem;
            border-radius: .5rem;
        }

        .goshen-sidebar-search input {
            font-size: .9rem;
        }

        .fi-sidebar .fi-sidebar-nav-groups {
            gap: .5rem;
        }

        .fi-sidebar .fi-sidebar-group-btn {
            min-height: 2.5rem;
            column-gap: .55rem;
            padding: .45rem .55rem;
        }

        .fi-sidebar .fi-sidebar-group-label {
            font-size: .7rem;
            letter-spacing: .08em;
        }

        .fi-sidebar .fi-sidebar-item-btn {
            min-height: 2.4rem;
            column-gap: .55rem;
            padding: .45rem .55rem;
        }

        .fi-sidebar .fi-sidebar-item-label {
            font-size: .88rem;
            font-weight: 720;
        }
    }

    .goshen-settings-tabs.fi-sc-tabs.fi-vertical {
        display: grid;
        grid-template-columns: minmax(240px, .34fr) minmax(0, 1fr);
        gap: 2rem;
        align-items: start;
    }

    .goshen-settings-tabs.fi-sc-tabs.fi-vertical > .fi-tabs {
        display: grid;
        gap: .95rem;
        padding-inline-end: 1.4rem;
        border-inline-end: 1px solid rgba(148, 163, 184, .22);
    }

    .goshen-settings-tabs .fi-tabs-item {
        justify-content: flex-start;
        min-height: 4.4rem;
        width: 100%;
        padding: .95rem 1rem;
        border: 1px solid rgba(148, 163, 184, .24);
        border-radius: .45rem;
        background: #fff;
        color: #5f6873;
        box-shadow: none;
    }

    .dark .goshen-settings-tabs .fi-tabs-item {
        background: rgba(15, 23, 42, .55);
        border-color: rgba(148, 163, 184, .22);
        color: #cbd5e1;
    }

    .goshen-settings-tabs .fi-tabs-item svg {
        width: 1.35rem;
        height: 1.35rem;
        color: #77808a;
    }

    .goshen-settings-tabs .fi-tabs-item-label {
        font-size: 1.02rem;
        font-weight: 850;
    }

    .goshen-settings-tabs .fi-tabs-item.fi-active {
        border-color: rgba(16, 185, 129, .25);
        background: #e7f4ef;
        color: #047857;
        box-shadow: inset .22rem 0 0 #10b981;
    }

    .dark .goshen-settings-tabs .fi-tabs-item.fi-active {
        background: rgba(16, 185, 129, .18);
        color: #6ee7b7;
    }

    .goshen-settings-tabs .fi-tabs-item.fi-active svg {
        color: currentColor;
    }

    @media (max-width: 900px) {
        .goshen-settings-tabs.fi-sc-tabs.fi-vertical {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .goshen-settings-tabs.fi-sc-tabs.fi-vertical > .fi-tabs {
            padding-inline-end: 0;
            border-inline-end: 0;
        }
    }
</style>
