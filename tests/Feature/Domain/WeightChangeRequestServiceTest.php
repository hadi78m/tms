<?php

namespace Tests\Feature\Domain;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\Enums\TaskPriority;
use App\Domain\Enums\TaskStatus;
use App\Domain\Exceptions\InvalidTaskTransitionException;
use App\Domain\Exceptions\InvalidWeightException;
use App\Domain\Exceptions\PendingRequestExistsException;
use App\Domain\Services\WeightChangeRequestService;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\SyncedContract;
use App\Models\SyncedContractor;
use App\Models\SyncedSystem;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeightChangeRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WeightChangeRequestService $weightService;

    protected AuditServiceInterface $auditServiceMock;

    protected User $employerUser;

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

        $this->weightService = $this->app->make(WeightChangeRequestService::class);
    }

    protected function createTestTask(array $attributes = []): Task
    {
        $task = new Task;
        $task->project_id = $this->project->id;
        $task->contract_id = $this->project->contract_id;
        $task->contractor_id = $this->project->contractor_id;
        $task->title = $attributes['title'] ?? 'Test Title';
        $task->status = $attributes['status'] ?? TaskStatus::InProgress->value;
        $task->weight = $attributes['weight'] ?? 10;
        $task->priority = $attributes['priority'] ?? TaskPriority::Normal->value;
        $task->created_by = $this->employerUser->id;
        $task->save();

        return $task;
    }

    public function test_it_can_request_weight_change()
    {
        $task = $this->createTestTask(['weight' => 20]);

        $this->auditServiceMock->expects($this->once())
            ->method('log')
            ->with($this->equalTo('task_weight_change_requested'))
            ->willReturn(new ActivityLog);

        $request = $this->weightService->requestChange($task, $this->employerUser, 30, 'Need more weight');

        $this->assertEquals(20, $request->old_weight);
        $this->assertEquals(30, $request->new_weight);
        $this->assertEquals('pending', $request->status);
        $this->assertEquals('Need more weight', $request->reason);

        $this->assertDatabaseHas('weight_change_requests', [
            'id' => $request->id,
            'status' => 'pending',
            'old_weight' => 20.00,
            'new_weight' => 30.00,
        ]);
    }

    public function test_cannot_request_if_pending_exists()
    {
        $task = $this->createTestTask();

        $this->weightService->requestChange($task, $this->employerUser, 30, 'First request');

        $this->expectException(PendingRequestExistsException::class);
        $this->weightService->requestChange($task, $this->employerUser, 40, 'Second request');
    }

    public function test_weight_must_be_between_0_and_100()
    {
        $task = $this->createTestTask();

        $this->expectException(InvalidWeightException::class);
        $this->weightService->requestChange($task, $this->employerUser, 150, 'Invalid request');
    }

    public function test_weight_must_not_be_negative()
    {
        $task = $this->createTestTask();

        $this->expectException(InvalidWeightException::class);
        $this->weightService->requestChange($task, $this->employerUser, -10, 'Invalid request');
    }

    public function test_approve_request_updates_task_weight()
    {
        $task = $this->createTestTask(['weight' => 20]);

        $this->auditServiceMock->expects($this->exactly(2))
            ->method('log')
            ->willReturn(new ActivityLog);

        $request = $this->weightService->requestChange($task, $this->employerUser, 30, 'Need more weight');

        $this->weightService->approveRequest($request, $this->employerUser);

        $request->refresh();
        $this->assertEquals('approved', $request->status);
        $this->assertEquals($this->employerUser->id, $request->approved_by);

        $task->refresh();
        $this->assertEquals(30.00, $task->weight);
    }

    public function test_reject_request_does_not_update_task_weight()
    {
        $task = $this->createTestTask(['weight' => 20]);

        $this->auditServiceMock->expects($this->exactly(2))
            ->method('log')
            ->willReturn(new ActivityLog);

        $request = $this->weightService->requestChange($task, $this->employerUser, 30, 'Need more weight');

        $this->weightService->rejectRequest($request, $this->employerUser, 'Not allowed');

        $request->refresh();
        $this->assertEquals('rejected', $request->status);
        $this->assertEquals($this->employerUser->id, $request->approved_by);

        $task->refresh();
        $this->assertEquals(20.00, $task->weight);
    }

    public function test_cannot_approve_already_approved_request()
    {
        $task = $this->createTestTask(['weight' => 20]);
        $request = $this->weightService->requestChange($task, $this->employerUser, 30, 'Need more weight');

        $this->weightService->approveRequest($request, $this->employerUser);

        $this->expectException(InvalidTaskTransitionException::class);
        $this->weightService->approveRequest($request, $this->employerUser);
    }
}
