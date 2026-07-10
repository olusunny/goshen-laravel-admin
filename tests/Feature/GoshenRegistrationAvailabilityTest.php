<?php

namespace Tests\Feature;

use App\Models\MobileUser;
use App\Services\GoshenRegistrationAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\BookingLine;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventTicketType;
use Tests\TestCase;

class GoshenRegistrationAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_capacity_counts_booking_line_quantity_and_cancelled_booking_releases_it(): void
    {
        [$member, $ticketType] = $this->fixture(capacity: 3);
        $other = MobileUser::query()->create([
            'name' => 'Reserved Member',
            'email' => 'reserved-capacity@example.test',
            'is_verified' => true,
        ]);
        $reservation = $this->reservation($other, $ticketType, 2, BookingStatus::Pending);

        try {
            app(GoshenRegistrationAvailabilityService::class)
                ->lockAndAssertAvailable($member, $ticketType, 2);
            $this->fail('Expected capacity validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ticket_type_id', $exception->errors());
        }

        [$lockedMember, $lockedType] = app(GoshenRegistrationAvailabilityService::class)
            ->lockAndAssertAvailable($member, $ticketType, 1);
        $this->assertSame($member->id, $lockedMember->id);
        $this->assertSame($ticketType->id, $lockedType->id);

        $reservation->forceFill(['status' => BookingStatus::Cancelled])->save();
        app(GoshenRegistrationAvailabilityService::class)
            ->lockAndAssertAvailable($member, $ticketType, 3);
        $this->addToAssertionCount(1);
    }

    private function fixture(int $capacity): array
    {
        $member = MobileUser::query()->create([
            'name' => 'Available Member',
            'email' => 'available@example.test',
            'is_verified' => true,
        ]);
        $event = Event::query()->create([
            'name' => 'Goshen Availability',
            'slug' => 'goshen-availability',
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
        ]);
        $ticketType = EventTicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Adult',
            'currency' => 'GBP',
            'price' => 150,
            'capacity' => $capacity,
            'min_per_booking' => 1,
            'max_per_booking' => 10,
            'is_active' => true,
        ]);

        return [$member, $ticketType];
    }

    private function reservation(
        MobileUser $member,
        EventTicketType $ticketType,
        int $quantity,
        BookingStatus $status,
    ): Booking {
        $booking = Booking::query()->create([
            'event_id' => $ticketType->event_id,
            'customer_id' => $member->id,
            'customer_name' => $member->name,
            'customer_email' => $member->email,
            'currency' => $ticketType->currency,
            'subtotal' => 150 * $quantity,
            'total' => 150 * $quantity,
            'paid_total' => 0,
            'status' => $status,
        ]);
        BookingLine::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => $quantity,
            'currency' => $ticketType->currency,
            'unit_price' => 150,
            'line_total' => 150 * $quantity,
        ]);

        return $booking;
    }
}
