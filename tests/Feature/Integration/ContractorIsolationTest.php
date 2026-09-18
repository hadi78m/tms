<?php

namespace Tests\Feature\Integration;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\DTOs\AssignTaskData;
use App\Domain\Enums\TaskStatus;
use App\Domain\Exceptions\ContractorScopeViolationException;
use App\Domain\Services\TaskAssignmentService;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContractorIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function createTaskForContractor($taskId, $contractorId, $createdById)
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
        $task->status = TaskStatus::Draft->value;
        $task->created_by = $createdById;
        $task->save();

        return $task;
    }

    public function test_assignee_must_belong_to_the_same_contractor_as_the_task()
    {
        // Setup two contractors
        DB::table('synced_contractors')->insert([
            ['id' => 10, 'external_id' => 'ex10', 'source_system' => 'sys_a', 'name' => 'A', 'code' => 'C10', 'status' => 'active', 'sync_status' => 'synced', 'source_updated_at' => now(), 'last_synced_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'external_id' => 'ex20', 'source_system' => 'sys_a', 'name' => 'B', 'code' => 'C20', 'status' => 'active', 'sync_status' => 'synced', 'source_updated_at' => now(), 'last_synced_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $actor = User::factory()->create();

        // Assignee belongs to Contractor B
        $assignee = User::factory()->create(['contractor_id' => 20]);

        // Task belongs to Contractor A
        $task = $this->createTaskForContractor(100, 10, $actor->id);

        // Mock the AuditService
        $auditServiceMock = $this->createMock(AuditServiceInterface::class);
        $this->app->instance(AuditServiceInterface::class, $auditServiceMock);

        $assignmentService = app(TaskAssignmentService::class);
        $data = new AssignTaskData($task->id, $assignee->id, $actor->id, 'Cross-assignment test');

        $this->expectException(ContractorScopeViolationException::class);
        $assignmentService->assign($data, $actor);
    }
}
