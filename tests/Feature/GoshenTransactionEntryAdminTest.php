<?php

namespace Tests\Feature;

use App\Filament\Resources\GoshenTransactionEntryResource;
use App\Filament\Resources\MobileUserResource;
use App\Filament\Resources\MobileUserResource\RelationManagers\TransactionEntriesRelationManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoshenTransactionEntryAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_resource_is_read_only_for_admins(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        $this->actingAs($admin);

        $this->assertTrue(GoshenTransactionEntryResource::canViewAny());
        $this->assertFalse(GoshenTransactionEntryResource::canCreate());
        $this->assertFalse(GoshenTransactionEntryResource::canDeleteAny());
    }

    public function test_mobile_user_resource_exposes_financial_activity_relation(): void
    {
        $this->assertContains(
            TransactionEntriesRelationManager::class,
            MobileUserResource::getRelations(),
        );
    }
}
