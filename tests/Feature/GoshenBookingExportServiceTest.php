<?php

namespace Tests\Feature;

use App\Filament\Resources\GoshenBookingResource;
use App\Services\GoshenBookingExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventAttendeeField;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\Ticket;
use ReflectionMethod;
use Tests\TestCase;

class GoshenBookingExportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exports_one_row_per_attendee_with_configured_goshen_registration_fields(): void
    {
        $event = Event::query()->create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-retreat-2026-export',
            'timezone' => 'Europe/London',
            'status' => 'published',
            'settings' => ['module' => 'goshen_retreat'],
        ]);

        EventAttendeeField::query()->create([
            'event_id' => $event->id,
            'key' => 'gender',
            'label' => 'Gender',
            'type' => 'select',
            'is_required' => true,
            'sort_order' => 10,
            'options' => [
                ['label' => 'Male', 'value' => 'male'],
                ['label' => 'Female', 'value' => 'female'],
            ],
        ]);

        EventAttendeeField::query()->create([
            'event_id' => $event->id,
            'key' => 'arrival_window',
            'label' => 'Arrival window',
            'type' => 'select',
            'is_required' => false,
            'sort_order' => 20,
            'options' => [
                ['label' => 'Morning', 'value' => 'morning'],
                ['label' => 'Evening', 'value' => 'evening'],
            ],
        ]);

        $otherEvent = Event::query()->create([
            'name' => 'Non Goshen Conference',
            'slug' => 'conference-export',
            'timezone' => 'Europe/London',
            'status' => 'published',
        ]);

        EventAttendeeField::query()->create([
            'event_id' => $otherEvent->id,
            'key' => 'irrelevant_field',
            'label' => 'Should not export',
            'type' => 'text',
            'sort_order' => 1,
        ]);

        $ticketType = EventTicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Goshen Family',
            'currency' => 'GBP',
            'price' => 300,
            'is_active' => true,
        ]);

        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_name' => 'Parent Buyer',
            'customer_email' => 'parent@example.test',
            'customer_phone' => '+447700900000',
            'currency' => 'GBP',
            'subtotal' => 600,
            'total' => 600,
            'paid_total' => 600,
            'status' => BookingStatus::Paid,
        ]);

        $attendee = Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => 'Grace',
            'last_name' => 'Buyer',
            'email' => 'grace@example.test',
            'phone' => '+447700900001',
            'company' => 'Goshen Farms',
            'designation' => 'member',
            'custom_fields' => [
                'gender' => 'female',
                'arrival_window' => 'morning',
                'room_note' => 'Near parent',
            ],
        ]);

        Ticket::query()->create([
            'event_id' => $event->id,
            'booking_id' => $booking->id,
            'attendee_id' => $attendee->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => '000123',
            'formatted_number' => 'GOSHEN-2-000123',
            'qr_hash' => hash('sha256', 'goshen-export-ticket'),
            'status' => TicketStatus::NotCheckedIn,
            'issued_at' => now(),
        ]);

        $exporter = app(GoshenBookingExportService::class);
        $registrationFields = $exporter->registrationFields();
        $headings = $exporter->headings($registrationFields);
        $row = $exporter->rowsForBooking($booking, $registrationFields)[0];
        $combined = array_combine($headings, $row);

        $this->assertContains('Registration: Gender', $headings);
        $this->assertContains('Registration: Arrival window', $headings);
        $this->assertNotContains('Registration: Should not export', $headings);

        $this->assertSame('parent@example.test', $combined['Customer email']);
        $this->assertSame('Grace', $combined['First name']);
        $this->assertSame('Buyer', $combined['Last name']);
        $this->assertSame('Goshen Family', $combined['Ticket type']);
        $this->assertSame('GOSHEN-2-000123', $combined['Ticket number']);
        $this->assertSame('Female', $combined['Registration: Gender']);
        $this->assertSame('Morning', $combined['Registration: Arrival window']);
        $this->assertStringContainsString('room_note', $combined['Additional custom fields']);
    }

    public function test_goshen_booking_gender_table_column_shows_answer_without_attendee_name(): void
    {
        $event = Event::query()->create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-retreat-2026-gender-column',
            'timezone' => 'Europe/London',
            'status' => 'published',
            'settings' => ['module' => 'goshen_retreat'],
        ]);

        $genderField = EventAttendeeField::query()->create([
            'event_id' => $event->id,
            'key' => 'gender',
            'label' => 'Gender',
            'type' => 'select',
            'is_required' => true,
            'sort_order' => 10,
            'options' => [
                ['label' => 'Male', 'value' => 'male'],
                ['label' => 'Female', 'value' => 'female'],
            ],
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
            'customer_name' => 'Parent Buyer',
            'customer_email' => 'parent@example.test',
            'currency' => 'GBP',
            'subtotal' => 300,
            'total' => 300,
            'paid_total' => 300,
            'status' => BookingStatus::Paid,
        ]);

        Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => 'Grace',
            'last_name' => 'Buyer',
            'email' => 'grace@example.test',
            'custom_fields' => ['gender' => 'female'],
        ]);

        $method = new ReflectionMethod(GoshenBookingResource::class, 'attendeeRegistrationFieldSummary');
        $method->setAccessible(true);

        $state = $method->invoke(null, $booking, 'gender', $genderField);

        $this->assertSame(['Female'], $state);
        $this->assertStringNotContainsString('Grace', implode("\n", $state));
        $this->assertStringNotContainsString('Buyer', implode("\n", $state));
    }
}
