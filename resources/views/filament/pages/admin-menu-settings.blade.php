<x-filament-panels::page>
    <style>
        .ams-page { display:grid; gap:18px; }
        .ams-panel { border:1px solid #e5e7eb; border-radius:16px; background:#fff; padding:20px; box-shadow:0 14px 34px rgba(15,23,42,.08); }
        .dark .ams-panel { border-color:rgba(148,163,184,.24); background:#111827; box-shadow:0 18px 44px rgba(0,0,0,.28); }
        .ams-title { margin:0; color:#111827; font-size:24px; font-weight:900; line-height:1.2; }
        .dark .ams-title { color:#f8fafc; }
        .ams-copy { margin:8px 0 0; color:#667085; font-size:14px; line-height:1.55; }
        .dark .ams-copy { color:#a8b0bd; }
        .ams-empty { margin-top:16px; border:1px dashed #d0d5dd; border-radius:14px; padding:16px; color:#667085; }
        .dark .ams-empty { border-color:rgba(148,163,184,.32); color:#a8b0bd; }
        .ams-table-wrap { margin-top:18px; overflow:auto; border:1px solid #e5e7eb; border-radius:14px; }
        .dark .ams-table-wrap { border-color:rgba(148,163,184,.24); }
        .ams-table { width:100%; min-width:860px; border-collapse:collapse; font-size:14px; }
        .ams-table th, .ams-table td { border-bottom:1px solid #edf2f7; padding:12px; text-align:left; vertical-align:middle; }
        .dark .ams-table th, .dark .ams-table td { border-bottom-color:rgba(148,163,184,.18); }
        .ams-table th { background:#f8fafc; color:#334155; font-size:12px; letter-spacing:.02em; text-transform:uppercase; }
        .dark .ams-table th { background:#0b1220; color:#cbd5e1; }
        .ams-menu-label { color:#111827; font-weight:850; }
        .dark .ams-menu-label { color:#f8fafc; }
        .ams-menu-meta { color:#667085; font-size:12px; margin-top:3px; }
        .dark .ams-menu-meta { color:#a8b0bd; }
        .ams-group { display:inline-flex; align-items:center; min-height:24px; border-radius:999px; padding:3px 9px; background:#fff7ed; color:#9a3412; font-weight:800; font-size:12px; }
        .dark .ams-group { background:rgba(251,146,60,.14); color:#fdba74; }
        .ams-check { display:flex; justify-content:center; }
        .ams-check input { width:18px; height:18px; accent-color:#f59e0b; }
        .ams-actions { margin-top:18px; display:flex; justify-content:flex-end; }
        .ams-button { min-height:42px; border:0; border-radius:12px; padding:10px 16px; background:#f59e0b; color:#111827; font-weight:900; cursor:pointer; }
    </style>

    <form wire:submit.prevent="save" class="ams-page">
        <section class="ams-panel">
            <h2 class="ams-title">Role-Based Admin Menu Visibility</h2>
            <p class="ams-copy">
                Choose which admin navigation items are visible to each admin role. This only controls the menu:
                underlying resource and page permissions still decide what a user can access.
            </p>

            @if (empty($roles))
                <div class="ams-empty">Create at least one non-super-admin web role before configuring menu visibility.</div>
            @elseif (empty($items))
                <div class="ams-empty">No admin menu items were found.</div>
            @else
                <div class="ams-table-wrap">
                    <table class="ams-table">
                        <thead>
                            <tr>
                                <th style="width:160px;">Group</th>
                                <th>Menu</th>
                                @foreach ($roles as $role)
                                    <th style="min-width:140px; text-align:center;">{{ str($role['name'])->headline() }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                <tr>
                                    <td><span class="ams-group">{{ $item['group'] }}</span></td>
                                    <td>
                                        <div class="ams-menu-label">{{ $item['label'] }}</div>
                                        <div class="ams-menu-meta">{{ str($item['type'])->headline() }}</div>
                                    </td>
                                    @foreach ($roles as $role)
                                        <td>
                                            <label class="ams-check" title="Visible for {{ $role['name'] }}">
                                                <input
                                                    type="checkbox"
                                                    wire:model.defer="visibility.{{ $role['id'] }}.{{ $item['hash'] }}"
                                                >
                                            </label>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="ams-actions">
                    <button type="submit" class="ams-button">Save menu visibility</button>
                </div>
            @endif
        </section>
    </form>
</x-filament-panels::page>
