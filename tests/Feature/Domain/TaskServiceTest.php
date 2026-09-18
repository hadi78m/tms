<?php

namespace Tests\Feature\Domain;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\DTOs\CreateTaskData;
use App\Domain\Enums\TaskPriority;
use App\Domain\Enums\TaskStatus;
use App\Domain\Exceptions\InvalidTaskTransitionException;
use App\Domain\Services\SlaService;
use App\Domain\Services\TaskService;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\SyncedContract;
use App\Models\SyncedContractor;
use App\Models\SyncedSystem;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TaskService $taskService;

    protected AuditServiceInterface $auditServiceMock;

    protected User $user;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user
        $this->user = User::factory()->create();

        // Create SyncedSystem
        $system = new SyncedSystem;
        $system->id = 1;
        $system->external_id = 'EXT-SYS-1';
        $system->source_system = 'SYS-A';
        $system->name = 'System A';
        $system->code = 'sys-a';
        $system->status = 'active';
        $system->source_updated_at = now();
        $system->last_synced_at = now();
        $system->sync_status = 'synced';
        $system->save();

        // Create SyncedContractor
        $contractor = new SyncedContractor;
        $contractor->id = 1;
        $contractor->external_id = 'EXT-C-1';
        $contractor->source_system = 'SYS-A';
        $contractor->name = 'Contractor 1';
        $contractor->code = 'C-1';
        $contractor->status = 'active';
        $contractor->source_updated_at = now();
        $contractor->last_synced_at = now();
        $contractor->sync_status = 'synced';
        $contractor->save();

        // Create a SyncedContract (required for Project)
        $contract = new SyncedContract;
        $contract->id = 1;
        $contract->contract_number = 'C-123';
        $contract->external_id = 'EXT-123';
        $contract->source_system = 'SYS-A';
        $contract->title = 'Test Contract';
        $contract->contractor_id = $contractor->id;
        $contract->system_id = $system->id;
        $contract->amount = 1000;
        $contract->status = 'active';
        $contract->start_date = '2026-01-01';
        $contract->end_date = '2026-12-31';
        $contract->sync_status = 'synced';
        $contract->source_updated_at = now();
        $contract->last_synced_at = now();
        $contract->save();

        // Create a Project
        $project = new Project;
        $project->id = 1;
        $project->contract_id = $contract->id;
        $project->contractor_id = $contractor->id;
        $project->name = 'Test Project';
        $project->status = 'active';
        $project->start_date = '2026-01-01';
        $project->end_date = '2026-12-31';
        $project->save();
        $this->project = $project;

        // Mock AuditService
        $this->auditServiceMock = $this->createMock(AuditServiceInterface::class);
        $this->app->instance(AuditServiceInterface::class, $this->auditServiceMock);

        $this->taskService = $this->app->make(TaskService::class);
    }

    protected function createTestTask(array $attributes = []): Task
    {
        $task = new Task;
        $task->project_id = $this->project->id;
        $task->contract_id = $this->project->contract_id;
        $task->contractor_id = $this->project->contractor_id;
        $task->title = $attributes['title'] ?? 'Test Title';
        $task->status = $attributes['status'] ?? TaskStatus::Draft->value;
        $task->weight = $attributes['weight'] ?? 10;
        $task->priority = $attributes['priority'] ?? TaskPriority::Normal->value;
        $task->created_by = $this->user->id;
        $task->save();

        return $task;
    }

    public function test_it_can_create_a_task()
    {
        $data = new CreateTaskData(
            project_id: $this->project->id,
            wbs_phase_id: null,
            title: 'Test Task Title',
            description: 'Task description',
            priority: TaskPriority::Normal,
            weight: 15.5,
            planned_start_date: '2026-09-01',
            planned_due_date: '2026-09-30',
            parent_task_id: null
        );

        $this->auditServiceMock->expects($this->once())
            ->method('log')
            ->with(
                $this->equalTo('task_created'),
                $this->isInstanceOf(Task::class),
                $this->equalTo($this->user)
            )
            ->willReturn(new ActivityLog);

        $task = $this->taskService->create($data, $this->user);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'contract_id' => $this->project->contract_id,
            'contractor_id' => $this->project->contractor_id,
            'title' => 'Test Task Title',
            'status' => TaskStatus::Draft->value,
            'weight' => 15.5,
            'priority' => TaskPriority::Normal->value,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_it_can_update_allowed_fields_only()
    {
        // First create a task
        $task = $this->createTestTask([
            'title' => 'Old Title',
            'status' => TaskStatus::Draft->value,
            'weight' => 10,
            'priority' => TaskPriority::Normal->value,
        ]);

        $this->auditServiceMock->expects($this->once())
            ->method('log')
            ->with($this->equalTo('task_updated_sensitive_field'))
            ->willReturn(new ActivityLog);

        $updatedTask = $this->taskService->update($task, [
            'title' => 'New Title',
            'priority' => TaskPriority::High,
        ], $this->user);

        $this->assertEquals('New Title', $updatedTask->title);
        $this->assertEquals(TaskPriority::High->value, $updatedTask->priority);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'New Title',
            'priority' => TaskPriority::High->value,
        ]);
    }

    public function test_update_throws_exception_if_status_or_weight_is_modified()
    {
        $task = $this->createTestTask([
            'title' => 'Title',
            'status' => TaskStatus::Draft->value,
            'weight' => 10,
            'priority' => TaskPriority::Normal->value,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->taskService->update($task, ['weight' => 20], $this->user);
    }

    public function test_it_can_submit_for_review()
    {
        $task = $this->createTestTask([
            'title' => 'Title',
            'status' => TaskStatus::InProgress->value,
            'weight' => 10,
            'priority' => TaskPriority::Normal->value,
        ]);

        // Start SLA so it can be stopped by submitForReview
        $slaService = $this->app->make(SlaService::class);
        $slaService->startResolutionSla($task);

        $this->auditServiceMock->expects($this->once())
            ->method('log')
            ->with($this->equalTo('task_submitted_for_review'))
            ->willReturn(new ActivityLog);

        $this->taskService->submitForReview($task, $this->user);

        $this->assertEquals(TaskStatus::SubmittedForReview->value, $task->status);
    }

    public function test_submit_for_review_fails_on_invalid_state()
    {
        $task = $this->createTestTask([
            'title' => 'Title',
            'status' => TaskStatus::Draft->value,
            'weight' => 10,
            'priority' => TaskPriority::Normal->value,
        ]);

        $this->expectException(InvalidTaskTransitionException::class);
        $this->taskService->submitForReview($task, $this->user);
    }

    public function test_it_can_cancel_task()
    {
        $task = $this->createTestTask([
            'title' => 'Title',
            'status' => TaskStatus::Draft->value,
            'weight' => 10,
            'priority' => TaskPriority::Normal->value,
        ]);

        $this->auditServiceMock->expects($this->once())
            ->method('log')
            ->with($this->equalTo('task_status_changed'))
            ->willReturn(new ActivityLog);

        $this->taskService->cancel($task, $this->user);

        $this->assertEquals(TaskStatus::Cancelled->value, $task->status);
    }
}
