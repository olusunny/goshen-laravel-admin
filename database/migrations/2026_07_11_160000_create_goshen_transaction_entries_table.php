<?php

use App\Filament\Resources\GoshenTransactionEntryResource;
use App\Support\AdminPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goshen_transaction_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mobile_user_id')->nullable()->constrained('mobile_users')->nullOnDelete();
            $table->string('source', 40);
            $table->string('source_table', 80);
            $table->unsignedBigInteger('source_id');
            $table->string('source_reference', 180)->nullable()->index();
            $table->string('transaction_kind', 40)->default('payment')->index();
            $table->string('direction', 20)->default('neutral')->index();
            $table->boolean('counts_toward_revenue')->default(false)->index();
            $table->string('label', 180)->nullable();
            $table->string('payer_name', 180)->nullable();
            $table->string('payer_email', 180)->nullable()->index();
            $table->string('payer_phone', 80)->nullable();
            $table->string('payment_provider', 40)->nullable()->index();
            $table->string('gateway', 40)->nullable()->index();
            $table->string('status', 40)->index();
            $table->string('currency', 3);
            $table->decimal('amount', 14, 2);
            $table->timestamp('initiated_at')->nullable()->index();
            $table->timestamp('settled_at')->nullable()->index();
            $table->timestamp('occurred_at')->index();
            $table->char('payer_ip_hash', 64)->nullable()->index();
            $table->char('payer_user_agent_hash', 64)->nullable();
            $table->string('payer_ip_label', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_table', 'source_id'], 'goshen_tx_source_unique');
            $table->index(['mobile_user_id', 'occurred_at', 'id'], 'goshen_tx_user_time_idx');
            $table->index(['source', 'occurred_at', 'id'], 'goshen_tx_source_time_idx');
            $table->index(['payment_provider', 'occurred_at', 'id'], 'goshen_tx_provider_time_idx');
            $table->index(['status', 'occurred_at', 'id'], 'goshen_tx_status_time_idx');
        });

        $permission = Permission::findOrCreate(
            AdminPermissions::resourcePermission(GoshenTransactionEntryResource::class),
            'web',
        );

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['super_admin', 'event_manager'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));
    }

    public function down(): void
    {
        Permission::query()
            ->where('name', AdminPermissions::resourcePermission(GoshenTransactionEntryResource::class))
            ->where('guard_name', 'web')
            ->delete();

        Schema::dropIfExists('goshen_transaction_entries');
    }
};
