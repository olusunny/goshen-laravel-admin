<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventSchedule;
use Personal\EventInstallments\Models\EventTicketType;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class GoshenRetreatDemoSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['modules', 'goshen_retreat_enabled', '1', false, 'Show the Goshen Retreat module in the mobile app and web experience.'],
            ['modules', 'goshen_scanner_enabled', '1', false, 'Allow authorized scanner users to validate Goshen Retreat tickets.'],
            ['modules', 'goshen_stripe_giving_enabled', '1', false, 'Allow new giving payments through Stripe.'],
        ] as [$group, $key, $value, $secret, $description]) {
            AppSetting::query()->updateOrCreate(['key' => $key], [
                'group' => $group,
                'value' => $value,
                'is_secret' => $secret,
                'description' => $description,
            ]);
        }

        $goshenPermissions = ['manage_goshen_retreat_event', 'manage_goshen_schedule', 'manage_goshen_ticket_type', 'manage_goshen_booking', 'manage_goshen_ticket', 'manage_goshen_accommodation_allocation'];

        foreach ($goshenPermissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::query()->firstOrCreate(['name' => 'event_manager', 'guard_name' => 'web'])
            ->givePermissionTo($goshenPermissions);

        Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'])
            ->givePermissionTo($goshenPermissions);

        Role::query()->firstOrCreate(['name' => 'event_scanner', 'guard_name' => 'mobile']);
        Role::query()->firstOrCreate(['name' => 'event_manager', 'guard_name' => 'mobile']);

        $event = Event::query()->updateOrCreate(['slug' => 'goshen-retreat-2026'], [
            'name' => 'Goshen Retreat 2026',
            'type' => 'sequential',
            'description' => 'Demo Goshen Retreat edition for validating registration, full payments, tickets, and check-in workflows.',
            'timezone' => 'Africa/Lagos',
            'venue_name' => 'Goshen Retreat Camp',
            'venue_address' => 'Mercy Camp, Nigeria',
            'support_email' => 'support@goshen.shotfaz.com',
            'status' => 'published',
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addMonths(3),
        ]);

        EventSchedule::query()->updateOrCreate(['event_id' => $event->id, 'day_number' => 1], [
            'starts_at' => now()->addMonth()->setTime(9, 0),
            'ends_at' => now()->addMonth()->setTime(18, 0),
            'capacity' => 500,
            'metadata' => ['title' => 'Opening day sessions'],
        ]);

        EventSchedule::query()->updateOrCreate(['event_id' => $event->id, 'day_number' => 2], [
            'starts_at' => now()->addMonth()->addDay()->setTime(9, 0),
            'ends_at' => now()->addMonth()->addDay()->setTime(23, 0),
            'capacity' => 500,
            'metadata' => ['title' => 'Teaching, prayers, and vigil'],
        ]);

        EventTicketType::query()->updateOrCreate(['event_id' => $event->id, 'name' => 'Adult registration'], [
            'sku' => 'GOSHEN-ADULT',
            'currency' => 'NGN',
            'price' => 25000,
            'capacity' => 500,
            'min_per_booking' => 1,
            'max_per_booking' => 8,
            'is_active' => true,
        ]);

    }
}
