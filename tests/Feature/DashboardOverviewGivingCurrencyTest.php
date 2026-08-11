<?php

namespace Tests\Feature;

use App\Filament\Widgets\DashboardOverview;
use App\Models\AppSetting;
use App\Models\Donation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardOverviewGivingCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_giving_recorded_uses_the_configured_currency_for_empty_totals(): void
    {
        AppSetting::query()->create(['key' => 'currency', 'value' => '£']);

        $this->actingAs($this->admin());

        Livewire::test(DashboardOverview::class)
            ->assertSee('GBP 0.00')
            ->assertDontSee('NGN 0.00');
    }

    public function test_giving_recorded_keeps_completed_donation_totals_in_their_actual_currencies(): void
    {
        AppSetting::query()->create(['key' => 'currency', 'value' => 'GBP']);

        Donation::query()->create([
            'amount' => 25,
            'currency' => 'GBP',
            'status' => 'completed',
        ]);
        Donation::query()->create([
            'amount' => 50,
            'currency' => 'NGN',
            'status' => 'paid',
        ]);
        Donation::query()->create([
            'amount' => 10,
            'currency' => 'not-a-currency',
            'status' => 'legacy',
            'paid_at' => now(),
        ]);

        $this->actingAs($this->admin());

        Livewire::test(DashboardOverview::class)
            ->assertSee('GBP 35.00')
            ->assertSee('NGN 50.00');
    }

    private function admin(): User
    {
        $this->seed();

        return User::query()->where('email', 'admin@church.local')->firstOrFail();
    }
}
