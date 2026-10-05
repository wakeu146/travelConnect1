<?php

namespace App\Enums;

enum PackageAvailabilityStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
}
