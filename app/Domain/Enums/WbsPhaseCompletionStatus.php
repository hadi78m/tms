<?php

namespace App\Domain\Enums;

/**
 * Completion status of a WBS Phase.
 *
 * A WBS Phase carries no weight — only completion. The final authority is the
 * Project Supervisor, who may declare it completed, declare it not completed
 * (with a mandatory reason), or reopen a completed phase.
 */
enum WbsPhaseCompletionStatus: string
{
    case Pending = 'pending';

    case Completed = 'completed';

    case NotCompleted = 'not_completed';

    public function isPending(): bool
    {
        return $this === self::Pending;
    }
}
