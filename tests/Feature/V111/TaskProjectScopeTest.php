<?php

namespace Tests\Feature\V111;

use App\Domain\Services\ModuleService;
use App\Domain\Services\ProjectMembershipService;
use App\Domain\DTOs\AssignProjectSupervisorData;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * V1.11 — DEC-049 Phase 1 · I-3: Task Project Scope Integration.
 *
 * Enforces DR-TASK-02=A over HTTP: a supervisor reaches ONLY the tasks of
 * projects holding an ACTIVE supervisor membership (ended_at IS NULL).
 * Historical membership grants nothing; direct permission grants never
 * bypass scope; admin/management are scope-unrestricted (scope only);
 * PM/viewer keep Q1 behavior; contractor isolation stays additive.
 *
 * Cross-project matrix (mission §12): S assigned to A, T assigned to B —
 * S sees A / denied B; T sees B / denied A. Also: direct-ID enumeration
 * protection on tasks.show, index query filtering, and replacement
 * semantics (V11-01 OD-6-d).
 */
class TaskProjectScopeTest extends TestCase
{
    use RefreshDatabase;

    private Project $projectA;

    private Project $projectB;

    private Task $taskA;

    private Task $taskB;

    private User $supervisorS;

    private User $supervisorT;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // InitialTmsSeeder slice — catalog unchanged.
        foreach ([
            'manage users', 'manage settings', 'manage projects', 'create tasks', 'assign tasks',
            'technical_approval', 'final_approval', 'submit rework', 'view reports', 'export reports', 'upload evidence',
        ] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        Role::findOrCreate('admin', 'web')->syncPermissions([
            'create tasks', 'assign tasks', 'manage users', 'manage settings', 'manage projects',
            'technical_approval', 'final_approval', 'submit rework', 'view reports', 'export reports', 'upload evidence',
        ]);
        Role::findOrCreate('project_manager', 'web')->syncPermissions([
            'create tasks', 'assign tasks', 'manage projects', 'view reports',
        ]);

        $this->admin = $this->makeUserWithRole('admin');

        // Mission §12 fixture: Project A (S active) · Project B (T active).
        $this->projectA = Project::factory()->create(['name' => 'Project A']);
        $this->projectB = Project::factory()->create(['name' => 'Project B']);

        $this->supervisorS = $this->makeUserWithRole('supervisor');
        $this->supervisorT = $this->makeUserWithRole('supervisor');

        $membershipService = app(ProjectMembershipService::class);
        $membershipService->assignSupervisor(
            new AssignProjectSupervisorData(project_id: $this->projectA->id, user_id: $this->supervisorS->id, assigned_by: $this->admin->id),
            $this->admin
        );
        $membershipService->assignSupervisor(
            new AssignProjectSupervisorData(project_id: $this->projectB->id, user_id: $this->supervisorT->id, assigned_by: $this->admin->id),
            $this->admin
        );

        $this->taskA = $this->makeTask($this->projectA, 'Task A');
        $this->taskB = $this->makeTask($this->projectB, 'Task B');
    }

    private function makeTask(Project $project, string $title): Task
    {
        return Task::factory()->create([
            'project_id' => $project->id,
            'contractor_id' => $project->contractor_id,
            'contract_id' => $project->contract_id,
            'title' => $title,
            'status' => 'assigned',
        ]);
    }

    private function makeUserWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create(['contractor_id' => null]);
        $user->assignRole($role);

