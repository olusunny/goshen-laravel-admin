<?php

namespace Tests\Feature;

use App\Filament\Pages\AppSettings;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppSettingsRedisCacheControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_opt_in_to_redis_cache_without_affecting_other_settings(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::query()->firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]));

        AppSetting::query()->create([
            'group' => 'features',
            'key' => 'goshen_wallet_enabled',
            'value' => '1',
        ]);

        Livewire::actingAs($admin)
            ->test(AppSettings::class)
            ->assertSee('Use Redis for application cache')
            ->assertSee('Redis cache is opt-in.')
            ->assertSee('When it is turned off or unavailable, the database cache remains the fallback.')
            ->assertSee('Sessions, queues, payments, wallet balances, tickets, and audit records are unchanged.')
            ->assertSet('redisCacheEnabled', false)
            ->set('redisCacheEnabled', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('1', AppSetting::value('redis_cache_enabled'));
        $this->assertSame('1', AppSetting::value('goshen_wallet_enabled'));
    }
}
