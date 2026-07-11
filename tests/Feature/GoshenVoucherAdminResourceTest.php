<?php

namespace Tests\Feature;

use App\Filament\Resources\GoshenVoucherResource;
use App\Models\GoshenVoucher;
use App\Models\GoshenVoucherUsage;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GoshenVoucherAdminResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_access_uses_admin_permission(): void
    {
        $admin = User::factory()->create();
        $permission = Permission::findOrCreate(AdminPermissions::resourcePermission(GoshenVoucherResource::class), 'web');

        $this->assertFalse(GoshenVoucherResource::canViewAny());

        $this->actingAs($admin);
        $this->assertFalse(GoshenVoucherResource::canViewAny());

        $admin->givePermissionTo($permission);
        $this->assertTrue(GoshenVoucherResource::canViewAny());
    }

    public function test_delete_and_bulk_helpers_preserve_used_vouchers(): void
    {
        $unused = $this->voucher('unused-delete');
        $used = $this->usedVoucher('used-delete');

        $this->assertTrue(GoshenVoucherResource::canDeleteVoucher($unused));
        $this->assertFalse(GoshenVoucherResource::canDeleteVoucher($used));

        $result = GoshenVoucherResource::deleteUnusedVouchers(collect([$unused, $used]));

        $this->assertSame(['deleted' => 1, 'skipped' => 1], $result);
        $this->assertDatabaseMissing('goshen_vouchers', ['id' => $unused->id]);
        $this->assertDatabaseHas('goshen_vouchers', ['id' => $used->id]);
        $this->assertDatabaseHas('goshen_voucher_usages', ['voucher_id' => $used->id]);
    }

    public function test_bulk_void_skips_used_vouchers(): void
    {
        $unused = $this->voucher('unused-void');
        $used = $this->usedVoucher('used-void');

        $result = GoshenVoucherResource::voidUnusedVouchers(collect([$unused, $used]));

        $this->assertSame(['voided' => 1, 'skipped' => 1], $result);
        $this->assertSame(GoshenVoucher::STATUS_VOID, $unused->fresh()->status);
        $this->assertSame(GoshenVoucher::STATUS_EXHAUSTED, $used->fresh()->status);
    }

    private function voucher(string $label): GoshenVoucher
    {
        return GoshenVoucher::query()->create([
            'label' => $label,
            'purpose' => GoshenVoucher::PURPOSE_PAYMENTS,
            'code_hash' => hash('sha256', $label),
            'code_suffix' => strtoupper(substr($label, 0, 6)),
            'currency' => 'GBP',
            'amount' => 10,
            'max_uses' => 1,
            'used_count' => 0,
            'status' => GoshenVoucher::STATUS_ACTIVE,
        ]);
    }

    private function usedVoucher(string $label): GoshenVoucher
    {
        $voucher = $this->voucher($label);
        $voucher->forceFill([
            'used_count' => 1,
            'status' => GoshenVoucher::STATUS_EXHAUSTED,
        ])->save();

        GoshenVoucherUsage::query()->create([
            'voucher_id' => $voucher->id,
            'code_suffix' => $voucher->code_suffix,
            'currency' => $voucher->currency,
            'amount' => $voucher->amount,
            'source' => 'test',
            'status' => GoshenVoucherUsage::STATUS_APPLIED,
        ]);

        return $voucher->fresh();
    }
}
