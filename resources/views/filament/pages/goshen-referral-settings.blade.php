<x-filament-panels::page>
    <style>
        .grs-page { display:grid; gap:20px; color:#111827; }
        .dark .grs-page { color:#f8fafc; }
        .grs-panel { border:1px solid #e5e7eb; border-radius:18px; background:#fff; padding:22px; box-shadow:0 16px 40px rgba(15,23,42,.08); }
        .dark .grs-panel { border-color:rgba(148,163,184,.22); background:#111827; box-shadow:0 18px 46px rgba(0,0,0,.28); }
        .grs-title { margin:0; font-size:24px; line-height:1.15; font-weight:900; }
        .grs-copy { margin:8px 0 0; color:#667085; font-size:14px; line-height:1.55; }
        .dark .grs-copy { color:#a8b0bd; }
        .grs-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; margin-top:18px; }
        .grs-field { display:grid; gap:7px; }
        .grs-label { font-size:13px; font-weight:850; }
        .grs-input { width:100%; min-height:44px; box-sizing:border-box; border:1px solid #d0d5dd; border-radius:12px; padding:9px 11px; background:#fff; color:#111827; font:inherit; }
        .dark .grs-input { background:#0b1220; border-color:rgba(148,163,184,.32); color:#f8fafc; }
        .grs-check { display:flex; gap:10px; align-items:center; min-height:44px; }
        .grs-actions { margin-top:18px; }
        .grs-button { min-height:42px; border:0; border-radius:12px; padding:10px 16px; background:#f59e0b; color:#111827; font-weight:900; cursor:pointer; }
        @media (max-width: 760px) { .grs-grid { grid-template-columns:1fr; } }
    </style>

    <form wire:submit.prevent="save" class="grs-page">
        <section class="grs-panel">
            <h2 class="grs-title">Referral Settings</h2>
            <p class="grs-copy">Control Goshen Retreat referral awards and the wallet value of validated points.</p>

            <div class="grs-grid">
                <label class="grs-field">
                    <span class="grs-label">Referrals enabled</span>
                    <span class="grs-check">
                        <input type="checkbox" wire:model.defer="enabled">
                        <span>Accept referral codes and allow conversion</span>
                    </span>
                </label>

                <label class="grs-field">
                    <span class="grs-label">Points per paid registration</span>
                    <input class="grs-input" type="number" min="1" step="1" wire:model.defer="pointsPerPaidRegistration">
                </label>

                <label class="grs-field">
                    <span class="grs-label">Wallet amount per point</span>
                    <input class="grs-input" type="number" min="0" step="0.01" wire:model.defer="walletAmountPerPoint">
                </label>

                <label class="grs-field">
                    <span class="grs-label">Minimum points to convert</span>
                    <input class="grs-input" type="number" min="1" step="1" wire:model.defer="minConvertiblePoints">
                </label>
            </div>

            <div class="grs-actions">
                <button type="submit" class="grs-button">Save referral settings</button>
            </div>
        </section>
    </form>
</x-filament-panels::page>
