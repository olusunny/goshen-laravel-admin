<?php

namespace Personal\EventInstallments\Policies;

use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Support\AuthorizesEventInstallments;

class BookingPolicy
{
    use AuthorizesEventInstallments;

    public function view($user, Booking $booking): bool
    {
        return $this->managesEvent($user, $booking->event)
            || (string) $booking->customer_id === (string) $user?->getAuthIdentifier();
    }

    public function create($user): bool
    {
        return $user !== null;
    }

    public function managePayments($user, Booking $booking): bool
    {
        return $this->managesFinance($user) || $this->managesEvent($user, $booking->event);
    }

    public function checkout($user, Booking $booking): bool
    {
        return $this->view($user, $booking) || $this->managePayments($user, $booking);
    }
}
