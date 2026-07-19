<?php

namespace Tests\Feature;

use App\Filament\Widgets\GoshenBookingStatsWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Models\Ticket;
use Tests\TestCase;

class GoshenBookingStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_goshen_booking_widget_summarizes_sales_editions_and_recent_purchases(): void
    {
        $event = Event::query()->create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-retreat-2026-stats',
            'timezone' => 'Europe/London',
            'venue_name' => 'High Leigh Conference Centre',
            'venue_address' => 'Lord Street, Hoddesdon, Hertfordshire EN11 8SG',
            'status' => 'published',
            'settings' => ['module' => 'goshen_retreat'],
        ]);

        $ticketType = EventTicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Goshen Individual',
            'currency' => 'GBP',
            'price' => 300,
            'is_active' => true,
        ]);

        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_name' => 'Ada Lovelace',
            'customer_email' => 'ada@example.test',
            'customer_phone' => '+447700900123',
            'currency' => 'GBP',
            'subtotal' => 300,
            'total' => 300,
            'paid_total' => 300,
            'status' => BookingStatus::Paid,
        ]);

        $attendee = Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'phone' => '+447700900123',
        ]);

        Ticket::query()->create([
            'event_id' => $event->id,
            'booking_id' => $booking->id,
            'attendee_id' => $attendee->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => '000001',
            'formatted_number' => 'GOSHEN-2-000001',
            'qr_hash' => hash('sha256', 'goshen-stats-ticket'),
            'status' => TicketStatus::CheckedIn,
            'issued_at' => now(),
        ]);

        PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'gateway' => 'stripe',
            'provider_reference' => 'pi_goshen_stats_1',
            'currency' => 'GBP',
            'amount' => 300,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $widget = new GoshenBookingStatsWidget();

        $overview = $widget->getOverview();
        $this->assertSame(1, $overview['tickets_sold']);
        $this->assertSame(1, $overview['paid_bookings']);
        $this->assertSame('GBP 300.00', $overview['revenue']);
        $this->assertSame(1, $overview['today_tickets']);
        $this->assertSame('GBP 300.00', $overview['today_revenue']);
        $this->assertSame(1, $overview['checked_in']);
        $this->assertSame(0, $overview['awaiting_check_in']);

        $daily = collect($widget->getDailySales())->last();
        $this->assertSame(1, $daily['tickets']);
        $this->assertSame('GBP 300.00', $daily['amount']);

        $edition = collect($widget->getEditionBreakdown())->first();
        $this->assertSame('Goshen Retreat 2026', $edition['name']);
        $this->assertSame(1, $edition['tickets_sold']);
        $this->assertSame(1, $edition['paid_bookings']);
        $this->assertSame(1, $edition['checked_in']);
        $this->assertSame('GBP 300.00', $edition['revenue']);
        $this->assertStringContainsString('High Leigh Conference Centre', $edition['venue']);

        $recent = collect($widget->getRecentPurchases())->first();
        $this->assertSame('Ada Lovelace', $recent['customer']);
        $this->assertSame('ada@example.test', $recent['email']);
        $this->assertSame('Stripe', $recent['method']);
        $this->assertSame(1, $recent['tickets']);
        $this->assertSame('GBP 300.00', $recent['amount']);
    }
}
