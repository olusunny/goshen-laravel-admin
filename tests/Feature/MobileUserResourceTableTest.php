<?php

namespace Tests\Feature;

use App\Filament\Resources\MobileUserResource;
use App\Models\MobileUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileUserResourceTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_users_table_summary_counts_available_users_by_gender(): void
    {
        MobileUser::withoutEvents(fn () => MobileUser::query()->create([
            'name' => 'Main Pastor',
            'email' => 'main@example.test',
            'gender' => 'Male',
            'is_deleted' => false,
        ]));

        MobileUser::withoutEvents(fn () => MobileUser::query()->create([
            'name' => 'Member Female',
            'email' => 'female@example.test',
            'gender' => 'female',
            'is_deleted' => false,
        ]));

        MobileUser::withoutEvents(fn () => MobileUser::query()->create([
            'name' => 'No Gender',
            'email' => 'unknown@example.test',
            'gender' => null,
            'is_deleted' => false,
        ]));

        MobileUser::withoutEvents(fn () => MobileUser::query()->create([
            'name' => 'Deleted Female',
            'email' => 'deleted@example.test',
            'gender' => 'Female',
            'is_deleted' => true,
        ]));

        $this->assertSame(
            'Available registered users: 3 | Male: 1 | Female: 1',
            MobileUserResource::registeredUsersTableSummary(),
        );
    }

    public function test_triumphant_id_table_sort_uses_numeric_sequence_with_unassigned_last(): void
    {
        $this->createMobileUser('T010 Member', 't010@example.test', 10, 'T010');
        $this->createMobileUser('Unassigned Member', 'unassigned@example.test', null, null);
        $this->createMobileUser('T001 Member', 't001@example.test', 1, 'T001');
        $this->createMobileUser('T003 Member', 't003@example.test', 3, 'T003');

        $ordered = MobileUserResource::applyTriumphantIdTableSort(MobileUser::query())
            ->pluck('triumphant_id')
            ->all();

        $this->assertSame(['T001', 'T003', 'T010', null], $ordered);
    }

    private function createMobileUser(string $name, string $email, ?int $sequence, ?string $triumphantId): MobileUser
    {
        return MobileUser::withoutEvents(fn () => MobileUser::query()->create([
            'name' => $name,
            'email' => $email,
            'triumphant_id_sequence' => $sequence,
            'triumphant_id' => $triumphantId,
            'is_deleted' => false,
        ]));
    }
}
