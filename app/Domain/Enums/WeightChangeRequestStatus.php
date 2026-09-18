<?php

namespace App\Domain\Enums;

enum WeightChangeRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
