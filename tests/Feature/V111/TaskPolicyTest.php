<?php

namespace Tests\Feature\V111;

use App\Domain\Rules\TaskScopeService;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\Feature\V19\Concerns\V19Fixtures;
use Tests\TestCase;

/**
 * V1.11 — DEC-049 Phase 1 · I-1: Task Policy Infrastructure.
 *
 * Covers: discoverability, ability surface (exactly the six real abilities —
 * no update/destroy), permission gating (create/assign = catalog permission),
 * Project Scope composition via ProjectScopeService (DR-TASK-02=A: scope
 * restricts supervisors only; PM/Viewer preserve current behavior; admin/
 * management are scope-unrestricted but NOT permission-unrestricted), and
 * actor-side contractor isolation delegated to the canonical TaskScopeService.
 *
 * Business invariants are NOT tested here — the Policy must never replace a
 * Service Guard (state machine, final-approval lock, supervisor-only stage,
 * assignee contractor scope). A dedicated test below proves the separation:
 * a policy-allowed task start still fails in the service when the state
 * machine forbids it.
 *
 * I-1 scope: NO controller wiring, NO middleware change, NO query scope —
 * the policy object itself is exercised directly (infrastructure gate).
 */
class TaskPolicyTest extends TestCase
{
    use RefreshDatabase, V19Fixtures;

    private Project $project;

    private Task $task;

    private User $admin;

    private User $projectManager;

    private User $supervisor;

    private User $management;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUserWithRole('admin');
        $this->project = $this->makeProject(1);
        $this->task = $this->makeTask($this->project);
        $this->projectManager = $this->makeUserWithRole('project_manager');
        $this->supervisor = $this->makeUserWithRole('supervisor');
        $this->management = $this->makeUserWithRole('management');

        $this->seedTaskPermissionMatrix();

        // Role sync revokes/reattaches relations — refresh the actor instances.
        $this->admin->refresh();
        $this->projectManager->refresh();
        $this->supervisor->refresh();
        $this->management->refresh();
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    private function makeTask(Project $project): Task
    {
        return Task::create([
            'project_id' => $project->id,
            'contract_id' => $project->contract_id,
            'contractor_id' => $project->contractor_id,
            'title' => 'policy test task',
            'weight' => 10,
            'priority' => 'normal',
            'status' => 'assigned',
            'created_by' => $this->admin->id,
        ]);
    }

    private function policy(): \App\Policies\TaskPolicy
    {
        return app(\App\Policies\TaskPolicy::class);
    }

    private function givePermission(User $user, string $permission): void
    {
        \Spatie\Permission\Models\Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
        $user->refresh();
    }

    /**
     * Replicates the InitialTmsSeeder role→permission matrix for the task
     * permissions ONLY (tests never run the seeder — repo convention:
     * Permission::firstOrCreate + explicit assignment). No seeder change.
     */
    private function seedTaskPermissionMatrix(): void
    {
        foreach ([
            'manage users', 'manage settings', 'manage projects', 'create tasks', 'assign tasks',
            'technical_approval', 'final_approval', 'submit rework', 'view reports', 'export reports', 'upload evidence',
        ] as $name) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // InitialTmsSeeder: admin = all 11 · PM = manage projects, create tasks, assign tasks, view reports.
        Role::findOrCreate('admin', 'web')->syncPermissions([
            'create tasks', 'assign tasks', 'manage users', 'manage settings', 'manage projects',
            'technical_approval', 'final_approval', 'submit rework', 'view reports', 'export reports', 'upload evidence',
        ]);
        Role::findOrCreate('project_manager', 'web')->syncPermissions([
            'create tasks', 'assign tasks', 'manage projects', 'view reports',
        ]);
    }

    // ------------------------------------------------------------------
    // Infrastructure: discovery + ability surface
    // ------------------------------------------------------------------

    public function test_task_policy_is_discoverable_and_resolves_from_container(): void
    {
        $this->assertInstanceOf(\App\Policies\TaskPolicy::class, $this->policy());

        // Gate knows the model's policy (auto-discovery, DEC-050 convention).
        $this->assertSame(\App\Policies\TaskPolicy::class, Gate::getPolicyFor($this->task)::class);
    }

