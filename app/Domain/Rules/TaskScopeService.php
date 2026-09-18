<?php

namespace App\Domain\Rules;

use App\Domain\Exceptions\ContractorScopeViolationException;
use App\Models\Task;
use App\Models\User;

class TaskScopeService
{
    public function assertCanAccess(Task $task, User $actor): void
    {
        // If actor has a contractor_id, they can only access tasks for their contractor
        if ($actor->contractor_id !== null && $task->contractor_id !== $actor->contractor_id) {
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
