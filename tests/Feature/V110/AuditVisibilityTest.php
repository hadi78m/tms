<?php

namespace Tests\Feature\V110;

use App\Domain\DTOs\CreateTaskData;
use App\Domain\Enums\TaskPriority;
use App\Domain\Enums\TaskType;
use App\Domain\Services\StageProgressApprovalService;
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
 * V1.10 — Phase 4: Audit Visibility UI (DEC-045).
 *
 *   DR-4 = C: supervisor + employer may view the audit log in the UI.
 *   Masking = بله: sensitive fields are never rendered.
 *   Read-only: no write/edit/delete path exists for audit records.
 */
class AuditVisibilityTest extends TestCase
{
    use RefreshDatabase, V19Fixtures;

    private Project $project;

    private User $supervisor;

    private User $employer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makeProject(1);
        $this->supervisor = $this->makeUserWithRole('supervisor');
        $this->employer = $this->makeUserWithRole('employer');

        // Produce real audit rows through the approval service.
        $module = $this->makeSingleModule($this->project, $this->supervisor);
        $stage = $module->stages()->firstWhere('stage_code', 'analysis');
        $service = app(StageProgressApprovalService::class);
        $approval = $service->propose($stage, $this->supervisor, 5);
        $service->approve($approval, $this->supervisor, 5);
    }

    public function test_supervisor_can_view_audit_index(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('stage_progress_proposed', false)
            ->assertSee('stage_progress_approved', false);
    }

    public function test_employer_can_view_audit_index(): void
    {
        $this->actingAs($this->employer)
            ->get(route('audit.index'))
            ->assertOk();
    }

    public function test_other_roles_cannot_view_audit(): void
    {
        foreach (['project_manager', 'admin', 'management', 'contractor', 'viewer'] as $role) {
            $user = $this->makeUserWithRole($role);

            $this->actingAs($user)
                ->get(route('audit.index'))
                ->assertForbidden();

            $this->post(route('logout'));
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('audit.index'))->assertRedirect(route('login'));
    }

    public function test_masking_and_filters_are_present(): void
    {
        $this->actingAs($this->supervisor)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('ماسک', false)
            ->assertSee('immutable', false);

        // Filter by action narrows the rows but stays 200.
        $this->actingAs($this->supervisor)
            ->get(route('audit.index', ['action' => 'stage_progress_approved']))
            ->assertOk();
    }

    public function test_task_scoped_audit_page_works(): void
    {
        // Any activity row's entity works; use the stage approval entity via task route? No —
        // the task-scoped page filters by the Task morph class. Create a task audit row.
        $task = Task::create([
            'project_id' => $this->project->id,
            'contract_id' => $this->project->contract_id,
            'contractor_id' => $this->project->contractor_id,
            'title' => 'audited task',
            'priority' => 'normal',
            'weight' => 5,
            'status' => 'draft',
            'created_by' => $this->supervisor->id,
        ]);

        app(TaskService::class)->create(
            new CreateTaskData(
                project_id: $this->project->id,
                wbs_phase_id: null,
                title: 'audited task 2',
                description: null,
                priority: TaskPriority::Normal,
                task_type: TaskType::Development,
                weight: 5.0,
                planned_start_date: null,
                planned_due_date: null,
                parent_task_id: null
            ),
            $this->supervisor
        );

        $this->actingAs($this->supervisor)
            ->get(route('audit.task', $task->id))
            ->assertOk();
    }

    public function test_no_write_path_exists_for_audit(): void
    {
        // Structural guard: the audit routes are GET-only and immutable model
        // hooks forbid updates/deletes. Attempting an update via the model
        // must not change the row.
        $log = ActivityLog::firstOrFail();
        $original = $log->action;

        $log->action = 'tampered';
        $log->save();
        $log->refresh();

        $this->assertSame($original, $log->action);
    }
}
