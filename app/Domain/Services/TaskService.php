<?php

namespace App\Domain\Services;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\DTOs\CreateTaskData;
use App\Domain\Enums\TaskPriority;
use App\Domain\Enums\TaskStatus;
use App\Domain\Exceptions\CircularDependencyException;
use App\Domain\Exceptions\TaskBlockedException;
use App\Domain\Rules\TaskStateTransition;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
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
                // T-2-A (DEC-037): the authoritative task kind arrives explicitly
                // from validated domain input — never a silent DB default.
                'task_type' => $data->task_type->value,
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

            // Check for unapproved predecessors (Blocked by dependencies)
            $unapprovedPredecessors = $task->dependenciesAsSuccessor()
                ->whereHas('predecessor', function ($query) {
                    $query->where('status', '!=', 'approved');
                })->exists();

            if ($unapprovedPredecessors) {
                throw new TaskBlockedException('تسک دارای پیش‌نیازهای تایید نشده است و امکان شروع آن وجود ندارد.');
            }

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

    public function addDependency(Task $task, Task $dependsOnTask, User $actor, string $type = 'fs'): TaskDependency
    {
        if ($task->id === $dependsOnTask->id) {
            throw new InvalidArgumentException('تسک نمی‌تواند به خودش وابستگی داشته باشد.');
        }

        if ($task->project_id !== $dependsOnTask->project_id) {
            throw new InvalidArgumentException('وابستگی فقط بین تسک‌های یک پروژه مجاز است.');
        }

        // Circular Dependency Check
        if ($this->hasCircularDependency($task, $dependsOnTask)) {
            throw new CircularDependencyException('امکان ایجاد این وابستگی وجود ندارد زیرا باعث ایجاد چرخه (Circular Dependency) می‌شود.');
        }

        return DB::transaction(function () use ($task, $dependsOnTask, $actor, $type) {
            $dependency = TaskDependency::firstOrCreate([
                'successor_task_id' => $task->id,
                'predecessor_task_id' => $dependsOnTask->id,
            ], [
                'dependency_type' => $type,
                'created_by' => $actor->id,
            ]);

            $this->auditService->log('task_dependency_added', $task, $actor, [], [
                'predecessor_id' => $dependsOnTask->id,
                'dependency_type' => $type,
            ]);

            return $dependency;
        });
    }

    public function removeDependency(Task $task, TaskDependency $dependency, User $actor): void
    {
        if ($dependency->successor_task_id !== $task->id) {
            throw new InvalidArgumentException('این وابستگی متعلق به تسک مشخص شده نیست.');
        }

        DB::transaction(function () use ($task, $dependency, $actor) {
            $dependency->delete();

            $this->auditService->log('task_dependency_removed', $task, $actor, [
                'predecessor_id' => $dependency->predecessor_task_id,
            ], []);
        });
    }

    protected function hasCircularDependency(Task $task, Task $dependsOnTask): bool
    {
        // We want to add $dependsOnTask as a predecessor to $task.
        // So $task -> depends on -> $dependsOnTask.
        // A cycle exists if $dependsOnTask (or any of its predecessors) depends on $task.

        $visited = [];
        $queue = [$dependsOnTask->id];

        while (! empty($queue)) {
            $currentId = array_shift($queue);

            if ($currentId === $task->id) {
                return true; // We found the target task in the ancestor chain!
            }

            if (! isset($visited[$currentId])) {
                $visited[$currentId] = true;

                $predecessors = TaskDependency::where('successor_task_id', $currentId)
                    ->pluck('predecessor_task_id')
                    ->toArray();

                $queue = array_merge($queue, $predecessors);
            }
        }

        return false;
    }
}
