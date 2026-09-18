<?php

namespace App\Domain\Services;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\DTOs\CreateTaskData;
use App\Domain\Enums\TaskPriority;
use App\Domain\Enums\TaskStatus;
use App\Domain\Rules\TaskStateTransition;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaskService
{
    public function __construct(
        protected AuditServiceInterface $auditService,
        protected TaskStateTransition $stateTransition
    ) {}

    public function create(CreateTaskData $data, User $actor): Task
    {
        return DB::transaction(function () use ($data, $actor) {
            $project = Project::findOrFail($data->project_id);

            $task = Task::create([
                'project_id' => $data->project_id,
                'contract_id' => $project->contract_id,
                'contractor_id' => $project->contractor_id,
                'wbs_phase_id' => $data->wbs_phase_id,
                'title' => $data->title,
                'description' => $data->description,
                'priority' => $data->priority,
                'weight' => $data->weight,
                'planned_start_at' => $data->planned_start_date,
                'planned_due_at' => $data->planned_due_date,
                'parent_task_id' => $data->parent_task_id,
                'status' => 'draft',
                'created_by' => $actor->id,
            ]);

            $this->auditService->log('task_created', $task, $actor, [], $task->toArray());

            return $task;
        });
    }

    public function update(Task $task, array $fields, User $actor): Task
    {
        return DB::transaction(function () use ($task, $fields, $actor) {
            $allowedFields = ['title', 'description', 'priority', 'planned_start_at', 'planned_due_at', 'parent_task_id'];

            // Prevent changing forbidden fields
            if (array_key_exists('status', $fields) || array_key_exists('weight', $fields)) {
                throw new InvalidArgumentException('Cannot update status or weight through generic update.');
            }

            $oldValues = [];
            $newValues = [];

            foreach ($fields as $key => $value) {
                if (in_array($key, $allowedFields)) {
                    $oldValues[$key] = $task->getAttribute($key);

                    // Handle Enums correctly
                    if ($key === 'priority' && $value instanceof TaskPriority) {
                        $value = $value->value;
                    }

                    $task->setAttribute($key, $value);
                    $newValues[$key] = $value;
                }
            }

            if ($task->isDirty()) {
                $task->save();
                $this->auditService->log('task_updated_sensitive_field', $task, $actor, $oldValues, $newValues);
            }

            return $task;
        });
    }

    public function submitForReview(Task $task, User $actor): Task
    {
        return DB::transaction(function () use ($task, $actor) {
            $currentStatus = TaskStatus::from($task->status);
            $this->stateTransition->assertCanTransition($currentStatus, TaskStatus::SubmittedForReview);

            $oldStatus = $task->status;
            $task->status = TaskStatus::SubmittedForReview->value;
            $task->save();

            app(SlaService::class)->stopResolutionSla($task);

            $this->auditService->log('task_submitted_for_review', $task, $actor, ['status' => $oldStatus], ['status' => $task->status]);

            return $task;
        });
    }

    public function cancel(Task $task, User $actor): Task
    {
        return DB::transaction(function () use ($task, $actor) {
            $currentStatus = TaskStatus::from($task->status);
            $this->stateTransition->assertCanTransition($currentStatus, TaskStatus::Cancelled);

            $oldStatus = $task->status;
            $task->status = TaskStatus::Cancelled->value;
            $task->save();

            $this->auditService->log('task_status_changed', $task, $actor, ['status' => $oldStatus], ['status' => $task->status], ['reason' => 'cancelled']);

            return $task;
        });
    }

    public function startProgress(Task $task, User $actor): Task
    {
        return DB::transaction(function () use ($task, $actor) {
            $currentStatus = TaskStatus::from($task->status);
            $this->stateTransition->assertCanTransition($currentStatus, TaskStatus::InProgress);

            $oldStatus = $task->status;
            $task->status = TaskStatus::InProgress->value;
            $task->save();

            // Notify SLA service about valid contractor response
            app(SlaService::class)->recordValidContractorResponse($task, $actor);

            $this->auditService->log('task_status_changed', $task, $actor, ['status' => $oldStatus], ['status' => $task->status]);

            return $task;
        });
    }

    public function needsRework(Task $task, User $actor, string $reason): Task
    {
        return DB::transaction(function () use ($task, $actor, $reason) {
            $currentStatus = TaskStatus::from($task->status);
            $this->stateTransition->assertCanTransition($currentStatus, TaskStatus::NeedsRework);

            $oldStatus = $task->status;
            $task->status = TaskStatus::NeedsRework->value;
            $task->save();

            $this->auditService->log('task_status_changed', $task, $actor, ['status' => $oldStatus], ['status' => $task->status], ['reason' => $reason]);

            return $task;
        });
    }
}
