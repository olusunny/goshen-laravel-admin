<?php

use App\Filament\Resources\AppSplashMediaResource;
use App\Support\AdminPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('splash_media', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->string('media_type', 20)->index();
            $table->string('media_path');
            $table->string('thumbnail_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->boolean('active')->default(false)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->unsignedInteger('version')->unique();
            $table->string('checksum', 64)->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable()->index();
            $table->timestamps();

            $table->index(['active', 'enabled']);
            $table->index(['media_type', 'enabled']);
        });

        $source = resource_path('splash/default-splash-video.mp4');
        $path = 'app/splash-media/media/default-splash-video.mp4';

        if (is_file($source)) {
            Storage::disk('public')->put($path, file_get_contents($source));

            DB::table('splash_media')->insert([
                'title' => 'Default splash video',
                'media_type' => 'video',
                'media_path' => $path,
                'original_filename' => 'default-splash-video.mp4',
                'mime_type' => 'video/mp4',
                'size_bytes' => filesize($source) ?: null,
                'duration_ms' => 5000,
                'enabled' => true,
                'active' => true,
                'is_default' => true,
                'version' => 1,
                'checksum' => hash_file('sha256', $source) ?: null,
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate(
            AdminPermissions::resourcePermission(AppSplashMediaResource::class),
            'web',
        );

        Role::query()
            ->where('guard_name', 'web')
            ->where('name', 'super_admin')
            ->get()
            ->each(fn (Role $role): Role => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('splash_media');

        Permission::query()
            ->where('name', AdminPermissions::resourcePermission(AppSplashMediaResource::class))
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
