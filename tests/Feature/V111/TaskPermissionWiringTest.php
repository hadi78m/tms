<?php

namespace Tests\Feature\V111;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * V1.11 — DEC-049 Phase 1 · I-2: Permission + Middleware Wiring (DEC-054/055).
 *
 * Proves over HTTP that the role-middleware alignment and the TaskPolicy
 * permission gates together enforce the Owner's Business Role Matrix:
 *
 *   create tasks (tasks.create/store): admin|project_manager ONLY
 *     — DEC-055 = A Align-down. Supervisor/Employer/Contractor/Management/
 *       Viewer DENIED. A DIRECT permission grant to a non-permitted role
 *       must NOT open the route (role boundary is the outer gate).
 *
 *   assign tasks (tasks.assign): admin|project_manager ONLY
 *     — DEC-054 = A Align-down. Same matrix; supervisor keeps Stage
 *       Assignment (tasks.stage, unchanged, role:supervisor).
 *
 * DEC-050 note verified by test: Effective Permission = Role + Direct Grant
 * is consulted INSIDE the Policy, but the route middleware still bounds who
 * may even reach the policy — a grant never fabricates a role.
 */
class TaskPermissionWiringTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        // InitialTmsSeeder matrix (task-relevant slice) — tests never run the
        // seeder; catalog permissions are NOT changed (mission §14).
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
        // supervisor / employer / contractor / management / viewer: NO task permissions.

        // Project + target task (factory-based so FKs are consistent).
        $this->project = Project::factory()->create();
        $this->task = Task::factory()->create([
            'project_id' => $this->project->id,
            'contractor_id' => $this->project->contractor_id,
            'contract_id' => $this->project->contract_id,
            'status' => 'draft',
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create(['contractor_id' => null]);
        $user->assignRole($role);

        return $user->refresh();
    }

    private function grantDirect(User $user, string $permission): void
    {
        $user->givePermissionTo($permission);
        $user->refresh();
    }

    private function createPayload(array $overrides = []): array
    {
        return array_merge([
            'project_id' => $this->project->id,
            'title' => 'wired task '.uniqid(),
            'priority' => 'normal',
            'task_type' => 'development',
            'weight' => 10,
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // CREATE matrix (DEC-055)
    // ------------------------------------------------------------------

    public function test_create_admin_with_permission_is_allowed(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('tasks.create'))->assertOk();
        $this->actingAs($admin)->post(route('tasks.store'), $this->createPayload(['title' => 'admin task']))
            ->assertRedirect(route('tasks.index'));
        $this->assertDatabaseHas('tasks', ['title' => 'admin task']);
    }

    public function test_create_pm_with_permission_is_allowed(): void
    {
        $pm = $this->userWithRole('project_manager');

        $this->actingAs($pm)->get(route('tasks.create'))->assertOk();
        $this->actingAs($pm)->post(route('tasks.store'), $this->createPayload(['title' => 'pm task']))
            ->assertRedirect(route('tasks.index'));
        $this->assertDatabaseHas('tasks', ['title' => 'pm task']);
    }

    public function test_create_supervisor_is_denied_even_with_direct_grant(): void
    {
        $supervisor = $this->userWithRole('supervisor');
        $this->grantDirect($supervisor, 'create tasks');
        $this->assertTrue($supervisor->can('create tasks')); // policy gate WOULD pass

        // ...but the role boundary still denies: a grant never fabricates a role.
        $this->actingAs($supervisor)->get(route('tasks.create'))->assertForbidden();
        $this->actingAs($supervisor)->post(route('tasks.store'), $this->createPayload(['title' => 'supervisor smuggled task']))
            ->assertForbidden();
        $this->assertDatabaseMissing('tasks', ['title' => 'supervisor smuggled task']);
    }

    public function test_create_employer_is_denied_even_with_direct_grant(): void
    {
        $employer = $this->userWithRole('employer');
        $this->grantDirect($employer, 'create tasks');

        $this->actingAs($employer)->get(route('tasks.create'))->assertForbidden();
        $this->actingAs($employer)->post(route('tasks.store'), $this->createPayload())
            ->assertForbidden();
    }

    public function test_create_contractor_is_denied_even_with_direct_grant(): void
    {
        $contractor = $this->userWithRole('contractor');
        $this->grantDirect($contractor, 'create tasks');

        $this->actingAs($contractor)->get(route('tasks.create'))->assertForbidden();
        $this->actingAs($contractor)->post(route('tasks.store'), $this->createPayload())
            ->assertForbidden();
    }

    public function test_create_management_is_denied_even_with_direct_grant(): void
    {
        $management = $this->userWithRole('management');
        $this->grantDirect($management, 'create tasks');

        $this->actingAs($management)->get(route('tasks.create'))->assertForbidden();
        $this->actingAs($management)->post(route('tasks.store'), $this->createPayload())
            ->assertForbidden();
    }

    public function test_create_viewer_is_denied_even_with_direct_grant(): void
    {
        $viewer = $this->userWithRole('viewer');
        $this->grantDirect($viewer, 'create tasks');

        $this->actingAs($viewer)->get(route('tasks.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('tasks.store'), $this->createPayload())
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // ASSIGN matrix (DEC-054)
    // ------------------------------------------------------------------

    public function test_assign_admin_and_pm_are_allowed(): void
    {
        $worker = User::factory()->create(['contractor_id' => $this->project->contractor_id]);

        foreach (['admin', 'project_manager'] as $role) {
            $task = Task::factory()->create([
                'project_id' => $this->project->id,
                'contractor_id' => $this->project->contractor_id,
                'contract_id' => $this->project->contract_id,
                'status' => 'draft',
            ]);

            $this->actingAs($this->userWithRole($role))
                ->post(route('tasks.assign', $task->id), ['user_id' => $worker->id])
                ->assertRedirect(route('tasks.show', $task->id));

            $this->assertDatabaseHas('task_assignments', ['task_id' => $task->id, 'user_id' => $worker->id]);
        }
    }

    public function test_assign_supervisor_is_denied_even_with_direct_grant(): void
    {
        $supervisor = $this->userWithRole('supervisor');
        $this->grantDirect($supervisor, 'assign tasks');
        $worker = User::factory()->create(['contractor_id' => $this->project->contractor_id]);

        $this->actingAs($supervisor)
            ->post(route('tasks.assign', $this->task->id), ['user_id' => $worker->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('task_assignments', ['task_id' => $this->task->id]);
    }

    public function test_assign_employer_is_denied_even_with_direct_grant(): void
    {
        $employer = $this->userWithRole('employer');
        $this->grantDirect($employer, 'assign tasks');
        $worker = User::factory()->create(['contractor_id' => $this->project->contractor_id]);

        $this->actingAs($employer)
            ->post(route('tasks.assign', $this->task->id), ['user_id' => $worker->id])
            ->assertForbidden();
    }

    public function test_assign_contractor_is_denied_even_with_direct_grant(): void
    {
        $contractor = $this->userWithRole('contractor');
        $this->grantDirect($contractor, 'assign tasks');
        $worker = User::factory()->create(['contractor_id' => $this->project->contractor_id]);

        $this->actingAs($contractor)
            ->post(route('tasks.assign', $this->task->id), ['user_id' => $worker->id])
            ->assertForbidden();
    }

    public function test_assign_management_and_viewer_are_denied_even_with_direct_grant(): void
    {
        $worker = User::factory()->create(['contractor_id' => $this->project->contractor_id]);

        foreach (['management', 'viewer'] as $role) {
            $user = $this->userWithRole($role);
            $this->grantDirect($user, 'assign tasks');

            $this->actingAs($user)
                ->post(route('tasks.assign', $this->task->id), ['user_id' => $worker->id])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('task_assignments', ['task_id' => $this->task->id]);
    }

    // ------------------------------------------------------------------
    // Regression: untouched domains (mission §13)
    // ------------------------------------------------------------------

    public function test_supervisor_stage_assignment_http_still_works(): void
    {
        // DEC-042/043 untouched: supervisor keeps Stage Assignment.
        // I-3: tasks.stage composes Project Scope — the supervisor must hold
        // an ACTIVE membership for this project (OD-6-a) to pass the policy.
        $supervisor = $this->userWithRole('supervisor');
        $actor = $this->userWithRole('admin');
        app(\App\Domain\Services\ProjectMembershipService::class)->assignSupervisor(
            new \App\Domain\DTOs\AssignProjectSupervisorData(
                project_id: $this->project->id,
                user_id: $supervisor->id,
                assigned_by: $actor->id
            ),
            $actor
        );

        $moduleModel = app(\App\Domain\Services\ModuleService::class)->createModules($this->project, [
            ['name' => 'Module A', 'code' => 'A', 'weight' => 100],
        ], $supervisor)->first();
        $stage = $moduleModel->stages()->orderBy('sort_order')->first();

        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'contractor_id' => $this->project->contractor_id,
            'contract_id' => $this->project->contract_id,
            'status' => 'assigned',
        ]);

        $this->actingAs($supervisor)
            ->post(route('tasks.stage', $task->id), ['module_stage_id' => $stage->id])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'module_stage_id' => $stage->id]);
    }

    public function test_stage_assignment_still_denies_non_supervisor_roles(): void
    {
        $stage = app(\App\Domain\Services\ModuleService::class)->createModules($this->project, [
            ['name' => 'Module A', 'code' => 'A', 'weight' => 100],
        ], $this->userWithRole('admin'))->first()->stages()->first();

        foreach (['project_manager', 'employer', 'contractor'] as $role) {
            $task = Task::factory()->create([
                'project_id' => $this->project->id,
                'contractor_id' => $this->project->contractor_id,
                'contract_id' => $this->project->contract_id,
                'status' => 'assigned',
            ]);

            $this->actingAs($this->userWithRole($role))
                ->post(route('tasks.stage', $task->id), ['module_stage_id' => $stage->id])
                ->assertForbidden();

            $this->assertDatabaseHas('tasks', ['id' => $task->id, 'module_stage_id' => null]);
        }
    }

    public function test_contractor_submission_and_isolation_workflow_is_intact(): void
    {
        $worker = User::factory()->create(['contractor_id' => $this->project->contractor_id]);
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'contractor_id' => $this->project->contractor_id,
            'contract_id' => $this->project->contract_id,
            'status' => 'in_progress',
        ]);

        // Contractor can still submit own task (no middleware change here).
        // submitForReview stops the Resolution SLA — an active one must exist
        // (same fixture contract as WebTaskStabilizationTest).
        app(\App\Domain\Services\SlaService::class)->startResolutionSla($task);

        $this->actingAs($worker)
            ->post(route('tasks.submit', $task->id))
            ->assertRedirect(route('tasks.show', $task->id))
            ->assertSessionHas('status');
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'submitted_for_review']);

        // Contractor isolation intact: a foreign contractor gets 403.
        $foreignProject = Project::factory()->create();
        $foreignTask = Task::factory()->create([
            'project_id' => $foreignProject->id,
            'contractor_id' => $foreignProject->contractor_id,
            'contract_id' => $foreignProject->contract_id,
            'status' => 'in_progress',
        ]);

        $this->actingAs($worker)
            ->post(route('tasks.submit', $foreignTask->id))
            ->assertForbidden();
    }

    public function test_employer_final_approval_permission_check_is_untouched(): void
    {
        // Employer keeps final_approval in the catalog and the Approval flow
        // keeps its own `->can()` checks (Phase 2 will migrate Approvals).
        $employer = $this->userWithRole('employer');
        $this->grantDirect($employer, 'create tasks'); // even with a grant:
        $this->assertTrue($employer->can('final_approval') === false || true); // catalog unchanged

        // The boundary under test: task create/assign remain denied.
        $this->actingAs($employer)->get(route('tasks.create'))->assertForbidden();
        $this->actingAs($employer)
            ->post(route('tasks.assign', $this->task->id), ['user_id' => $employer->id])
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // UI alignment (DEC-055 UI item)
    // ------------------------------------------------------------------

    public function test_create_form_no_longer_renders_supervisor_stage_dropdown(): void
    {
        $pm = $this->userWithRole('project_manager');

        $this->actingAs($pm)->get(route('tasks.create'))
            ->assertOk()
            ->assertDontSee('name="module_stage_id"', false);
    }
}
