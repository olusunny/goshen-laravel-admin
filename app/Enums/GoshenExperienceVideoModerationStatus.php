<?php

namespace App\Enums;

enum GoshenExperienceVideoModerationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Removed = 'removed';
}
