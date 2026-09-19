<?php

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\Enums\ApprovalStatus;
use App\Domain\Enums\ApprovalType;
use App\Domain\Enums\TaskPriority;
use App\Domain\Enums\TaskStatus;
use App\Domain\Exceptions\InvalidApprovalException;
use App\Domain\Exceptions\InvalidTaskTransitionException;
use App\Domain\Services\ApprovalService;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\SyncedContract;
use App\Models\SyncedContractor;
use App\Models\SyncedSystem;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function createTierTestEntities(): array
{
    $system = SyncedSystem::create([
        'external_id' => 'T-SYS', 'source_system' => 'TEST',
        'name' => 'Test System', 'code' => 't-sys', 'status' => 'active',
        'source_updated_at' => now(), 'last_synced_at' => now(), 'sync_status' => 'synced',
    ]);

    $contractor = SyncedContractor::create([
        'external_id' => 'T-CONT', 'source_system' => 'TEST',
        'name' => 'Test Contractor', 'code' => 'T-1', 'status' => 'active',
        'source_updated_at' => now(), 'last_synced_at' => now(), 'sync_status' => 'synced',
    ]);

    $contract = SyncedContract::create([
        'contract_number' => 'C-T-001', 'external_id' => 'T-CTR', 'source_system' => 'TEST',
        'title' => 'Test Contract', 'contractor_id' => $contractor->id, 'system_id' => $system->id,
        'amount' => 1000, 'status' => 'active', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        'sync_status' => 'synced', 'source_updated_at' => now(), 'last_synced_at' => now(),
    ]);

    $project = Project::create([
        'contract_id' => $contract->id, 'contractor_id' => $contractor->id,
        'name' => 'Test Project', 'status' => 'active',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $supervisorUser = User::factory()->create(['contractor_id' => null]);
    $employerUser = User::factory()->create(['contractor_id' => null]);
    $contractorUser = User::factory()->create(['contractor_id' => $contractor->id]);

    return compact('project', 'contractor', 'contract', 'supervisorUser', 'employerUser', 'contractorUser');
}

function createTierTask(Project $project, SyncedContract $contract, SyncedContractor $contractor, User $creator, string $status = 'under_review'): Task
{
    $task = new Task;
    $task->project_id = $project->id;
    $task->contract_id = $contract->id;
    $task->contractor_id = $contractor->id;
    $task->title = 'Two-Tier Test Task';
    $task->status = $status;
    $task->weight = 10;
    $task->priority = TaskPriority::Normal->value;
    $task->created_by = $creator->id;
    $task->save();

    return $task;
}

// ─── Tests ────────────────────────────────────────────────────────────────────

test('full two-tier approval: supervisor then employer transitions task to approved', function () {
    $entities = createTierTestEntities();
    extract($entities);

    $auditMock = mock(AuditServiceInterface::class);
    $auditMock->shouldReceive('log')->andReturn(new ActivityLog);
    app()->instance(AuditServiceInterface::class, $auditMock);

    $service = app(ApprovalService::class);

    // ① تسک در under_review
    $task = createTierTask($project, $contract, $contractor, $supervisorUser);

    // ② ناظر تایید فنی می‌دهد
    $techApproval = $service->recordTechnicalApproval($task, $supervisorUser, ApprovalStatus::Approved->value, 'Technical OK');

    expect($techApproval->approval_type)->toBe(ApprovalType::Technical->value);
    $task->refresh();
    expect($task->status)->toBe(TaskStatus::SupervisorApproved->value);
    expect($task->supervisor_approved_at)->not->toBeNull();

    // ③ بهره‌بردار تایید نهایی می‌دهد
    $finalApproval = $service->recordFinalApproval($task, $employerUser, ApprovalStatus::Approved->value, 'Final OK');

    expect($finalApproval->approval_type)->toBe(ApprovalType::Final->value);
    $task->refresh();
    expect($task->status)->toBe(TaskStatus::Approved->value);
    expect($task->approved_at)->not->toBeNull();
});

test('employer cannot perform final approval before supervisor approves', function () {
    $entities = createTierTestEntities();
    extract($entities);

    $auditMock = mock(AuditServiceInterface::class);
    app()->instance(AuditServiceInterface::class, $auditMock);

    $service = app(ApprovalService::class);
    $task = createTierTask($project, $contract, $contractor, $supervisorUser, TaskStatus::UnderReview->value);

    expect(fn () => $service->recordFinalApproval($task, $employerUser, ApprovalStatus::Approved->value))
        ->toThrow(InvalidTaskTransitionException::class);
});

test('supervisor rejection sends task to needs_rework', function () {
    $entities = createTierTestEntities();
    extract($entities);

    $auditMock = mock(AuditServiceInterface::class);
    $auditMock->shouldReceive('log')->andReturn(new ActivityLog);
    app()->instance(AuditServiceInterface::class, $auditMock);

    $service = app(ApprovalService::class);
    $task = createTierTask($project, $contract, $contractor, $supervisorUser);

    $service->recordTechnicalApproval($task, $supervisorUser, ApprovalStatus::NeedsRework->value, 'Needs work');

    $task->refresh();
    expect($task->status)->toBe(TaskStatus::NeedsRework->value);
});

test('employer rejection after supervisor approval sends task to needs_rework', function () {
    $entities = createTierTestEntities();
    extract($entities);

    $auditMock = mock(AuditServiceInterface::class);
    $auditMock->shouldReceive('log')->andReturn(new ActivityLog);
    app()->instance(AuditServiceInterface::class, $auditMock);

    $service = app(ApprovalService::class);
    $task = createTierTask($project, $contract, $contractor, $supervisorUser, TaskStatus::SupervisorApproved->value);

    $service->recordFinalApproval($task, $employerUser, ApprovalStatus::NeedsRework->value, 'Not acceptable');

    $task->refresh();
    expect($task->status)->toBe(TaskStatus::NeedsRework->value);
});

test('contractor cannot perform technical approval', function () {
    $entities = createTierTestEntities();
    extract($entities);

    $auditMock = mock(AuditServiceInterface::class);
    app()->instance(AuditServiceInterface::class, $auditMock);

    $service = app(ApprovalService::class);
    $task = createTierTask($project, $contract, $contractor, $supervisorUser);

    expect(fn () => $service->recordTechnicalApproval($task, $contractorUser, ApprovalStatus::Approved->value))
        ->toThrow(InvalidApprovalException::class);
});

test('contractor cannot perform final approval', function () {
    $entities = createTierTestEntities();
    extract($entities);

    $auditMock = mock(AuditServiceInterface::class);
    app()->instance(AuditServiceInterface::class, $auditMock);

    $service = app(ApprovalService::class);
    $task = createTierTask($project, $contract, $contractor, $supervisorUser, TaskStatus::SupervisorApproved->value);

    expect(fn () => $service->recordFinalApproval($task, $contractorUser, ApprovalStatus::Approved->value))
        ->toThrow(InvalidApprovalException::class);
});
