<?php

namespace Tests\Feature\V111;

use App\Domain\DTOs\AssignProjectSupervisorData;
use App\Domain\Services\ProjectMembershipService;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * V1.11 — DEC-049 Phase 1 · I-5: Final Authorization Matrix + UI Regression.
 *
 * Verification-first gate (no new authorization behavior):
 *   - GET tasks.create is role-gated admin|PM (DEC-055 route middleware).
 *   - Task detail UI visibility matches server authorization (mission §8):
 *     hiding is NOT authorization — every hidden surface is separately
 *     server-denied (covered by TaskPermissionWiringTest / TaskProjectScopeTest).
 *   - removeDependency enforces Project Scope on a foreign successor task
 *     (mission §6: BOTH dependency endpoints are scope-protected).
 */
class TaskFinalMatrixTest extends TestCase
{
    use RefreshDatabase;

    private Project $projectA;

    private Project $projectB;

    private Task $taskA;

    private Task $taskB;

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

        $this->projectA = Project::factory()->create(['name' => 'Project A']);
        $this->projectB = Project::factory()->create(['name' => 'Project B']);

        $supervisorS = $this->makeUserWithRole('supervisor');
        app(ProjectMembershipService::class)->assignSupervisor(
            new AssignProjectSupervisorData(project_id: $this->projectA->id, user_id: $supervisorS->id, assigned_by: $this->admin->id),
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
    // §8 — Create entry point: role-gated admin|PM (DEC-055)
    // ------------------------------------------------------------------

    public function test_create_page_is_role_gated_admin_and_pm_only(): void
    {
        $this->actingAs($this->admin)->get(route('tasks.create'))->assertOk();

        $pm = $this->makeUserWithRole('project_manager');
        $this->actingAs($pm)->get(route('tasks.create'))->assertOk();

        foreach (['supervisor', 'employer', 'management', 'viewer'] as $role) {
            $user = $this->makeUserWithRole($role);
            $this->actingAs($user)->get(route('tasks.create'))->assertForbidden();
        }

        $contractor = User::factory()->create(['contractor_id' => $this->projectA->contractor_id]);
        $this->actingAs($contractor)->get(route('tasks.create'))->assertForbidden();
    }

    // ------------------------------------------------------------------
    // §8 — UI visibility matches server authorization (hide ≠ authorize)
    // ------------------------------------------------------------------

    public function test_task_detail_ui_matches_authorization_matrix(): void
    {
        $pm = $this->makeUserWithRole('project_manager');

        // Admin + PM: dependency wiring + subtask entry points visible.
        foreach ([$this->admin, $pm] as $actor) {
            $response = $this->actingAs($actor)->get(route('tasks.show', $this->taskA->id))->assertOk();
            $content = $response->getContent();
            $this->assertStringContainsString('depends_on_task_id', $content);
            $this->assertStringContainsString('افزودن زیرتسک', $content);
        }

        // Supervisor: stage panel REMAINS available (DEC-042 preserved) but
        // no Create/dependency entry points.
        $supervisor = User::role('supervisor')->firstOrFail();
        $response = $this->actingAs($supervisor)->get(route('tasks.show', $this->taskA->id))->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('module_stage_id', $content);
        $this->assertStringNotContainsString('depends_on_task_id', $content);
        $this->assertStringNotContainsString('افزودن زیرتسک', $content);

        // Management + Viewer: may read (scope-unrestricted / Q1) but get no
        // write entry points.
        foreach (['management', 'viewer'] as $role) {
            $user = $this->makeUserWithRole($role);
            $response = $this->actingAs($user)->get(route('tasks.show', $this->taskA->id))->assertOk();
            $content = $response->getContent();
            $this->assertStringNotContainsString('depends_on_task_id', $content);
            $this->assertStringNotContainsString('module_stage_id', $content);
        }

        // Contractor of project A: start/submit workflow visible for their
        // assigned task; no dependency/stage/subtask entry points.
        $worker = User::factory()->create(['contractor_id' => $this->projectA->contractor_id]);
        $response = $this->actingAs($worker)->get(route('tasks.show', $this->taskA->id))->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('/tasks/'.$this->taskA->id.'/start', $content);
        $this->assertStringNotContainsString('depends_on_task_id', $content);
        $this->assertStringNotContainsString('module_stage_id', $content);
    }

    // ------------------------------------------------------------------
    // §6 — removeDependency: foreign successor is scope-denied
    // ------------------------------------------------------------------

    public function test_remove_dependency_is_scope_denied_for_foreign_project(): void
    {
        $dependency = TaskDependency::create([
            'successor_task_id' => $this->taskB->id,
            'predecessor_task_id' => $this->taskA->id,
            'dependency_type' => 'fs',
            'created_by' => $this->admin->id,
        ]);

        $supervisor = User::role('supervisor')->firstOrFail();
        $this->actingAs($supervisor)
            ->post(route('tasks.dependencies.destroy', [$this->taskB->id, $dependency->id]))
            ->assertForbidden();

        // Row untouched — denial happened before the write.
        $this->assertDatabaseHas('task_dependencies', ['id' => $dependency->id]);

        // Admin (scope-unrestricted) may remove it.
        $this->actingAs($this->admin)
            ->post(route('tasks.dependencies.destroy', [$this->taskB->id, $dependency->id]))
            ->assertRedirect();
        $this->assertDatabaseMissing('task_dependencies', ['id' => $dependency->id]);
    }
}
