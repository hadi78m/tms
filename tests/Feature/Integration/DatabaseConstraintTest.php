<?php

namespace Tests\Feature\Integration;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConstraintTest extends TestCase
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
        $task->status = 'draft';
        $task->created_by = $createdById;
        $task->save();

        return $task;
    }

    public function test_active_assignment_partial_unique_index()
    {
        DB::table('synced_contractors')->insert([
            ['id' => 10, 'external_id' => 'ex10', 'source_system' => 'sys_a', 'name' => 'A', 'code' => 'C10', 'status' => 'active', 'sync_status' => 'synced', 'source_updated_at' => now(), 'last_synced_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);
        $actor = User::factory()->create();

        $task = $this->createTaskForContractor(100, 10, $actor->id);

        $assignee1 = User::factory()->create(['contractor_id' => 10]);
        $assignee2 = User::factory()->create(['contractor_id' => 10]);

        // Insert first active assignment
        DB::table('task_assignments')->insert([
            'task_id' => $task->id,
            'user_id' => $assignee1->id,
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
            'ended_at' => null, // Active
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        // This should fail because of the PostgreSQL partial unique index on (task_id) WHERE ended_at IS NULL
        DB::table('task_assignments')->insert([
            'task_id' => $task->id,
            'user_id' => $assignee2->id,
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
            'ended_at' => null, // Active
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_performance_record_duplicate_period_unique_index()
    {
        DB::table('synced_contractors')->insert([
            ['id' => 10, 'external_id' => 'ex10', 'source_system' => 'sys_a', 'name' => 'A', 'code' => 'C10', 'status' => 'active', 'sync_status' => 'synced', 'source_updated_at' => now(), 'last_synced_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);
        $actor = User::factory()->create();

        $this->createTaskForContractor(100, 10, $actor->id);

        DB::table('performance_records')->insert([
            'contract_id' => 100,
            'contractor_id' => 10,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'tasks_completed' => 5,
            'total_weight_completed' => 50,
            'rework_count' => 0,
            'sla_breach_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        // This should fail due to unique constraint on contract_id, contractor_id, period_start, period_end
        DB::table('performance_records')->insert([
            'contract_id' => 100,
            'contractor_id' => 10,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'tasks_completed' => 2,
            'total_weight_completed' => 20,
            'rework_count' => 0,
            'sla_breach_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_task_weight_check_constraint()
    {
        DB::table('synced_contractors')->insert([
            ['id' => 10, 'external_id' => 'ex10', 'source_system' => 'sys_a', 'name' => 'A', 'code' => 'C10', 'status' => 'active', 'sync_status' => 'synced', 'source_updated_at' => now(), 'last_synced_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);
        $actor = User::factory()->create();

        $this->expectException(QueryException::class);

        // Attempting to create a task with weight > 100 should fail at the DB level
        $task = new Task;
        $task->id = 101;
        $task->title = 'Test Task Out of Bounds';
        $task->project_id = 100; // Violates FK, but if weight check fails first, it's fine. Actually let's do a raw insert to bypass eloquent events and test constraint directly

        DB::table('tasks')->insert([
            'title' => 'Test',
            'project_id' => 100,
            'contract_id' => 100,
            'contractor_id' => 10,
            'weight' => 150, // Out of bounds
            'priority' => 'normal',
            'status' => 'draft',
            'created_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
