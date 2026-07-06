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
    }

    .fi-sidebar.fi-main-sidebar {
        background:
            radial-gradient(circle at 90% 10%, rgba(245, 158, 11, .12), transparent 28%),
            linear-gradient(180deg, var(--goshen-admin-sidebar), var(--goshen-admin-sidebar-deep)) !important;
        color: var(--goshen-admin-sidebar-text);
        border-inline-end: 1px solid var(--goshen-admin-sidebar-line);
    }

    .fi-sidebar .fi-sidebar-header-ctn,
    .fi-sidebar .fi-sidebar-footer,
    .fi-sidebar .fi-sidebar-nav {
        background: transparent !important;
    }

    .fi-sidebar .fi-sidebar-header {
        padding: 1.25rem 1.2rem .8rem;
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
        border-radius: .55rem;
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
        outline: 0;
        background: transparent;
        color: var(--goshen-admin-sidebar-search-text);
        font-size: .95rem;
        font-weight: 650;
    }

    .goshen-sidebar-search input::placeholder {
        color: var(--goshen-admin-sidebar-search-placeholder);
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
        transition: background .16s ease, color .16s ease;
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
