<?php

namespace App\Enums;

enum TravelRequestStatus: string
{
    case DRAFT = 'draft';
    case OPEN = 'open';
    case ON_HOLD = 'on_hold';
    case CONVERTED = 'converted';
    case CLOSED = 'closed';
    case WITHDRAWN = 'withdrawn';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
}
