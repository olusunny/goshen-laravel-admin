<?php

namespace Tests\Feature;

use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\VideoAudioMediaResource;
use App\Models\MediaItem;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_super_admin_can_access_filament_dashboard(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@church.local')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Theme preference')
            ->assertSee('MFM');
    }

    public function test_login_page_uses_logo_branding(): void
    {
        $this->seed();

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('MFM')
            ->assertDontSee('MFM Triumphant Church Admin</div>', false);
    }

    public function test_user_without_admin_role_cannot_access_filament_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_media_create_forms_render_on_current_filament(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@church.local')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/media-items/create')
            ->assertOk()
            ->assertSee('Update banner')
            ->assertDontSee('Free to stream')
            ->assertDontSee('Can preview')
            ->assertDontSee('Free preview duration')
            ->assertDontSee('Views count')
            ->assertDontSee('Likes count');

        $this->actingAs($admin)
            ->get('/admin/video-audio-media/create')
            ->assertOk()
            ->assertSee('Video &amp; Audio', false)
            ->assertSee('Artwork and Playback')
            ->assertDontSee('Free to stream')
            ->assertDontSee('Can preview')
            ->assertDontSee('Free preview duration')
            ->assertDontSee('Views count')
            ->assertDontSee('Likes count');

        $this->actingAs($admin)
            ->get('/admin/streams/create')
            ->assertOk()
            ->assertSee('Stream url');
    }

    public function test_video_audio_management_cannot_access_update_banners(): void
    {
        $this->seed();

        $banner = MediaItem::create([
            'type' => 'banner',
            'title' => 'Admin-only update banner',
            'source_type' => 'none',
            'duration' => 0,
            'preview_duration' => 0,
            'can_download' => false,
            'can_preview' => false,
            'is_free' => true,
            'is_featured' => false,
            'is_published' => true,
            'views_count' => 0,
            'likes_count' => 0,
        ]);

        $admin = User::where('email', 'admin@church.local')->firstOrFail();

        $this->actingAs($admin)
            ->get("/admin/video-audio-media/{$banner->getKey()}/edit")
            ->assertNotFound();
    }

    public function test_video_audio_management_requires_its_resource_permission(): void
    {
        $permission = Permission::findOrCreate(
            AdminPermissions::resourcePermission(VideoAudioMediaResource::class),
            'web',
        );
        $mediaManager = Role::create(['name' => 'video_audio_manager', 'guard_name' => 'web']);

        $unauthorizedUser = User::factory()->create();
        $unauthorizedUser->assignRole($mediaManager);

        $this->actingAs($unauthorizedUser)
            ->get('/admin/video-audio-media')
            ->assertForbidden();

        $mediaManager->givePermissionTo($permission);
        $authorizedUser = User::factory()->create();
        $authorizedUser->assignRole($mediaManager);

        $this->actingAs($authorizedUser)
            ->get('/admin/video-audio-media')
            ->assertOk();
    }

    public function test_role_permission_edit_ignores_same_role_name_in_other_guard(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@church.local')->firstOrFail();
        $permission = Permission::firstOrCreate([
            'name' => AdminPermissions::FUNDRAISING_VIEW,
            'guard_name' => 'web',
        ]);

        Role::firstOrCreate([
            'name' => 'event_manager',
            'guard_name' => 'mobile',
        ]);

        $webRole = Role::firstOrCreate([
            'name' => 'event_manager',
            'guard_name' => 'web',
        ]);

        $this->actingAs($admin)
            ->get("/admin/roles/{$webRole->getKey()}/edit")
            ->assertOk()
            ->assertSee('Edit Role Permission');

        Livewire::actingAs($admin)
            ->test(EditRole::class, ['record' => $webRole->getKey()])
            ->fillForm([
                'name' => 'event_manager',
                'guard_name' => 'web',
                'permissions' => [$permission->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors(['name']);

        $this->assertSame(1, Role::query()
            ->where('name', 'event_manager')
            ->where('guard_name', 'web')
            ->count());
        $this->assertTrue($webRole->refresh()->hasPermissionTo($permission));
    }

    public function test_role_permission_edit_allows_unchanged_role_name(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@church.local')->firstOrFail();
        $permission = Permission::firstOrCreate([
            'name' => AdminPermissions::FUNDRAISING_MANAGE,
            'guard_name' => 'web',
        ]);

        $role = Role::firstOrCreate([
            'name' => 'Triumphant IT Manager',
            'guard_name' => 'web',
        ]);

        Livewire::actingAs($admin)
            ->test(EditRole::class, ['record' => $role->getKey()])
            ->fillForm([
                'name' => 'Triumphant IT Manager',
                'guard_name' => 'web',
                'permissions' => [$permission->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors(['name']);

        $this->assertSame(1, Role::query()
            ->where('name', 'Triumphant IT Manager')
            ->where('guard_name', 'web')
            ->count());
        $this->assertTrue($role->refresh()->hasPermissionTo($permission));
    }

    public function test_role_permission_edit_allows_saving_permissions_when_mobile_role_has_same_name(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@church.local')->firstOrFail();
        $permission = Permission::firstOrCreate([
            'name' => AdminPermissions::FUNDRAISING_MANAGE,
            'guard_name' => 'web',
        ]);

        Role::firstOrCreate([
            'name' => 'Triumphant IT Manager',
            'guard_name' => 'mobile',
        ]);

        $role = Role::firstOrCreate([
            'name' => 'Triumphant IT Manager',
            'guard_name' => 'web',
        ]);

        Livewire::actingAs($admin)
            ->test(EditRole::class, ['record' => $role->getKey()])
            ->fillForm([
                'permissions' => [$permission->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors(['name']);

        $this->assertTrue($role->refresh()->hasPermissionTo($permission));
    }

    public function test_role_permission_edit_rejects_duplicate_name_in_same_role_type(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@church.local')->firstOrFail();
        Role::firstOrCreate([
            'name' => 'Triumphant IT Manager',
            'guard_name' => 'web',
        ]);
        $role = Role::firstOrCreate([
            'name' => 'Content Manager',
            'guard_name' => 'web',
        ]);

        Livewire::actingAs($admin)
            ->test(EditRole::class, ['record' => $role->getKey()])
            ->fillForm([
                'name' => 'Triumphant IT Manager',
                'guard_name' => 'web',
                'permissions' => [],
            ])
            ->call('save')
            ->assertHasFormErrors(['name' => 'unique']);
    }

    public function test_role_permission_editor_manages_mobile_roles(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@church.local')->firstOrFail();
        $permission = Permission::firstOrCreate([
            'name' => 'manage_goshen_quiz',
            'guard_name' => 'mobile',
        ]);
        $mobileRole = Role::firstOrCreate([
            'name' => 'Quiz manager',
            'guard_name' => 'mobile',
        ]);

        $this->actingAs($admin)
            ->get("/admin/roles/{$mobileRole->getKey()}/edit")
            ->assertOk()
            ->assertSee('App access');

        Livewire::actingAs($admin)
            ->test(EditRole::class, ['record' => $mobileRole->getKey()])
            ->fillForm([
                'name' => 'Quiz manager',
                'guard_name' => 'mobile',
                'permissions' => [$permission->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors(['name']);

        $this->assertTrue($mobileRole->refresh()->hasPermissionTo($permission));
    }

    public function test_all_filament_resource_index_and_create_pages_render(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@church.local')->firstOrFail();
        $this->actingAs($admin);

        foreach ($this->adminResourcePaths() as $path) {
            $this->get('/admin/'.$path)->assertOk();
            $this->get('/admin/'.$path.'/create')->assertOk();
        }
    }

    /**
     * @return array<int, string>
     */
    private function adminResourcePaths(): array
    {
        return [
            'app-settings',
            'bible-versions',
            'branches',
            'categories',
            'church-events',
            'content-pages',
            'devotionals',
            'donation-account-categories',
            'donation-bank-accounts',
            'donations',
            'fcm-tokens',
            'hymns',
            'inbox-messages',
            'media-items',
            'video-audio-media',
            'mobile-users',
            'prayer-points',
            'streams',
            'user-comments',
            'users',
        ];
    }
}
