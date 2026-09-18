<?php

namespace App\Domain\Enums;

enum ApprovalStatus: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case NeedsRework = 'needs_rework';
}
