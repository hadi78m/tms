<?php

namespace App\Domain\Rules;

use App\Domain\Exceptions\ContractorScopeViolationException;
use App\Models\Task;
use App\Models\User;

class TaskScopeService
{
    /**
     * V1.11 — DEC-049 Phase 1 · I-1: read-only actor-side contractor check for
     * the Policy layer. Behaviorally IDENTICAL to assertCanAccess — the truth
     * table lives here and nowhere else; assertCanAccess delegates to it.
     */
    public function canAccess(Task $task, User $actor): bool
    {
        // If actor has a contractor_id, they can only access tasks for their contractor
        return $actor->contractor_id === null || (int) $task->contractor_id === (int) $actor->contractor_id;
    }

    public function assertCanAccess(Task $task, User $actor): void
    {
        if (! $this->canAccess($task, $actor)) {
            throw new ContractorScopeViolationException('Contractor does not have access to this task.');
        }
    }

    public function assertCanActAsAssignedContractor(Task $task, User $actor): void
    {
        // For Phase S1: Check if actor is the active assignee or authorized contractor manager
    }

    public function assertCanManageContract(Task $task, User $actor): void
    {
        // For Phase S1: Check if actor has contract management permissions
    }
}
