<?php

namespace App\Domain\Enums;

enum TaskStatus: string
{
    case Draft = 'draft';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case SubmittedForReview = 'submitted_for_review';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case NeedsRework = 'needs_rework';
    case Cancelled = 'cancelled';
}
