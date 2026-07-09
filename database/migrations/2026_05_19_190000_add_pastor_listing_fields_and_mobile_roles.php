<?php

use App\Models\MobileUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_users', function (Blueprint $table) {
            if (! Schema::hasColumn('mobile_users', 'role_title')) {
                $table->string('role_title')->nullable()->after('login_type');
            }

            if (! Schema::hasColumn('mobile_users', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('role_title');
            }
        });

        foreach (['Pastor', 'Disciple', 'Group leader', 'Assistant group leader'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'mobile']);
        }
    }

    public function down(): void
    {
        Schema::table('mobile_users', function (Blueprint $table) {
            $table->dropColumn(['role_title', 'sort_order']);
        });
    }
};
