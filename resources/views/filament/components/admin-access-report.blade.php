<div class="space-y-4 text-sm">
    <p>{{ $report['note'] }}</p>
    <p><strong>Admin roles:</strong> {{ collect($report['roles'])->pluck('name')->join(', ') ?: 'None' }}</p>
    @if ($report['super_admin'])
        <p>Super Admin has full admin permission access.</p>
    @endif
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead><tr><th class="p-2">Permission</th><th class="p-2">Granted by</th></tr></thead>
            <tbody>
                @forelse ($report['permissions'] as $permission)
                    <tr class="border-t">
                        <td class="p-2">{{ $permission['label'] }}@unless ($permission['catalogued']) <span>(outside current catalog; retained)</span>@endunless</td>
                        <td class="p-2">{{ collect($permission['roles'])->when($permission['direct'], fn ($sources) => $sources->push('Individual grant'))->join(', ') }}</td>
                    </tr>
                @empty
                    <tr><td class="p-2" colspan="2">No explicit grants.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($report['addons'])
        <p><strong>Add-on availability:</strong> Add-on system {{ $report['addon_system_enabled'] ? 'enabled' : 'disabled' }}.</p>
        <ul class="list-inside list-disc">
            @foreach ($report['addons'] as $addon)
                <li>{{ $addon['name'] }}: {{ $addon['status'] }} (grants: {{ implode(', ', $addon['granted_permissions']) }})</li>
            @endforeach
        </ul>
        <p>These are installed statuses. A grant does not activate an add-on or guarantee its runtime health.</p>
    @endif
    @if ($report['hidden_menus'])
        <p><strong>Hidden menus:</strong></p>
        <ul class="list-inside list-disc">
            @foreach ($report['hidden_menus'] as $hide)
                <li>{{ collect(\App\Support\AdminMenuRegistry::items())->firstWhere('key', $hide['menu_key'])['label'] ?? class_basename($hide['menu_key']) }} (role #{{ $hide['role_id'] }})</li>
            @endforeach
        </ul>
    @endif
</div>
