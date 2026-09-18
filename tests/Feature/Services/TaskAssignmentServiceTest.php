<?php

namespace Tests\Feature\Services;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\DTOs\AssignTaskData;
use App\Domain\Enums\SlaType;
use App\Domain\Enums\TaskStatus;
use App\Domain\Exceptions\ContractorScopeViolationException;
use App\Domain\Services\TaskAssignmentService;
use App\Models\SyncedContractor;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaskAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TaskAssignmentService $service;

    protected AuditServiceInterface $auditServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditServiceMock = $this->createMock(AuditServiceInterface::class);
        $this->app->instance(AuditServiceInterface::class, $this->auditServiceMock);

        $this->service = app(TaskAssignmentService::class);
    }

    protected function createTaskWithRelations($taskId, $contractorId, $status, $createdById)
    {
        DB::table('synced_systems')->insertOrIgnore([
            'id' => 1,
            'external_id' => 'sys_1',
            'source_system' => 'sys_a',
            'name' => 'System',
            'code' => 'SYS',
            'status' => 'active',
            'sync_status' => 'synced',
            'source_updated_at' => now(),
            'last_synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('synced_contracts')->insertOrIgnore([
            'id' => $taskId,
            'system_id' => 1,
            'contractor_id' => $contractorId,
            'external_id' => 'contract_'.$taskId,
            'source_system' => 'sys_a',
            'contract_number' => 'C'.$taskId,
            'title' => 'Contract '.$taskId,
            'start_date' => now(),
            'end_date' => now()->addYear(),
            'amount' => 1000,
            'status' => 'active',
            'sync_status' => 'synced',
            'source_updated_at' => now(),
            'last_synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('projects')->insertOrIgnore([
            'id' => $taskId,
            'contract_id' => $taskId,
            'contractor_id' => $contractorId,
            'name' => 'Project '.$taskId,
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $task = new Task;
        $task->id = $taskId;
        $task->title = 'Test Task '.$taskId;
        $task->project_id = $taskId;
        $task->contract_id = $taskId;
        $task->contractor_id = $contractorId;
        $task->weight = 10;
        $task->priority = 'normal';
        $task->status = $status;
        $task->created_by = $createdById;
        $task->save();

        return $task;
    }

    public function test_assigns_draft_task_and_transitions_to_assigned()
    {
        $contractor = new SyncedContractor;
        $contractor->id = 1;
        $contractor->name = 'Test Contractor';
        $contractor->code = '1234567890';
        $contractor->external_id = 'ext_1';
        $contractor->source_system = 'system_a';
        $contractor->status = 'active';
        $contractor->sync_status = 'synced';
        $contractor->source_updated_at = now();
        $contractor->last_synced_at = now();
        $contractor->save();

        $assignee = new User;
        $assignee->id = 1;
        $assignee->name = 'Assignee';
        $assignee->email = 'assignee1@test.com';
        $assignee->national_code = '1111111111';
        $assignee->mobile = '09111111111';
        $assignee->password = 'password';
        $assignee->contractor_id = $contractor->id;
        $assignee->save();

        $actor = new User;
        $actor->id = 2;
        $actor->name = 'Actor';
        $actor->email = 'actor1@test.com';
        $actor->national_code = '1111111112';
        $actor->mobile = '09111111112';
        $actor->password = 'password';
        $actor->save();

        $task = $this->createTaskWithRelations(1, $contractor->id, TaskStatus::Draft->value, $actor->id);

        $data = new AssignTaskData($task->id, $assignee->id, $actor->id, 'Initial Assignment');
        $assignment = $this->service->assign($data, $actor);

        $this->assertEquals($assignee->id, $assignment->user_id);
        $this->assertNull($assignment->ended_at);
        $this->assertEquals('Initial Assignment', $assignment->reason);

        // Status transitioned to assigned
        $task->refresh();
        $this->assertEquals(TaskStatus::Assigned->value, $task->status);

        // SLA records created
        $this->assertTrue($task->slaRecords()->where('sla_type', SlaType::Response->value)->exists());
        $this->assertTrue($task->slaRecords()->where('sla_type', SlaType::Resolution->value)->exists());
    }

    public function test_rotates_active_assignment_without_changing_in_progress_status()
    {
        $contractor = new SyncedContractor;
        $contractor->id = 2;
        $contractor->name = 'Test Contractor 2';
        $contractor->code = '1234567891';
        $contractor->external_id = 'ext_2';
        $contractor->source_system = 'system_a';
        $contractor->status = 'active';
        $contractor->sync_status = 'synced';
        $contractor->source_updated_at = now();
        $contractor->last_synced_at = now();
        $contractor->save();

        $assigneeOld = new User;
        $assigneeOld->id = 3;
        $assigneeOld->name = 'Assignee Old';
        $assigneeOld->email = 'assigneeOld2@test.com';
        $assigneeOld->national_code = '2111111111';
        $assigneeOld->mobile = '09211111111';
        $assigneeOld->password = 'password';
        $assigneeOld->contractor_id = $contractor->id;
        $assigneeOld->save();

        $assigneeNew = new User;
        $assigneeNew->id = 4;
        $assigneeNew->name = 'Assignee New';
        $assigneeNew->email = 'assigneeNew2@test.com';
        $assigneeNew->national_code = '2111111112';
        $assigneeNew->mobile = '09211111112';
        $assigneeNew->password = 'password';
        $assigneeNew->contractor_id = $contractor->id;
        $assigneeNew->save();

        $actor = new User;
        $actor->id = 5;
        $actor->name = 'Actor 2';
        $actor->email = 'actor2@test.com';
        $actor->national_code = '2111111113';
        $actor->mobile = '09211111113';
        $actor->password = 'password';
        $actor->save();

        $task = $this->createTaskWithRelations(2, $contractor->id, TaskStatus::InProgress->value, $actor->id);

        // First assignment
        $dataOld = new AssignTaskData($task->id, $assigneeOld->id, $actor->id);
        $oldAssignment = $this->service->assign($dataOld, $actor);

        // Re-assignment
        $dataNew = new AssignTaskData($task->id, $assigneeNew->id, $actor->id, 'Rotation');
        $newAssignment = $this->service->assign($dataNew, $actor);

        $oldAssignment->refresh();
        $task->refresh();

        $this->assertNotNull($oldAssignment->ended_at); // Old assignment ended
        $this->assertNull($newAssignment->ended_at); // New assignment active
        $this->assertEquals($assigneeNew->id, $newAssignment->user_id);

        $this->assertEquals(TaskStatus::InProgress->value, $task->status); // Status untouched
    }

    public function test_assignment_is_idempotent()
    {
        $contractor = new SyncedContractor;
        $contractor->id = 3;
        $contractor->name = 'Test Contractor 3';
        $contractor->code = '1234567892';
        $contractor->external_id = 'ext_3';
        $contractor->source_system = 'system_a';
        $contractor->status = 'active';
        $contractor->sync_status = 'synced';
        $contractor->source_updated_at = now();
        $contractor->last_synced_at = now();
        $contractor->save();

        $assignee = new User;
        $assignee->id = 6;
        $assignee->name = 'Assignee 3';
        $assignee->email = 'assignee3@test.com';
        $assignee->national_code = '3111111111';
        $assignee->mobile = '09311111111';
        $assignee->password = 'password';
        $assignee->contractor_id = $contractor->id;
        $assignee->save();

        $actor = new User;
        $actor->id = 7;
        $actor->name = 'Actor 3';
        $actor->email = 'actor3@test.com';
        $actor->national_code = '3111111112';
        $actor->mobile = '09311111112';
        $actor->password = 'password';
        $actor->save();

        $task = $this->createTaskWithRelations(3, $contractor->id, TaskStatus::Draft->value, $actor->id);

        $data = new AssignTaskData($task->id, $assignee->id, $actor->id);

        $assignment1 = $this->service->assign($data, $actor);
        $assignment2 = $this->service->assign($data, $actor);

        $this->assertEquals($assignment1->id, $assignment2->id);
        $this->assertEquals(1, $task->assignments()->count());
    }

    public function test_enforces_contractor_scope()
    {
        $taskContractor = new SyncedContractor;
        $taskContractor->id = 4;
        $taskContractor->name = 'Task Contractor';
        $taskContractor->code = '1234567893';
        $taskContractor->external_id = 'ext_4';
        $taskContractor->source_system = 'system_a';
        $taskContractor->status = 'active';
        $taskContractor->sync_status = 'synced';
        $taskContractor->source_updated_at = now();
        $taskContractor->last_synced_at = now();
        $taskContractor->save();

        $otherContractor = new SyncedContractor;
        $otherContractor->id = 5;
        $otherContractor->name = 'Other Contractor';
        $otherContractor->code = '1234567894';
        $otherContractor->external_id = 'ext_5';
        $otherContractor->source_system = 'system_a';
        $otherContractor->status = 'active';
        $otherContractor->sync_status = 'synced';
        $otherContractor->source_updated_at = now();
        $otherContractor->last_synced_at = now();
        $otherContractor->save();

        $assignee = new User;
        $assignee->id = 8;
        $assignee->name = 'Assignee 4';
        $assignee->email = 'assignee4@test.com';
        $assignee->national_code = '4111111111';
        $assignee->mobile = '09411111111';
        $assignee->password = 'password';
        $assignee->contractor_id = $otherContractor->id;
        $assignee->save();

        $actor = new User;
        $actor->id = 9;
        $actor->name = 'Actor 4';
        $actor->email = 'actor4@test.com';
        $actor->national_code = '4111111112';
        $actor->mobile = '09411111112';
        $actor->password = 'password';
        $actor->save();

        $task = $this->createTaskWithRelations(4, $taskContractor->id, TaskStatus::Draft->value, $actor->id);

        $data = new AssignTaskData($task->id, $assignee->id, $actor->id);

        $this->expectException(ContractorScopeViolationException::class);
        $this->service->assign($data, $actor);
    }
}
