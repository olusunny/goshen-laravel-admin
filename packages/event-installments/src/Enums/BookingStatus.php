<?php

namespace Personal\EventInstallments\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case DepositPaid = 'deposit_paid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
