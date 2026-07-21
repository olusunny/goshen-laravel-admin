<?php

namespace Tests\Feature;

use App\Models\GoshenReferralCode;
use App\Models\MobileUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventSchedule;
use Tests\TestCase;

class GoshenReferralInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_invite_page_includes_retreat_share_metadata_without_ticket_price(): void
    {
        $member = MobileUser::query()->create([
            'name' => 'Referral Owner',
            'email' => 'owner@example.test',
            'phone' => '+447700900001',
            'password' => 'secret',
        ]);
        $code = 'GROWNER2026';
        GoshenReferralCode::query()->updateOrCreate(
            ['mobile_user_id' => $member->id],
            ['code' => $code, 'generated_at' => now()],
        );
        $event = Event::query()->create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-retreat-2026',
            'type' => EventType::Sequential,
            'timezone' => 'Europe/London',
            'status' => 'published',
            'sales_start_at' => Carbon::parse('2026-06-01'),
            'sales_end_at' => Carbon::parse('2026-09-01'),
            'venue_name' => 'High Leigh Conference Centre',
            'venue_address' => 'Hoddesdon, Hertfordshire',
            'settings' => [
                'module' => 'goshen_retreat',
                'feature_banner' => ['image_path' => 'goshen/retreat/banners/goshen-2026.jpg'],
            ],
        ]);
        EventSchedule::query()->create([
            'event_id' => $event->id,
            'day_number' => 1,
            'starts_at' => Carbon::parse('2026-08-14 17:00:00'),
            'ends_at' => Carbon::parse('2026-08-16 14:00:00'),
            'metadata' => ['title' => 'Opening service'],
        ]);

        $this->get('/invite/'.$code)
            ->assertOk()
            ->assertSee('Join me at Goshen Retreat 2026')
            ->assertSee('High Leigh Conference Centre - Hoddesdon, Hertfordshire')
            ->assertSee('14 Aug 2026 - 16 Aug 2026')
            ->assertSee('/storage/goshen/retreat/banners/goshen-2026.jpg', false)
            ->assertSee('/app?ref='.$code, false)
            ->assertDontSee('£')
            ->assertDontSee('GBP');
    }

    public function test_referral_invite_requires_a_real_referral_code(): void
    {
        $this->get('/invite/UNKNOWN2026')->assertNotFound();
    }
}
