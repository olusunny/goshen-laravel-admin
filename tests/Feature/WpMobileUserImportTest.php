<?php

namespace Tests\Feature;

use App\Models\GoshenWallet;
use App\Models\MobileUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WpMobileUserImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_create_without_writing_records(): void
    {
        $path = $this->csvPath([
            ['Ayo', 'AYO@example.test', '+447700900111', 'male', '25-35', 'Ayo', 'Balogun'],
        ]);

        $this->artisan('goshen:import-wp-users', ['path' => $path])
            ->expectsOutputToContain('Dry run complete. No database changes were made.')
            ->assertSuccessful();

        $this->assertSame(0, MobileUser::query()->count());
    }

    public function test_apply_imports_members_with_triumphant_id_wallet_and_no_password(): void
    {
        $path = $this->csvPath([
            ['Ayo', 'AYO@example.test', '+447700900111', 'male', '25-35', 'Ayo', 'Balogun'],
            ['Bisi', 'bisi@example.test', '', '', '16-24', 'Bisi', 'Ade'],
        ]);

        $this->artisan('goshen:import-wp-users', ['path' => $path, '--apply' => true])
            ->expectsOutputToContain('Import complete.')
            ->assertSuccessful();

        $ayo = MobileUser::query()->where('email', 'ayo@example.test')->firstOrFail();
        $bisi = MobileUser::query()->where('email', 'bisi@example.test')->firstOrFail();

        $this->assertSame('Ayo Balogun', $ayo->name);
        $this->assertSame('Ayo', $ayo->first_name);
        $this->assertSame('Balogun', $ayo->last_name);
        $this->assertSame('+447700900111', $ayo->phone);
        $this->assertSame('447700900111', $ayo->phone_normalized);
        $this->assertSame('Male', $ayo->gender);
        $this->assertSame('church_member', $ayo->member_type);
        $this->assertSame('wp_import_forgot_password', $ayo->login_type);
        $this->assertNull($ayo->password);
        $this->assertTrue((bool) $ayo->is_verified);
        $this->assertNotNull($ayo->email_verified_at);
        $this->assertNotNull($ayo->triumphant_id);
        $this->assertNotNull($bisi->triumphant_id);
        $this->assertNotSame($ayo->triumphant_id, $bisi->triumphant_id);
        $this->assertDatabaseHas('goshen_wallets', ['mobile_user_id' => $ayo->id, 'balance' => 0]);
        $this->assertDatabaseHas('goshen_wallets', ['mobile_user_id' => $bisi->id, 'balance' => 0]);
        $this->assertSame(2, GoshenWallet::query()->count());
    }

    public function test_duplicate_csv_email_is_merged_into_one_member(): void
    {
        $path = $this->csvPath([
            ['First', 'duplicate@example.test', '', '', '', 'Duplicate', 'Member'],
            ['Second', 'DUPLICATE@example.test', '+2348012345678', 'Female', '', '', ''],
        ]);

        $this->artisan('goshen:import-wp-users', ['path' => $path, '--apply' => true])
            ->assertSuccessful();

        $this->assertSame(1, MobileUser::query()->whereRaw('LOWER(email) = ?', ['duplicate@example.test'])->count());

        $member = MobileUser::query()->where('email', 'duplicate@example.test')->firstOrFail();
        $this->assertSame('Duplicate Member', $member->name);
        $this->assertSame('+2348012345678', $member->phone);
        $this->assertSame('Female', $member->gender);
    }

    public function test_existing_member_is_updated_without_overwriting_password_or_existing_phone(): void
    {
        $existing = MobileUser::query()->create([
            'name' => 'Old Name',
            'email' => 'member@example.test',
            'phone' => '+11111111111',
            'password' => Hash::make('ExistingPassw0rd!'),
            'is_verified' => true,
            'email_verified_at' => now(),
            'member_type' => 'visitor',
        ]);

        $path = $this->csvPath([
            ['Member', 'MEMBER@example.test', '+22222222222', 'Female', '40 Above', 'New', 'Name'],
        ]);

        $this->artisan('goshen:import-wp-users', ['path' => $path, '--apply' => true])
            ->assertSuccessful();

        $existing->refresh();

        $this->assertSame('New Name', $existing->name);
        $this->assertSame('New', $existing->first_name);
        $this->assertSame('Name', $existing->last_name);
        $this->assertSame('+11111111111', $existing->phone);
        $this->assertSame('Female', $existing->gender);
        $this->assertSame('church_member', $existing->member_type);
        $this->assertTrue(Hash::check('ExistingPassw0rd!', $existing->password));
        $this->assertNotNull($existing->triumphant_id);
        $this->assertSame(1, GoshenWallet::query()->where('mobile_user_id', $existing->id)->count());
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string}>  $rows
     */
    private function csvPath(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wp-users-');
        $handle = fopen($path, 'w');

        fputcsv($handle, ['Username', 'Email', 'Phone Number', 'Gender', 'Age-group', 'First Name', 'Last Name']);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }
}