    public function test_policy_has_exactly_the_six_real_abilities(): void
    {
        $methods = collect((new \ReflectionClass(\App\Policies\TaskPolicy::class))
            ->getMethods(\ReflectionMethod::IS_PUBLIC))
            ->filter(fn ($m) => ! str_starts_with($m->getName(), '__'))
            ->map(fn ($m) => $m->getName())
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'assign', 'create', 'manageStages', 'startProgress', 'submitForReview', 'view',
        ], $methods);
    }

    public function test_policy_has_no_update_or_destroy_ability(): void
    {
        $class = new \ReflectionClass(\App\Policies\TaskPolicy::class);

        $this->assertFalse($class->hasMethod('update'));
        $this->assertFalse($class->hasMethod('destroy'));
    }

    // ------------------------------------------------------------------
    // view — scope + contractor; NO permission gate (none exists in catalog)
    // ------------------------------------------------------------------

    public function test_view_allows_unrestricted_roles_without_permission(): void
    {
        // Admin & management: unrestricted PROJECT scope (scope only).
        $this->assertTrue($this->policy()->view($this->admin, $this->task));
        $this->assertTrue($this->policy()->view($this->management, $this->task));

        // PM/Viewer: Q1 preserve — no scope derived, no permission needed.
        $viewer = $this->makeUserWithRole('viewer');
        $this->assertTrue($this->policy()->view($this->projectManager, $this->task));
        $this->assertTrue($this->policy()->view($viewer, $this->task));
    }

    public function test_view_denies_supervisor_without_active_membership(): void
    {
        $this->assertFalse($this->policy()->view($this->supervisor, $this->task));
    }

    public function test_view_allows_supervisor_only_within_active_membership(): void
    {
        app(\App\Domain\Services\ProjectMembershipService::class)->assignSupervisor(
            new \App\Domain\DTOs\AssignProjectSupervisorData(
                project_id: $this->project->id,
                user_id: $this->supervisor->id,
                assigned_by: $this->admin->id
            ),
            $this->admin
        );

        $this->assertTrue($this->policy()->view($this->supervisor, $this->task));
    }

    public function test_view_denies_supervisor_after_membership_ends(): void
    {
        $service = app(\App\Domain\Services\ProjectMembershipService::class);
        $service->assignSupervisor(
            new \App\Domain\DTOs\AssignProjectSupervisorData(
                project_id: $this->project->id,
                user_id: $this->supervisor->id,
                assigned_by: $this->admin->id
            ),
            $this->admin
        );
        $service->endActiveSupervisor($this->project, $this->admin);

        $this->assertFalse($this->policy()->view($this->supervisor, $this->task));
    }

    // ------------------------------------------------------------------
    // create — permission `create tasks` AND scope AND actor contractor
    // ------------------------------------------------------------------

    public function test_create_requires_create_tasks_permission(): void
    {
        // Admin & PM hold `create tasks` via role (InitialTmsSeeder matrix).
        $this->assertTrue($this->policy()->create($this->admin, $this->project));
        $this->assertTrue($this->policy()->create($this->projectManager, $this->project));

        // Supervisor is scope-restricted anyway, but the permission gate is
        // what DENIES management here (scope-unrestricted, no permission).
        $this->assertFalse($this->policy()->create($this->management, $this->project));
        $this->assertFalse($this->policy()->create($this->supervisor, $this->project));

        $employer = $this->makeUserWithRole('employer');
        $this->assertFalse($this->policy()->create($employer, $this->project));
    }

    public function test_create_direct_permission_grant_passes_permission_but_not_scope(): void
    {
        // DEC-050: direct grant-only. A supervisor holding a direct
        // `create tasks` grant passes the PERMISSION gate...
        $this->givePermission($this->supervisor, 'create tasks');
        $this->assertTrue($this->supervisor->can('create tasks'));

        // ...but is still DENIED without an active project membership —
        // permission can never bypass Project Scope.
        $this->assertFalse($this->policy()->create($this->supervisor, $this->project));

        // With an active membership the same direct grant goes through.
        app(\App\Domain\Services\ProjectMembershipService::class)->assignSupervisor(
            new \App\Domain\DTOs\AssignProjectSupervisorData(
                project_id: $this->project->id,
                user_id: $this->supervisor->id,
                assigned_by: $this->admin->id
            ),
            $this->admin
        );
        $this->assertTrue($this->policy()->create($this->supervisor, $this->project));
    }

    public function test_create_denies_contractor_actor_for_foreign_contractor_project(): void
    {
        Role::findOrCreate('contractor', 'web');
        $contractorUser = User::factory()->create(['contractor_id' => $this->project->contractor_id]);
        $contractorUser->assignRole('contractor');

        // Own contractor's project: permission gate decides (contractor role
        // holds no `create tasks` → deny — the immutable rule lives upstream).
        $this->assertFalse($this->policy()->create($contractorUser, $this->project));

        // Even WITH a direct grant, a foreign contractor's project is denied
        // by actor-side contractor isolation in the policy.
        $other = $this->makeProject(2);
        $this->givePermission($contractorUser, 'create tasks');
        $this->assertFalse($this->policy()->create($contractorUser, $other));
    }

    // ------------------------------------------------------------------
    // assign — permission `assign tasks` AND scope
    // ------------------------------------------------------------------

    public function test_assign_requires_assign_tasks_permission(): void
    {
        $this->assertTrue($this->policy()->assign($this->admin, $this->task));
        $this->assertTrue($this->policy()->assign($this->projectManager, $this->task));

        // Supervisor/employer hold no `assign tasks` (DEC-054 Align-down).
        $this->assertFalse($this->policy()->assign($this->supervisor, $this->task));
        $this->assertFalse($this->policy()->assign($this->makeUserWithRole('employer'), $this->task));
        $this->assertFalse($this->policy()->assign($this->management, $this->task));
    }

    public function test_assign_permission_cannot_bypass_project_scope(): void
    {
        $pm2 = $this->makeUserWithRole('project_manager');
        // PM holds the permission; scope preserves current behavior (Q1) so
        // the policy allows. A supervisor with a direct grant must not pass.
        $this->assertTrue($this->policy()->assign($pm2, $this->task));

        $this->givePermission($this->supervisor, 'assign tasks');
        $this->assertFalse($this->policy()->assign($this->supervisor, $this->task));
    }

    // ------------------------------------------------------------------
    // startProgress / submitForReview / manageStages — scope-only abilities
    // ------------------------------------------------------------------

    public function test_scope_only_abilities_follow_membership(): void
    {
        // Supervisor denied outside membership for all three.
        $this->assertFalse($this->policy()->startProgress($this->supervisor, $this->task));
        $this->assertFalse($this->policy()->submitForReview($this->supervisor, $this->task));
        $this->assertFalse($this->policy()->manageStages($this->supervisor, $this->task));

        app(\App\Domain\Services\ProjectMembershipService::class)->assignSupervisor(
            new \App\Domain\DTOs\AssignProjectSupervisorData(
                project_id: $this->project->id,
                user_id: $this->supervisor->id,
                assigned_by: $this->admin->id
            ),
            $this->admin
        );

        $this->assertTrue($this->policy()->startProgress($this->supervisor, $this->task));
        $this->assertTrue($this->policy()->submitForReview($this->supervisor, $this->task));
        $this->assertTrue($this->policy()->manageStages($this->supervisor, $this->task));
    }

    public function test_contractor_actor_is_isolated_by_task_scope_service(): void
    {
        Role::findOrCreate('contractor', 'web');
        $contractorUser = User::factory()->create(['contractor_id' => $this->project->contractor_id]);
        $contractorUser->assignRole('contractor');

        $this->assertTrue($this->policy()->view($contractorUser, $this->task));

        $other = $this->makeProject(2);
        $foreignTask = $this->makeTask($other);
        $this->assertFalse($this->policy()->view($contractorUser, $foreignTask));
        $this->assertFalse($this->policy()->startProgress($contractorUser, $foreignTask));
    }

    // ------------------------------------------------------------------
    // Invariant separation: Policy must NOT replace Service Guards
    // ------------------------------------------------------------------

    public function test_policy_allows_but_service_guard_still_rejects_invalid_transition(): void
    {
        // Task is `assigned`; starting requires going through the state
        // machine. The policy allows an in-scope actor...
        $this->assertTrue($this->policy()->startProgress($this->admin, $this->task));

        // ...but the Service Guard owns the invariant: an already-started
        // (in_progress) task cannot start again even for an allowed actor.
        $this->task->status = 'in_progress';
        $this->task->save();

        $this->expectException(\App\Domain\Exceptions\InvalidTaskTransitionException::class);
        app(\App\Domain\Services\TaskService::class)->startProgress($this->task->refresh(), $this->admin);
    }

    public function test_task_scope_service_remains_canonical_for_contractor_scope(): void
    {
        // canAccess (new read-only delegate) and assertCanAccess share one
        // truth table; the exception contract is unchanged.
        // A user of a DIFFERENT contractor (created via the fixture machinery
        // so the FK on synced_contractors is satisfied) must not access this
        // contractor's task.
        $other = $this->makeProject(2);
        $contractorUser = User::factory()->create(['contractor_id' => $other->contractor_id]);
        $this->assertFalse(app(TaskScopeService::class)->canAccess($this->task, $contractorUser));

        $this->expectException(\App\Domain\Exceptions\ContractorScopeViolationException::class);
        app(TaskScopeService::class)->assertCanAccess($this->task, $contractorUser);
    }
}
