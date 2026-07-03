<?php

namespace Personal\EventInstallments\Enums;

enum TicketStatus: string
{
    case NotCheckedIn = 'not_checked_in';
    case CheckedIn = 'checked_in';
    case Cancelled = 'cancelled';
    case Unpaid = 'unpaid';
    case Provisional = 'provisional';
}
