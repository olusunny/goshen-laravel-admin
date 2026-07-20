<?php

namespace Tests\Feature;

use App\Filament\Resources\AppSplashMediaResource;
use App\Filament\Resources\AppSplashMediaResource\Pages\ListAppSplashMedia;
use App\Models\SplashMedia;
use App\Models\User;
use App\Services\SplashMediaService;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppSplashMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $compiledViewsPath = storage_path('framework/testing/views/'.str_replace('\\', '_', static::class));

        File::ensureDirectoryExists($compiledViewsPath);
        config(['view.compiled' => $compiledViewsPath]);
    }

    public function test_public_endpoint_returns_active_enabled_splash_media_payload(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('app/splash-media/media/splash.jpg', 'splash-image-bytes');

        $inactive = SplashMedia::query()->create([
            'title' => 'Inactive splash',
            'media_type' => SplashMedia::TYPE_IMAGE,
            'media_path' => 'app/splash-media/media/inactive.jpg',
            'enabled' => true,
            'active' => false,
        ]);

        $active = SplashMedia::query()->create([
            'title' => 'Opening splash',
            'media_type' => SplashMedia::TYPE_IMAGE,
            'media_path' => 'app/splash-media/media/splash.jpg',
            'enabled' => true,
            'active' => true,
            'duration_ms' => 1200,
            'checksum' => hash('sha256', 'splash-image-bytes'),
        ]);

        app(SplashMediaService::class)->activate($active);

        $this->assertFalse($inactive->fresh()->active);

        $response = $this->getJson('/api/v1/app/splash-media')
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('media_type', 'image')
            ->assertJsonPath('version', $active->version)
            ->assertJsonPath('checksum', hash('sha256', 'splash-image-bytes'))
            ->assertJsonPath('duration_ms', 1200);

        $this->assertStringContainsString(
            '/storage/app/splash-media/media/splash.jpg',
            (string) $response->json('media_url'),
        );

        $this->assertSame($response->json('media_url'), $response->json('thumbnail_url'));
    }

    public function test_public_endpoint_returns_disabled_payload_when_active_splash_is_disabled(): void
    {
        SplashMedia::query()->create([
            'title' => 'Paused splash',
            'media_type' => SplashMedia::TYPE_VIDEO,
            'media_path' => 'app/splash-media/media/splash.mp4',
            'thumbnail_path' => 'app/splash-media/thumbnails/splash.jpg',
            'enabled' => false,
            'active' => true,
            'duration_ms' => 2500,
            'checksum' => str_repeat('a', 64),
        ]);

        $this->getJson('/api/v1/app/splash-media')
            ->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('media_type', 'video')
            ->assertJsonPath('media_url', null)
            ->assertJsonPath('thumbnail_url', null)
            ->assertJsonPath('duration_ms', 2500);
    }

    public function test_activating_splash_media_keeps_only_one_active_record(): void
    {
        $first = SplashMedia::query()->create([
            'title' => 'Version one',
            'media_type' => SplashMedia::TYPE_IMAGE,
            'media_path' => 'app/splash-media/media/v1.jpg',
            'enabled' => true,
            'active' => true,
        ]);

        $second = SplashMedia::query()->create([
            'title' => 'Version two',
            'media_type' => SplashMedia::TYPE_VIDEO,
            'media_path' => 'app/splash-media/media/v2.mp4',
            'enabled' => false,
            'active' => false,
        ]);

        app(SplashMediaService::class)->activate($second);

        $this->assertFalse($first->fresh()->active);
        $this->assertTrue($second->fresh()->active);
        $this->assertTrue($second->fresh()->enabled);
        $this->assertSame(1, SplashMedia::query()->where('active', true)->count());
    }

    public function test_app_splash_media_resource_permission_is_discovered_and_enforced(): void
    {
        $permissionName = AdminPermissions::resourcePermission(AppSplashMediaResource::class);

        $this->assertSame('manage_app_splash_media', $permissionName);
        $this->assertArrayHasKey($permissionName, AdminPermissions::all());
        $this->assertSame('Media Library - App Splash Media', AdminPermissions::all()[$permissionName]);

        $role = Role::create(['name' => 'splash_manager', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->get('/admin/app-splash-media')
            ->assertForbidden();

        $role->givePermissionTo(Permission::findOrCreate($permissionName, 'web'));

        $this->actingAs($user)
            ->get('/admin/app-splash-media')
            ->assertOk();
    }

    public function test_admin_can_activate_splash_media_from_resource_history(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@church.local')->firstOrFail();

        $first = SplashMedia::query()->create([
            'title' => 'First splash',
            'media_type' => SplashMedia::TYPE_IMAGE,
            'media_path' => 'app/splash-media/media/first.jpg',
            'enabled' => true,
            'active' => true,
        ]);
        $second = SplashMedia::query()->create([
            'title' => 'Second splash',
            'media_type' => SplashMedia::TYPE_IMAGE,
            'media_path' => 'app/splash-media/media/second.jpg',
            'enabled' => true,
            'active' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(ListAppSplashMedia::class)
            ->callTableAction('activate', $second)
            ->assertHasNoTableActionErrors();

        $this->assertFalse($first->fresh()->active);
        $this->assertTrue($second->fresh()->active);
    }
}
