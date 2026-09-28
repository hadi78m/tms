<?php

namespace App\Domain\Exceptions;

/**
 * V1.11 — V11-01 (OD-6-d): a concurrent Supervisor assignment hit the
 * idx_active_project_supervisor partial unique index. Mirror of
 * TaskAssignmentRaceConditionException (C-15).
 */
class ProjectMembershipRaceConditionException extends DomainException
{
    public function __construct(string $message = 'Another Supervisor assignment for this project is currently being processed. Please try again.')
    {
        parent::__construct($message);
    }
}
