<?php

namespace Tests\Feature\Integration;

use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\SlaEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImmutableRecordTest extends TestCase
{
    use RefreshDatabase;

    protected function createTaskForContractor($taskId, $contractorId, $createdById)
    {
        DB::table('synced_systems')->insertOrIgnore([
            'id' => 1, 'external_id' => 'sys_1', 'source_system' => 'sys_a', 'name' => 'System', 'code' => 'SYS', 'status' => 'active', 'sync_status' => 'synced', 'source_updated_at' => now(), 'last_synced_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('synced_contractors')->insertOrIgnore([
            'id' => $contractorId, 'external_id' => 'con_'.$contractorId, 'source_system' => 'sys_a', 'name' => 'Contractor', 'code' => 'C'.$contractorId, 'status' => 'active', 'sync_status' => 'synced', 'source_updated_at' => now(), 'last_synced_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('synced_contracts')->insertOrIgnore([
            'id' => $taskId, 'system_id' => 1, 'contractor_id' => $contractorId, 'external_id' => 'contract_'.$taskId, 'source_system' => 'sys_a', 'contract_number' => 'C'.$taskId, 'title' => 'Contract', 'start_date' => now(), 'end_date' => now()->addYear(), 'amount' => 1000, 'status' => 'active', 'sync_status' => 'synced', 'source_updated_at' => now(), 'last_synced_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('projects')->insertOrIgnore([
            'id' => $taskId, 'contract_id' => $taskId, 'contractor_id' => $contractorId, 'name' => 'Project', 'status' => 'active', 'start_date' => now(), 'end_date' => now()->addYear(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('tasks')->insert([
            'id' => $taskId, 'title' => 'Test Task', 'project_id' => $taskId, 'contract_id' => $taskId, 'contractor_id' => $contractorId, 'weight' => 10, 'priority' => 'normal', 'status' => 'draft', 'created_by' => $createdById, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_activity_log_is_immutable()
    {
        $user = User::factory()->create();

        $log = ActivityLog::create([
            'action' => 'test_event',
            'entity_type' => 'App\\Models\\Task',
            'entity_id' => 1,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('activity_logs', ['id' => $log->id]);

        // In our models, we used `booted` to return false on updating/deleting
        $log->action = 'changed_event';
        $this->assertFalse($log->save());
    }

    public function test_activity_log_deletion_is_prevented()
    {
        $user = User::factory()->create();

        $log = ActivityLog::create([
            'action' => 'test_event',
            'entity_type' => 'App\\Models\\Task',
            'entity_id' => 1,
            'user_id' => $user->id,
        ]);

        $this->assertFalse($log->delete());
    }

    public function test_approval_record_is_immutable()
    {
        $user = User::factory()->create();

        $this->createTaskForContractor(1, 10, $user->id);

        $approval = Approval::create([
            'task_id' => 1,
            'sequence' => 1,
            'approval_type' => 'technical',
            'status' => 'approved',
            'comment' => 'Looks good',
            'requested_at' => now(),
        ]);

        $approval->status = 'needs_rework';
        $this->assertFalse($approval->save());
    }

    public function test_sla_event_record_is_immutable()
    {
        $user = User::factory()->create();
        $this->createTaskForContractor(1, 10, $user->id);

        DB::table('sla_records')->insert([
            'id' => 1,
            'task_id' => 1,
            'sla_type' => 'response',
            'status' => 'active',
            'target_duration' => 60,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $event = SlaEvent::create([
            'sla_record_id' => 1,
            'event_type' => 'stop',
            'occurred_at' => now(),
            'created_at' => now(),
        ]);

        $event->event_type = 'start';
        $this->assertFalse($event->save());
    }
}
