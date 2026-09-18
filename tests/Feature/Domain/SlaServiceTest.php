<?php

namespace Tests\Feature\Domain;

use App\Domain\Enums\SlaEventType;
use App\Domain\Enums\SlaStatus;
use App\Domain\Enums\SlaType;
use App\Domain\Exceptions\SlaAlreadyStartedException;
use App\Domain\Exceptions\SlaAlreadyStoppedException;
use App\Domain\Services\SlaService;
use App\Models\Project;
use App\Models\SyncedContract;
use App\Models\SyncedContractor;
use App\Models\SyncedSystem;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SlaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SlaService $slaService;

    protected User $employerUser;

    protected User $contractorUser;

    protected User $otherContractorUser;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

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
        $this->contractorUser = User::factory()->create(['contractor_id' => $contractor->id]);
        $this->otherContractorUser = User::factory()->create(['contractor_id' => $contractor->id]);

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

        $this->slaService = $this->app->make(SlaService::class);
    }

    protected function createTestTask(): Task
    {
        $task = new Task;
        $task->project_id = $this->project->id;
        $task->contract_id = $this->project->contract_id;
        $task->contractor_id = $this->project->contractor_id;
        $task->title = 'Test Task SLA';
        $task->status = 'assigned';
        $task->priority = 'normal';
        $task->weight = 10;
        $task->created_by = $this->employerUser->id;
        $task->save();

        return $task;
    }

    public function test_it_starts_response_sla_and_logs_event()
    {
        $task = $this->createTestTask();

        $sla = $this->slaService->startResponseSla($task, $this->employerUser);

        $this->assertDatabaseHas('sla_records', [
            'id' => $sla->id,
            'sla_type' => SlaType::Response->value,
            'status' => SlaStatus::Active->value,
        ]);

        $this->assertDatabaseHas('sla_events', [
            'sla_record_id' => $sla->id,
            'event_type' => SlaEventType::Start->value,
            'user_id' => $this->employerUser->id,
        ]);
    }

    public function test_it_cannot_start_duplicate_response_sla()
    {
        $task = $this->createTestTask();

        $this->slaService->startResponseSla($task, $this->employerUser);

        $this->expectException(SlaAlreadyStartedException::class);
        $this->slaService->startResponseSla($task, $this->employerUser);
    }

    public function test_it_stops_response_sla_and_calculates_duration()
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));
        $task = $this->createTestTask();
        $sla = $this->slaService->startResponseSla($task, $this->employerUser);

        Carbon::setTestNow(Carbon::parse('2026-01-01 10:30:00'));
        $this->slaService->stopResponseSla($task, $this->contractorUser, 'Replied');

        $sla->refresh();
        $this->assertEquals(SlaStatus::Stopped->value, $sla->status);
        $this->assertEquals(30, $sla->actual_duration);
        $this->assertFalse($sla->is_breached);

        $this->assertDatabaseHas('sla_events', [
            'sla_record_id' => $sla->id,
            'event_type' => SlaEventType::Stop->value,
            'user_id' => $this->contractorUser->id,
            'reason' => 'Replied',
        ]);
    }

    public function test_it_marks_breach_if_duration_exceeds_target()
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));
        $task = $this->createTestTask();
        $sla = $this->slaService->startResponseSla($task, $this->employerUser);

        Carbon::setTestNow(Carbon::parse('2026-01-01 11:05:00'));
        $this->slaService->stopResponseSla($task, $this->contractorUser);

        $sla->refresh();
        $this->assertEquals(65, $sla->actual_duration);
        $this->assertTrue($sla->is_breached);
        $this->assertNotNull($sla->breached_at);
    }

    public function test_cannot_stop_already_stopped_sla()
    {
        $task = $this->createTestTask();
        $this->slaService->startResponseSla($task, $this->employerUser);
        $this->slaService->stopResponseSla($task, $this->contractorUser);

        $this->expectException(SlaAlreadyStoppedException::class);
        $this->slaService->stopResponseSla($task, $this->contractorUser);
    }

    public function test_record_valid_contractor_response_stops_sla()
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));
        $task = $this->createTestTask();

        // Setup assignment
        TaskAssignment::create([
            'task_id' => $task->id,
            'user_id' => $this->contractorUser->id,
            'assigned_by' => $this->employerUser->id,
            'assigned_at' => now(),
            'started_at' => now(),
        ]);

        $sla = $this->slaService->startResponseSla($task, $this->employerUser);

        // Submitting with wrong contractor should not stop SLA
        $this->slaService->recordValidContractorResponse($task, $this->otherContractorUser);
        $sla->refresh();
        $this->assertEquals(SlaStatus::Active->value, $sla->status);

        // Submitting with employer should not stop SLA
        $this->slaService->recordValidContractorResponse($task, $this->employerUser);
        $sla->refresh();
        $this->assertEquals(SlaStatus::Active->value, $sla->status);

        Carbon::setTestNow(Carbon::parse('2026-01-01 10:15:00'));
        // Submitting with correct assigned contractor should stop SLA
        $this->slaService->recordValidContractorResponse($task, $this->contractorUser);
        $sla->refresh();
        $this->assertEquals(SlaStatus::Stopped->value, $sla->status);
        $this->assertEquals(15, $sla->actual_duration);
    }
}
