<?php

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
        Schema::create('goshen_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained('ei_events')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_mobile_user_id')->nullable()->constrained('mobile_users')->nullOnDelete();
            $table->string('label')->nullable();
            $table->string('batch_reference')->nullable()->index();
            $table->string('code_hash', 64)->unique();
            $table->string('code_suffix', 12)->index();
            $table->string('currency', 3)->default('GBP');
            $table->decimal('amount', 12, 2);
            $table->unsignedInteger('max_uses')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->string('status')->default('active')->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['currency', 'amount']);
        });

        Schema::create('goshen_voucher_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('voucher_id')->constrained('goshen_vouchers')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('ei_events')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('ei_bookings')->nullOnDelete();
            $table->foreignId('payment_installment_id')->nullable()->constrained('ei_payment_installments')->nullOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('ei_payment_transactions')->nullOnDelete();
            $table->foreignId('mobile_user_id')->nullable()->constrained('mobile_users')->nullOnDelete();
            $table->foreignId('redeemed_by_mobile_user_id')->nullable()->constrained('mobile_users')->nullOnDelete();
            $table->foreignId('redeemed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code_suffix', 12)->index();
            $table->string('currency', 3);
            $table->decimal('amount', 12, 2);
            $table->string('source')->default('mobile_registration')->index();
            $table->string('status')->default('applied')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['voucher_id', 'booking_id'], 'goshen_voucher_unique_booking_usage');
            $table->index(['event_id', 'created_at']);
            $table->index(['mobile_user_id', 'created_at']);
        });

        $permissions = collect([
            AdminPermissions::resourcePermission(\App\Filament\Resources\GoshenVoucherResource::class),
            AdminPermissions::resourcePermission(\App\Filament\Resources\GoshenVoucherUsageResource::class),
        ])->map(fn (string $name) => Permission::findOrCreate($name, 'web'));

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['super_admin', 'event_manager'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        Permission::findOrCreate('manage_goshen_vouchers', 'mobile');
        Role::query()
            ->where('guard_name', 'mobile')
            ->whereIn('name', ['admin', 'super_admin', 'event_manager', 'goshen_manager'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo('manage_goshen_vouchers'));
    }

    public function down(): void
    {
        Schema::dropIfExists('goshen_voucher_usages');
        Schema::dropIfExists('goshen_vouchers');

        Permission::query()
            ->whereIn('name', [
                'manage_goshen_voucher',
                'manage_goshen_voucher_usage',
                'manage_goshen_vouchers',
            ])
            ->delete();
    }
};
