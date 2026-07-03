<?php

namespace App\Services;

use App\Models\AccommodationBlockedDate;
use App\Models\AccommodationBooking;
use App\Models\AccommodationCategory;
use App\Models\AccommodationUnit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AccommodationBookingService
{
    public function __construct(private readonly AccommodationNotificationService $notifications)
    {
    }

    public function quote(AccommodationCategory $category, array $data): array
    {
        $checkIn = CarbonImmutable::parse($data['check_in_date'])->startOfDay();
        $checkout = CarbonImmutable::parse($data['checkout_date'])->startOfDay();
        $adults = max(1, (int) ($data['adults'] ?? 1));
        $children = max(0, (int) ($data['children'] ?? 0));
        $rooms = max(1, (int) ($data['rooms'] ?? 1));

        if ($checkout->lessThanOrEqualTo($checkIn)) {
            throw new InvalidArgumentException('Checkout date must be after check-in date.');
        }

        $nights = $checkIn->diffInDays($checkout);
        if ($nights > (int) $category->max_stay_days) {
            throw new InvalidArgumentException("Maximum stay for {$category->name} is {$category->max_stay_days} day(s).");
        }

        if (! $category->children_allowed && $children > 0) {
            throw new InvalidArgumentException('Children are not allowed for this accommodation.');
        }

        if ($adults > (int) $category->max_adults || $children > (int) $category->max_children || ($adults + $children) > (int) $category->capacity) {
            throw new InvalidArgumentException('Selected occupants exceed this accommodation capacity.');
        }

        $availableUnits = $this->availableUnits($category, $checkIn->toDateString(), $checkout->toDateString());
        $available = $availableUnits->count() >= $rooms;
        $pricePerNight = (float) $category->price;
        $subtotal = $pricePerNight * $nights * $rooms;

        return [
            'available' => $available,
            'available_units' => $availableUnits->count(),
            'requested_rooms' => $rooms,
            'nights' => $nights,
            'price_per_night' => $pricePerNight,
            'service_charge' => 0,
            'discount' => 0,
            'total_amount' => $subtotal,
            'currency' => $category->currency ?: 'NGN',
            'message' => $available ? 'Available for selected dates.' : 'Not available for the selected dates. Please choose another date.',
        ];
    }

    public function createPendingBooking(AccommodationCategory $category, $user, array $data): AccommodationBooking
    {
        $booking = DB::transaction(function () use ($category, $user, $data) {
            $quote = $this->quote($category, $data);
            if (! $quote['available']) {
                throw new InvalidArgumentException($quote['message']);
            }

            $unit = $this->availableUnits($category, $data['check_in_date'], $data['checkout_date'])->first();
            if (! $unit) {
                throw new InvalidArgumentException('No room/unit is currently available for the selected date.');
            }

            return AccommodationBooking::create([
                'booking_reference' => $this->newReference(),
                'user_id' => $user->id,
                'accommodation_category_id' => $category->id,
                'accommodation_unit_id' => $unit->id,
                'check_in_date' => $data['check_in_date'],
                'checkout_date' => $data['checkout_date'],
                'nights' => $quote['nights'],
                'adults' => (int) $data['adults'],
                'children' => (int) ($data['children'] ?? 0),
                'total_occupants' => (int) $data['adults'] + (int) ($data['children'] ?? 0),
                'price_per_night' => $quote['price_per_night'],
                'service_charge' => $quote['service_charge'],
                'discount' => $quote['discount'],
                'total_amount' => $quote['total_amount'],
                'currency' => $quote['currency'],
                'booking_status' => 'pending_payment',
                'payment_status' => 'pending',
                'rules_accepted_at' => now(),
                'expires_at' => now()->addMinutes(15),
            ]);
        });

        $this->notifications->bookingCreated($booking);

        return $booking;
    }

    public function availableUnits(AccommodationCategory $category, string $checkInDate, string $checkoutDate)
    {
        return AccommodationUnit::query()
            ->where('accommodation_category_id', $category->id)
            ->where('is_active', true)
            ->where('status', 'available')
            ->whereDoesntHave('bookings', function ($query) use ($checkInDate, $checkoutDate) {
                $query->whereIn('booking_status', ['pending_payment', 'confirmed', 'checked_in'])
                    ->where(function ($statusQuery) {
                        $statusQuery->where('payment_status', 'paid')
                            ->orWhere(function ($pendingQuery) {
                                $pendingQuery->where('payment_status', 'pending')
                                    ->where('expires_at', '>', now());
                            });
                    })
                    ->whereDate('check_in_date', '<', $checkoutDate)
                    ->whereDate('checkout_date', '>', $checkInDate);
            })
            ->whereNotExists(function ($query) use ($checkInDate, $checkoutDate) {
                $query->selectRaw('1')
                    ->from('accommodation_blocked_dates')
                    ->whereColumn('accommodation_blocked_dates.accommodation_category_id', 'accommodation_units.accommodation_category_id')
                    ->where(function ($unitQuery) {
                        $unitQuery->whereNull('accommodation_blocked_dates.accommodation_unit_id')
                            ->orWhereColumn('accommodation_blocked_dates.accommodation_unit_id', 'accommodation_units.id');
                    })
                    ->whereDate('start_date', '<', $checkoutDate)
                    ->whereDate('end_date', '>', $checkInDate);
            })
            ->orderBy('unit_name')
            ->get();
    }

    public function expireOldPendingBookings(): int
    {
        $bookings = AccommodationBooking::query()
            ->where('booking_status', 'pending_payment')
            ->where('payment_status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $bookings->each->update(['booking_status' => 'expired']);

        return $bookings->count();
    }

    private function newReference(): string
    {
        do {
            $reference = 'MC-BOOK-' . now()->format('ymd') . '-' . Str::upper(Str::random(6));
        } while (AccommodationBooking::where('booking_reference', $reference)->exists());

        return $reference;
    }
}
