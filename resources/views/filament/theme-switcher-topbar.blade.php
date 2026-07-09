@if (filament()->auth()->check() && filament()->hasDarkMode() && (! filament()->hasDarkModeForced()))
    <div class="com-topbar-theme-switcher" aria-label="Theme preference">
        <x-filament-panels::theme-switcher />
    </div>
@endif

