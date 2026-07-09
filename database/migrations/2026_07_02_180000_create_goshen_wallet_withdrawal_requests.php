<?php

use App\Support\AdminPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('goshen_wallet_withdrawal_requests')) {
            Schema::create('goshen_wallet_withdrawal_requests', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('wallet_id');
                $table->unsignedBigInteger('mobile_user_id');
                $table->unsignedBigInteger('ledger_entry_id')->nullable()->unique('gw_withdraw_ledger_unique');
                $table->unsignedBigInteger('refund_ledger_entry_id')->nullable()->unique('gw_withdraw_refund_ledger_unique');
                $table->unsignedBigInteger('reviewed_by_mobile_user_id')->nullable();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('GBP');
                $table->string('status', 40)->default('pending')->index('gw_withdraw_status_idx');
                $table->string('bank_name')->nullable();
                $table->string('account_name')->nullable();
                $table->string('account_number', 80)->nullable();
                $table->string('sort_code', 40)->nullable();
                $table->string('iban', 80)->nullable();
                $table->string('payout_reference')->nullable()->index('gw_withdraw_payout_ref_idx');
                $table->text('user_note')->nullable();
                $table->text('admin_note')->nullable();
                $table->timestamp('requested_at')->nullable()->index('gw_withdraw_requested_idx');
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['mobile_user_id', 'created_at'], 'gw_withdraw_user_created_idx');
                $table->index(['status', 'created_at'], 'gw_withdraw_status_created_idx');
                $table->foreign('wallet_id', 'gw_withdraw_wallet_fk')->references('id')->on('goshen_wallets')->cascadeOnDelete();
                $table->foreign('mobile_user_id', 'gw_withdraw_user_fk')->references('id')->on('mobile_users')->cascadeOnDelete();
                $table->foreign('ledger_entry_id', 'gw_withdraw_ledger_fk')->references('id')->on('goshen_wallet_ledger_entries')->nullOnDelete();
                $table->foreign('refund_ledger_entry_id', 'gw_withdraw_refund_fk')->references('id')->on('goshen_wallet_ledger_entries')->nullOnDelete();
                $table->foreign('reviewed_by_mobile_user_id', 'gw_withdraw_reviewer_fk')->references('id')->on('mobile_users')->nullOnDelete();
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        collect([
            AdminPermissions::resourcePermission(\App\Filament\Resources\GoshenWalletWithdrawalRequestResource::class),
        ])->each(fn (string $name) => Permission::findOrCreate($name, 'web'));

        collect([
            'manage_goshen_wallet_withdrawals',
            'send_admin_messages',
        ])->each(fn (string $name) => Permission::findOrCreate($name, 'mobile'));

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['super_admin', 'event_manager'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo(AdminPermissions::resourcePermission(\App\Filament\Resources\GoshenWalletWithdrawalRequestResource::class)));

        Role::query()
            ->where('guard_name', 'mobile')
            ->whereIn('name', ['admin', 'super_admin', 'event_manager', 'goshen_manager'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo([
                'manage_goshen_wallet_withdrawals',
                'send_admin_messages',
            ]));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('goshen_wallet_withdrawal_requests');

        Permission::query()
            ->whereIn('name', [
                'manage_goshen_wallet_withdrawal_request',
                'manage_goshen_wallet_withdrawals',
                'send_admin_messages',
            ])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