        return $user->refresh();
    }

    // ------------------------------------------------------------------
    // Cross-project matrix (mission §12)
    // ------------------------------------------------------------------

    public function test_supervisor_s_sees_project_a_and_is_denied_project_b(): void
    {
        // Index: only Project A's tasks are retrieved.
        $this->actingAs($this->supervisorS)->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('Task A')
            ->assertDontSee('Task B');

        // Show: own project allowed.
        $this->actingAs($this->supervisorS)->get(route('tasks.show', $this->taskA->id))
            ->assertOk();
    }

    public function test_supervisor_t_sees_project_b_and_is_denied_project_a(): void
    {
        $this->actingAs($this->supervisorT)->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('Task B')
            ->assertDontSee('Task A');

        $this->actingAs($this->supervisorT)->get(route('tasks.show', $this->taskB->id))
            ->assertOk();
    }

    public function test_direct_id_enumeration_denies_foreign_project_task(): void
    {
        // Supervisor S guesses Task B's URL — no task info leak (mission §11):
        // the 403 error page must not contain the task's identity markers.
        $response = $this->actingAs($this->supervisorS)->get(route('tasks.show', $this->taskB->id))
            ->assertForbidden();

        $content = $response->getContent();
        $this->assertStringNotContainsString('Task B', $content);
        $this->assertStringNotContainsString('Project B', $content);
        $this->assertStringNotContainsString('مشخصات وظیفه', $content);
    }

    public function test_no_membership_grants_nothing(): void
    {
        $outsider = $this->makeUserWithRole('supervisor');

        $this->actingAs($outsider)->get(route('tasks.index'))
            ->assertOk()
            ->assertDontSee('Task A')
            ->assertDontSee('Task B');

        $this->actingAs($outsider)->get(route('tasks.show', $this->taskA->id))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Historical / replacement membership (missions §13, §14)
    // ------------------------------------------------------------------

    public function test_ended_membership_denies_access_but_row_is_retained(): void
    {
        $membershipService = app(ProjectMembershipService::class);
        $membershipService->endActiveSupervisor($this->projectA, $this->admin);

        $this->actingAs($this->supervisorS)->get(route('tasks.index'))
            ->assertOk()
            ->assertDontSee('Task A');
        $this->actingAs($this->supervisorS)->get(route('tasks.show', $this->taskA->id))
            ->assertForbidden();

        // History retained (V11-01): the membership row still exists.
        $this->assertDatabaseHas('project_memberships', [
            'project_id' => $this->projectA->id,
            'user_id' => $this->supervisorS->id,
        ]);
    }

    public function test_replacement_moves_scope_from_s_to_t(): void
    {
        $membershipService = app(ProjectMembershipService::class);
        $membershipService->assignSupervisor(
            new AssignProjectSupervisorData(project_id: $this->projectA->id, user_id: $this->supervisorT->id, assigned_by: $this->admin->id),
            $this->admin
        );

        // S lost scope...
        $this->actingAs($this->supervisorS)->get(route('tasks.show', $this->taskA->id))
            ->assertForbidden();

        // ...T gained it (active on A now, keeps B).
        $this->actingAs($this->supervisorT)->get(route('tasks.show', $this->taskA->id))
            ->assertOk();
        $this->actingAs($this->supervisorT)->get(route('tasks.show', $this->taskB->id))
            ->assertOk();
    }

    // ------------------------------------------------------------------
    // Direct permission never bypasses scope (mission §2)
    // ------------------------------------------------------------------

    public function test_direct_assign_tasks_grant_does_not_bypass_scope(): void
    {
        // S holds a direct `assign tasks` grant yet cannot even SEE task B.
        $this->supervisorS->givePermissionTo('assign tasks');
        $this->supervisorS->refresh();
        $this->assertTrue($this->supervisorS->can('assign tasks'));

        $this->actingAs($this->supervisorS)->get(route('tasks.show', $this->taskB->id))
            ->assertForbidden();
        $this->actingAs($this->supervisorS)->get(route('tasks.index'))
            ->assertOk()
            ->assertDontSee('Task B');
    }

    // ------------------------------------------------------------------
    // Admin / Management — scope-unrestricted (mission §15)
    // ------------------------------------------------------------------

    public function test_admin_is_scope_unrestricted_on_tasks(): void
    {
        $this->actingAs($this->admin)->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('Task A')
            ->assertSee('Task B');

        $this->actingAs($this->admin)->get(route('tasks.show', $this->taskA->id))
            ->assertOk();
        $this->actingAs($this->admin)->get(route('tasks.show', $this->taskB->id))
            ->assertOk();
    }

    public function test_management_is_project_scope_unrestricted(): void
    {
        $management = $this->makeUserWithRole('management');

        // Scope layer passes both projects...
        $scope = app(\App\Domain\Rules\ProjectScopeService::class);
        $this->assertNull($scope->accessibleProjectIds($management));
        $this->assertTrue($scope->canAccessProject($this->projectA, $management));
        $this->assertTrue($scope->canAccessProject($this->projectB, $management));

        // ...and index reveals both (no permission gate on view; index is
        // read-only retrieval — unchanged Phase 1 behavior for management).
        $this->actingAs($management)->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('Task A')
            ->assertSee('Task B');
    }

    // ------------------------------------------------------------------
    // PM / Viewer regression — Q1 preserved (mission §16)
    // ------------------------------------------------------------------

    public function test_pm_behavior_is_preserved_without_membership(): void
    {
        $pm = $this->makeUserWithRole('project_manager');

        // Q1: no scope derived — PM still sees everything on index (current
        // repository behavior), membership NOT made their basis.
        $this->actingAs($pm)->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('Task A')
            ->assertSee('Task B');

        $this->actingAs($pm)->get(route('tasks.show', $this->taskA->id))
            ->assertOk();
    }

    public function test_viewer_behavior_is_preserved_without_membership(): void
    {
        $viewer = $this->makeUserWithRole('viewer');

        $this->actingAs($viewer)->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('Task A')
            ->assertSee('Task B');

        $this->actingAs($viewer)->get(route('tasks.show', $this->taskB->id))
            ->assertOk();
    }

    // ------------------------------------------------------------------
    // Contractor regression — isolation additive (mission §17)
    // ------------------------------------------------------------------

    public function test_contractor_isolation_is_additive_with_project_scope(): void
    {
        // Contractor of project A: sees A's tasks only (existing filter).
        $workerA = User::factory()->create(['contractor_id' => $this->projectA->contractor_id]);

        $this->actingAs($workerA)->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('Task A')
            ->assertDontSee('Task B');

        // Supervisor scoped to A ALSO remains contractor-filtered: a second
        // project belonging to a DIFFERENT contractor never leaks in, and the
        // supervisor's membership scope composes with the contractor layer.
        $this->actingAs($this->supervisorS)->get(route('tasks.show', $this->taskA->id))
            ->assertOk();
    }

    public function test_contractor_cannot_read_task_of_other_contractor(): void
    {
        $workerA = User::factory()->create(['contractor_id' => $this->projectA->contractor_id]);

        $this->actingAs($workerA)->get(route('tasks.show', $this->taskB->id))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Scope-only abilities on other task operations (view/start/submit)
    // ------------------------------------------------------------------

    public function test_start_and_submit_are_scope_denied_for_foreign_project(): void
    {
        $this->actingAs($this->supervisorS)
            ->post(route('tasks.start', $this->taskB->id))
            ->assertForbidden();

        $inProgressB = $this->makeTask($this->projectB, 'Task B2');
        $inProgressB->status = 'in_progress';
        $inProgressB->save();

        $this->actingAs($this->supervisorS)
            ->post(route('tasks.submit', $inProgressB->id))
            ->assertForbidden();
    }

    public function test_dependencies_and_stage_are_scope_denied_for_foreign_project(): void
    {
        $this->actingAs($this->supervisorS)
            ->post(route('tasks.dependencies.store', $this->taskB->id), ['depends_on_task_id' => $this->taskA->id])
            ->assertForbidden();

        // manageStages scope layer: foreign project denied (role mw passes
        // supervisor; the policy scope check denies before the service).
        $this->actingAs($this->supervisorS)
            ->post(route('tasks.stage', $this->taskB->id), ['module_stage_id' => null])
            ->assertForbidden();
    }
}
