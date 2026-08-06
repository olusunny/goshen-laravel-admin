<div class="goshen-sidebar-search" x-show="$store.sidebar.isOpen" x-transition>
    <x-filament::icon icon="heroicon-o-magnifying-glass" />
    <input
        type="search"
        placeholder="Search menu"
        autocomplete="off"
        data-goshen-menu-search
        aria-label="Search menu"
    >
    <p class="fi-sr-only" role="status" aria-live="polite" data-goshen-menu-search-status></p>
</div>
