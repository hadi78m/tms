<?php

namespace Tests\Feature\Domain;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\Enums\ApprovalStatus;
use App\Domain\Enums\ApprovalType;
use App\Domain\Enums\TaskPriority;
use App\Domain\Enums\TaskStatus;
use App\Domain\Exceptions\InvalidApprovalException;
use App\Domain\Exceptions\InvalidTaskTransitionException;
use App\Domain\Exceptions\TaskAlreadyApprovedException;
use App\Domain\Services\ApprovalService;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\SyncedContract;
use App\Models\SyncedContractor;
use App\Models\SyncedSystem;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ApprovalService $approvalService;

    protected AuditServiceInterface $auditServiceMock;

    protected User $employerUser;

    protected User $contractorUser;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        parent::setUp();

        // Setup relations
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

        $this->employerUser = User::factory()->create(['contractor_id' => null]);
        $this->contractorUser = User::factory()->create(['contractor_id' => 1]);

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

        $this->auditServiceMock = $this->createMock(AuditServiceInterface::class);
        $this->app->instance(AuditServiceInterface::class, $this->auditServiceMock);

        $this->approvalService = $this->app->make(ApprovalService::class);
    }

    protected function createTestTask(array $attributes = []): Task
    {
        $task = new Task;
        $task->project_id = $this->project->id;
        $task->contract_id = $this->project->contract_id;
        $task->contractor_id = $this->project->contractor_id;
        $task->title = $attributes['title'] ?? 'Test Title';
        $task->status = $attributes['status'] ?? TaskStatus::UnderReview->value;
        $task->weight = $attributes['weight'] ?? 10;
        $task->priority = $attributes['priority'] ?? TaskPriority::Normal->value;
        $task->created_by = $this->employerUser->id;
        $task->save();

        return $task;
    }

    public function test_contractor_cannot_perform_final_approval()
    {
        $task = $this->createTestTask();

        $this->expectException(InvalidApprovalException::class);

        $this->approvalService->recordApproval(
            $task,
            $this->contractorUser,
            ApprovalType::Final->value,
            ApprovalStatus::Approved->value
        );
    }

    public function test_final_approval_must_be_on_under_review_task()
    {
        $task = $this->createTestTask(['status' => TaskStatus::InProgress->value]);

        $this->expectException(InvalidTaskTransitionException::class);

        $this->approvalService->recordApproval(
            $task,
            $this->employerUser,
            ApprovalType::Final->value,
            ApprovalStatus::Approved->value
        );
    }

    public function test_task_already_approved_throws_exception()
    {
        $task = $this->createTestTask(['status' => TaskStatus::Approved->value]);

        $this->expectException(TaskAlreadyApprovedException::class);

        $this->approvalService->recordApproval(
            $task,
            $this->employerUser,
            ApprovalType::Technical->value,
            ApprovalStatus::Approved->value
        );
    }

    public function test_final_approval_transitions_task_status_to_approved()
    {
        $task = $this->createTestTask(['status' => TaskStatus::UnderReview->value]);

        $this->auditServiceMock->expects($this->exactly(2))
            ->method('log')
            ->willReturn(new ActivityLog);

        $approval = $this->approvalService->recordApproval(
            $task,
            $this->employerUser,
            ApprovalType::Final->value,
            ApprovalStatus::Approved->value,
            'Looks good'
        );

        $this->assertEquals(ApprovalStatus::Approved->value, $approval->status);
        $this->assertEquals(ApprovalType::Final->value, $approval->approval_type);
        $this->assertEquals($this->employerUser->id, $approval->acted_by);
        $this->assertEquals(1, $approval->sequence);
        $this->assertEquals('Looks good', $approval->comment);

        $task->refresh();
        $this->assertEquals(TaskStatus::Approved->value, $task->status);
    }

    public function test_final_approval_needs_rework_transitions_task_status()
    {
        $task = $this->createTestTask(['status' => TaskStatus::UnderReview->value]);

        $this->auditServiceMock->expects($this->exactly(2))
            ->method('log')
            ->willReturn(new ActivityLog);

        $approval = $this->approvalService->recordApproval(
            $task,
            $this->employerUser,
            ApprovalType::Final->value,
            ApprovalStatus::NeedsRework->value,
            'Please fix the issues'
        );

        $task->refresh();
        $this->assertEquals(TaskStatus::NeedsRework->value, $task->status);
    }

    public function test_technical_approval_does_not_transition_status()
    {
        $task = $this->createTestTask(['status' => TaskStatus::UnderReview->value]);

        $this->auditServiceMock->expects($this->once())
            ->method('log')
            ->with($this->equalTo('task_approval_recorded'))
            ->willReturn(new ActivityLog);

        $approval = $this->approvalService->recordApproval(
            $task,
            $this->employerUser,
            ApprovalType::Technical->value,
            ApprovalStatus::Approved->value,
            'Tech approved'
        );

        $task->refresh();
        $this->assertEquals(TaskStatus::UnderReview->value, $task->status);
        $this->assertEquals(1, $approval->sequence);
    }
}
