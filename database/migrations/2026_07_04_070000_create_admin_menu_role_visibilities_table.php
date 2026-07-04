<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_menu_role_visibilities', function (Blueprint $table): void {
            $table->id();
            $table->string('menu_key', 190);
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['menu_key', 'role_id'], 'admin_menu_role_visibility_unique');
            $table->index(['role_id', 'is_visible'], 'admin_menu_role_visibility_role_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_menu_role_visibilities');
    }
};
