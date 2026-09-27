<?php

namespace Tests\Feature\V110;

use App\Domain\DTOs\CreateTaskData;
use App\Domain\Enums\TaskPriority;
use App\Domain\Enums\TaskType;
use App\Domain\Exceptions\UnauthorizedTaskOperationException;
use App\Domain\Services\TaskService;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\V19\Concerns\V19Fixtures;
use Tests\TestCase;

use function app;
use function route;

/**
 * V1.10 — Phase 1 + 2: Task ↔ Stage assignment (DEC-042/043) and the
 * Phase 3 HTTP-layer role enforcement for F-2/F-3 (DEC-044).
 *
 *   DEC-042 (DR-1=A): only a supervisor may set module_stage_id.
 *   DEC-043 (DR-2=A): the stage may change until the task's Final Approval
 *                     (terminal states approved/cancelled lock it).
 *   DEC-044 (DR-3=C): supervisor role enforced at route level AND as a
 *                     service guard; F-2 (tasks.create/store) and F-3
 *                     (tasks.assign) now carry role middleware.
 */
class TaskStageAssignmentTest extends TestCase
{
    use RefreshDatabase, V19Fixtures;

    private Project $project;

    private User $supervisor;

    private User $manager;

    private User $employer;

    private User $contractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makeProject(1);
        $this->supervisor = $this->makeUserWithRole('supervisor');
        $this->manager = $this->makeUserWithRole('project_manager');
        $this->employer = $this->makeUserWithRole('employer');
        $this->contractor = $this->makeUserWithRole('contractor');
    }

    private function service(): TaskService
    {
        return app(TaskService::class);
    }

    private function stageIds(): array
    {
        $module = $this->makeSingleModule($this->project, $this->supervisor);

        return [$module, $module->stages()->orderBy('sort_order')->get()->pluck('id')->all()];
    }

    private function createPayload(array $overrides = []): array
    {
        return array_merge([
            'project_id' => $this->project->id,
            'title' => 'task from http',
            'priority' => 'normal',
            'task_type' => 'development',
            'weight' => 10,
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // DEC-042 — create with stage (supervisor only)
    // ------------------------------------------------------------------

    public function test_supervisor_can_create_task_with_stage(): void
    {
        [$module, $stages] = $this->stageIds();

        $response = $this->actingAs($this->supervisor)
            ->post(route('tasks.store'), $this->createPayload(['module_stage_id' => $stages[0]]))
            ->assertRedirect(route('tasks.index'));

        $task = Task::where('title', 'task from http')->firstOrFail();
        $this->assertSame($stages[0], (int) $task->module_stage_id);
        $this->assertSame($module->id, (int) $task->module_id); // derived, DEC-021

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'task_stage_assigned',
            'entity_id' => $task->id,
        ]);
    }

    public function test_supervisor_can_create_task_without_stage(): void
    {
        $this->actingAs($this->supervisor)
            ->post(route('tasks.store'), $this->createPayload())
            ->assertRedirect(route('tasks.index'));

        $task = Task::where('title', 'task from http')->firstOrFail();
        $this->assertNull($task->module_stage_id);
        $this->assertNull($task->module_id);
    }

    public function test_stage_from_another_project_is_rejected_on_create(): void
    {
        $other = $this->makeProject(2);
        $otherModule = $this->makeSingleModule($other, $this->supervisor);
        $otherStage = $otherModule->stages()->first();

        $this->actingAs($this->supervisor)
            ->post(route('tasks.store'), $this->createPayload(['module_stage_id' => $otherStage->id]))
            ->assertSessionHasErrors('module_stage_id');

        $this->assertDatabaseMissing('tasks', ['title' => 'task from http']);
    }

    public function test_stage_endpoint_rejects_unknown_stage(): void
    {
        [$module, $stages] = $this->stageIds();
        $task = $this->service()->create($this->dto(), $this->supervisor);

        $this->actingAs($this->supervisor)
            ->post(route('tasks.stage', $task->id), ['module_stage_id' => 999999])
            ->assertSessionHasErrors('module_stage_id');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'module_stage_id' => null]);
    }

    // ------------------------------------------------------------------
    // DEC-042 — authorization matrix (service guard)
    // ------------------------------------------------------------------

    public function test_only_supervisor_can_assign_stage_via_service(): void
    {
        [$module, $stages] = $this->stageIds();
        $task = $this->service()->create($this->dto(), $this->manager);

        foreach ([$this->manager, $this->employer, $this->contractor] as $actor) {
            try {
                $this->service()->assignStage($task, $stages[0], $actor);
                $this->fail("Expected UnauthorizedTaskOperationException for role {$actor->getRoleNames()->first()}");
            } catch (UnauthorizedTaskOperationException $e) {
                $this->assertDatabaseHas('tasks', ['id' => $task->id, 'module_stage_id' => null]);
            }
        }
    }

    public function test_supervisor_can_assign_stage_via_service(): void
    {
        [$module, $stages] = $this->stageIds();
        $task = $this->service()->create($this->dto(), $this->supervisor);

        $this->service()->assignStage($task, $stages[0], $this->supervisor);

        $task->refresh();
        $this->assertSame($stages[0], (int) $task->module_stage_id);
        $this->assertSame($module->id, (int) $task->module_id);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'task_stage_assigned',
            'entity_id' => $task->id,
        ]);
    }

    // ------------------------------------------------------------------
    // DEC-042 — HTTP reassignment endpoint (role:supervisor)
    // ------------------------------------------------------------------

    public function test_stage_endpoint_forbidden_for_non_supervisor_roles(): void
    {
        [$module, $stages] = $this->stageIds();
        $task = $this->service()->create($this->dto(), $this->supervisor);

        foreach ([$this->manager, $this->employer, $this->contractor] as $actor) {
            $this->actingAs($actor)
                ->post(route('tasks.stage', $task->id), ['module_stage_id' => $stages[0]])
                ->assertForbidden();
        }

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'module_stage_id' => null]);
    }

    public function test_stage_endpoint_allows_supervisor_to_set_and_change(): void
    {
        [$module, $stages] = $this->stageIds();
        $task = $this->service()->create($this->dto(), $this->supervisor);

        $this->actingAs($this->supervisor)
            ->post(route('tasks.stage', $task->id), ['module_stage_id' => $stages[0]])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'module_stage_id' => $stages[0]]);

        // change to another stage
        $this->actingAs($this->supervisor)
            ->post(route('tasks.stage', $task->id), ['module_stage_id' => $stages[1]])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'module_stage_id' => $stages[1]]);
    }

    public function test_stage_endpoint_rejects_cross_project_stage(): void
    {
        [$module, $stages] = $this->stageIds();
        $task = $this->service()->create($this->dto(), $this->supervisor);

        $other = $this->makeProject(2);
        $otherModule = $this->makeSingleModule($other, $this->supervisor);
        $otherStage = $otherModule->stages()->first();

        $this->actingAs($this->supervisor)
            ->post(route('tasks.stage', $task->id), ['module_stage_id' => $otherStage->id])
            ->assertSessionHasErrors('module_stage_id');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'module_stage_id' => null]);
    }

    // ------------------------------------------------------------------
    // DEC-043 — stage change timing (until Final Approval)
    // ------------------------------------------------------------------

    public function test_stage_change_allowed_before_final_approval(): void
    {
        [$module, $stages] = $this->stageIds();

        foreach (['draft', 'assigned', 'in_progress', 'submitted_for_review', 'under_review', 'supervisor_approved', 'needs_rework'] as $status) {
            $task = $this->service()->create($this->dto(), $this->supervisor);
            $task->status = $status;
            $task->save();

            $this->service()->assignStage($task, $stages[0], $this->supervisor);
            $this->assertSame($stages[0], (int) $task->fresh()->module_stage_id, "status $status should allow stage change");
        }
    }

    public function test_stage_change_forbidden_after_final_approval(): void
    {
        [$module, $stages] = $this->stageIds();
        $task = $this->service()->create($this->dto(), $this->supervisor);
        $task->status = 'approved';
        $task->save();

        try {
            $this->service()->assignStage($task, $stages[0], $this->supervisor);
            $this->fail('Expected stage to be locked after Final Approval (DEC-043).');
        } catch (UnauthorizedTaskOperationException $e) {
            $this->assertDatabaseHas('tasks', ['id' => $task->id, 'module_stage_id' => null]);
        }
    }

    public function test_stage_change_forbidden_after_cancel(): void
    {
        [$module, $stages] = $this->stageIds();
        $task = $this->service()->create($this->dto(), $this->supervisor);
        $task->status = 'cancelled';
        $task->save();

        try {
            $this->service()->assignStage($task, $stages[0], $this->supervisor);
            $this->fail('Expected stage to be locked after cancellation (DEC-043).');
        } catch (UnauthorizedTaskOperationException $e) {
            $this->assertDatabaseHas('tasks', ['id' => $task->id, 'module_stage_id' => null]);
        }
    }

    public function test_stage_locked_rejection_is_surfaced_over_http(): void
    {
        [$module, $stages] = $this->stageIds();
        $task = $this->service()->create($this->dto(), $this->supervisor);
        $task->status = 'approved';
        $task->save();

        $this->actingAs($this->supervisor)
            ->post(route('tasks.stage', $task->id), ['module_stage_id' => $stages[0]])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'module_stage_id' => null]);
    }

    // ------------------------------------------------------------------
    // DEC-040 regression — stage-less tasks remain valid and counted
    // ------------------------------------------------------------------

    public function test_stageless_task_remains_valid_and_report_counts_it(): void
    {
        $this->makeSingleModule($this->project, $this->supervisor);

        $task = $this->service()->create($this->dto(), $this->supervisor);

        $this->assertNull($task->module_stage_id);

        $this->actingAs($this->manager)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('1 وظیفه بدون مرحله', false);
    }

    // ------------------------------------------------------------------
    // DEC-044 — F-2: tasks.create/store role enforcement
    // ------------------------------------------------------------------

    public function test_create_form_and_store_forbidden_for_contractor(): void
    {
        $this->actingAs($this->contractor)
            ->get(route('tasks.create'))
            ->assertForbidden();

        $this->actingAs($this->contractor)
            ->post(route('tasks.store'), $this->createPayload())
            ->assertForbidden();

        $this->assertDatabaseMissing('tasks', ['title' => 'task from http']);
    }

    public function test_create_and_store_allowed_for_permitted_roles(): void
    {
        foreach ([$this->manager, $this->supervisor, $this->employer] as $actor) {
            $this->actingAs($actor)
                ->get(route('tasks.create'))
                ->assertOk();

            $this->actingAs($actor)
                ->post(route('tasks.store'), $this->createPayload(['title' => 'created by '.$actor->id]))
                ->assertRedirect(route('tasks.index'));

            $this->assertDatabaseHas('tasks', ['title' => 'created by '.$actor->id]);
        }
    }

    // ------------------------------------------------------------------
    // DEC-044 — F-3: tasks.assign role enforcement
    // ------------------------------------------------------------------

    public function test_assign_forbidden_for_contractor_role(): void
    {
        $task = Task::create([
            'project_id' => $this->project->id,
            'contract_id' => $this->project->contract_id,
            'contractor_id' => $this->project->contractor_id,
            'title' => 'assign target',
            'priority' => 'normal',
            'weight' => 5,
            'status' => 'draft',
            'created_by' => $this->manager->id,
        ]);

        $worker = User::factory()->create(['contractor_id' => $this->project->contractor_id]);

        $this->actingAs($this->contractor)
            ->post(route('tasks.assign', $task->id), ['user_id' => $worker->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('task_assignments', ['task_id' => $task->id]);
    }

    public function test_assign_allowed_for_permitted_roles(): void
    {
        $task = Task::create([
            'project_id' => $this->project->id,
            'contract_id' => $this->project->contract_id,
            'contractor_id' => $this->project->contractor_id,
            'title' => 'assign target 2',
            'priority' => 'normal',
            'weight' => 5,
            'status' => 'draft',
            'created_by' => $this->manager->id,
        ]);

        $worker = User::factory()->create(['contractor_id' => $this->project->contractor_id]);

        foreach ([$this->manager, $this->employer] as $actor) {
            $this->actingAs($actor)
                ->post(route('tasks.assign', $task->id), ['user_id' => $worker->id])
                ->assertRedirect(route('tasks.show', $task->id));
        }
    }

    // ------------------------------------------------------------------
    // Audit content: old/new stage ids are recorded (DEC-043)
    // ------------------------------------------------------------------

    public function test_stage_change_audit_records_old_and_new_stage(): void
    {
        [$module, $stages] = $this->stageIds();
        $task = $this->service()->create($this->dto(), $this->supervisor);

        $this->service()->assignStage($task, $stages[0], $this->supervisor);
        $this->service()->assignStage($task->refresh(), $stages[1], $this->supervisor);

        $audit = ActivityLog::where('action', 'task_stage_assigned')
            ->where('entity_id', $task->id)
            ->orderBy('id')
            ->get();

        $this->assertSame(2, $audit->count());

        $first = $audit->first();
        $this->assertNull($first->old_values['old_stage_id'] ?? null);
        $this->assertSame($stages[0], (int) $first->new_values['new_stage_id']);

        $second = $audit->last();
        $this->assertSame($stages[0], (int) $second->old_values['old_stage_id']);
        $this->assertSame($stages[1], (int) $second->new_values['new_stage_id']);
    }

    private function dto(?int $stageId = null): CreateTaskData
    {
        return new CreateTaskData(
            project_id: $this->project->id,
            wbs_phase_id: null,
            title: 'service task',
            description: null,
            priority: TaskPriority::Normal,
            task_type: TaskType::Development,
            weight: 5.0,
            planned_start_date: null,
            planned_due_date: null,
            parent_task_id: null,
            module_stage_id: $stageId
        );
    }
}
