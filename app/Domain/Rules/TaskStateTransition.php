<?php

namespace App\Domain\Rules;

use App\Domain\Enums\TaskStatus;
use App\Domain\Exceptions\InvalidTaskTransitionException;

class TaskStateTransition
{
    protected array $allowedTransitions = [
        'draft' => ['assigned', 'cancelled'],
        'assigned' => ['in_progress', 'cancelled'],
        'in_progress' => ['submitted_for_review', 'cancelled'],
        'submitted_for_review' => ['under_review'],
        'under_review' => ['supervisor_approved', 'needs_rework'],
        'supervisor_approved' => ['approved', 'needs_rework'],
        'needs_rework' => ['in_progress', 'cancelled'],
        'approved' => [],
        'cancelled' => [],
    ];

    public function canTransition(TaskStatus $from, TaskStatus $to): bool
    {
        if ($from === $to) {
            return false;
        }

        return in_array($to->value, $this->allowedTransitions[$from->value] ?? []);
    }

    public function assertCanTransition(TaskStatus $from, TaskStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw new InvalidTaskTransitionException(
                sprintf('Cannot transition task from %s to %s', $from->value, $to->value)
            );
        }
    }
}
