<?php

namespace Personal\EventInstallments\Enums;

enum EventType: string
{
    case Single = 'single';
    case Sequential = 'sequential';
    case SpecificDates = 'specific_dates';
    case Booking = 'booking';
    case Seating = 'seating';
}
