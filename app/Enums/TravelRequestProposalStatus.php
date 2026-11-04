<?php

namespace App\Enums;

enum TravelRequestProposalStatus: string
{
    case SUBMITTED = 'submitted';
    case SELECTED = 'selected';
    case NOT_SELECTED = 'not_selected';
    case WITHDRAWN = 'withdrawn';
    case EXPIRED = 'expired';
    case CLOSED = 'closed';
}
