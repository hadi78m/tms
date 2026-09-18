<?php

namespace App\Domain\Services;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\DTOs\AssignTaskData;
use App\Domain\Enums\TaskStatus;
use App\Domain\Exceptions\TaskAssignmentRaceConditionException;
use App\Domain\Rules\TaskScopeService;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class TaskAssignmentService
{
    public function __construct(
        protected AuditServiceInterface $auditService,
        protected TaskScopeService $scopeService,
        protected SlaService $slaService
    ) {}

    public function assign(AssignTaskData $data, User $actor): TaskAssignment
    {
        return DB::transaction(function () use ($data, $actor) {
            try {
                /** @var Task $task */
                $task = Task::where('id', $data->task_id)->lockForUpdate()->firstOrFail();
                $assignee = User::findOrFail($data->user_id);

                // Ensure the assignee is within the scope of the task (e.g. contractor matches)
                $this->scopeService->assertCanAccess($task, $assignee);

                // Get current active assignment and lock it
                /** @var TaskAssignment|null $activeAssignment */
                $activeAssignment = $task->activeAssignment()->lockForUpdate()->first();

                // Idempotency: If the current active assignment is to the same user, do nothing
                if ($activeAssignment && $activeAssignment->user_id === $data->user_id) {
                    return $activeAssignment;
                }

                // If changing assignment, end the current one
                if ($activeAssignment) {
                    $activeAssignment->ended_at = now();
                    $activeAssignment->save();

                    // Audit task_unassigned
                    $this->auditService->log(
                        'task_unassigned',
                        $task,
                        $actor,
                        ['user_id' => $activeAssignment->user_id],
                        ['user_id' => null]
                    );
                }

                // Create new assignment
                $newAssignment = TaskAssignment::create([
                    'task_id' => $task->id,
                    'user_id' => $assignee->id,
                    'assigned_by' => $actor->id,
                    'assigned_at' => now(),
                    'reason' => $data->reason,
                ]);

                // Transition status if draft
                $oldStatus = $task->status;
                if ($oldStatus === TaskStatus::Draft->value) {
                    $task->status = TaskStatus::Assigned->value;
                    $task->save();

                    $this->auditService->log(
                        'task_status_changed',
                        $task,
                        $actor,
                        ['status' => $oldStatus],
                        ['status' => $task->status]
                    );

                    // Start SLAs when a task is initially assigned
                    $this->slaService->startResponseSla($task);
                    $this->slaService->startResolutionSla($task);
                }

                // Audit task_assigned
                $this->auditService->log(
                    'task_assigned',
                    $task,
                    $actor,
                    $activeAssignment ? ['user_id' => $activeAssignment->user_id] : [],
                    ['user_id' => $assignee->id, 'reason' => $data->reason]
                );

                return $newAssignment;

            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'idx_active_assignment') || str_contains($e->getMessage(), 'task_assignments_active_unique')) {
                    throw new TaskAssignmentRaceConditionException;
                }
                throw $e;
            }
        });
    }
}
