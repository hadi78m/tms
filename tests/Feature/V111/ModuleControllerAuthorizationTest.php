<?php

namespace Tests\Feature\V111;

use App\Domain\DTOs\AssignProjectSupervisorData;
use App\Domain\Services\ProjectMembershipService;
use App\Models\Module;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Feature\V19\Concerns\V19Fixtures;
use Tests\TestCase;

/**
 * V1.11 — Module Phase · I-2 (DEC-060 · DEC-062 · DEC-064 · DEC-065 · DEC-066):
 * ModuleController / ModuleStageController HTTP authorization wiring.
 *
 * I-2 wires the already-approved ModulePolicy into the HTTP surface and adds
 * the ProjectScopeService retrieval filter to the Module index. It changes no
 * routes, middleware, services, models or invariants — the role boundary stays
 * in the route middleware, business invariants stay in the services, and the
 * policy adds Resource Scope only.
 *
 * No V1.11 mirror role is project-scope-restricted on its own (PM/viewer = Q1
 * null scope; employer = OD-2 fail-open, DR-EMP-01 OPEN; admin/management
 * unrestricted). A composite actor (project_manager + active supervisor
 * membership in project A) is therefore used to exercise scope on the WRITE
 * surface: the role mirror passes it in, then Project Scope decides the
 * project — proving the controller actually consults the policy.
 */
class ModuleControllerAuthorizationTest extends TestCase
{
    use RefreshDatabase, V19Fixtures;

    private Project $projectA;

    private Project $projectB;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUserWithRole('admin');
        $this->projectA = $this->makeProject(1);
        $this->projectB = $this->makeProject(2);
    }

    // ------------------------------------------------------------------
    // Fixture helpers
    // ------------------------------------------------------------------

    private function scopeSupervisorToProjectA(User $user): void
    {
        app(ProjectMembershipService::class)->assignSupervisor(
            new AssignProjectSupervisorData(
                project_id: $this->projectA->id,
                user_id: $user->id,
                assigned_by: $this->admin->id
            ),
            $this->admin
        );
    }

    /**
     * A mirror-allowed write role (project_manager) that is ALSO scope
     * restricted (active supervisor membership in project A) — the only way
     * to exercise the Project-Scope half of the policy in V1.11.
     */
    private function scopedWriteActor(): User
    {
        $user = $this->makeUserWithRole('project_manager');
        Role::findOrCreate('supervisor', 'web');
        $user->assignRole('supervisor');
        $user = $user->fresh();
        $this->scopeSupervisorToProjectA($user);

        return $user;
    }

    private function projectsFromIndexResponse($response)
    {
        return collect($response->viewData('projects'))->pluck('id');
    }

    // ------------------------------------------------------------------
    // 1 & 2 — index respects supervisor scope; foreign projects are hidden
    // ------------------------------------------------------------------

    public function test_index_respects_supervisor_project_membership(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);

        $response = $this->actingAs($supervisor)->get(route('modules.index'))->assertOk();
        $ids = $this->projectsFromIndexResponse($response);

        $this->assertTrue($ids->contains($this->projectA->id));
    }

    public function test_index_hides_projects_outside_supervisor_scope(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);

        $response = $this->actingAs($supervisor)->get(route('modules.index'))->assertOk();
        $ids = $this->projectsFromIndexResponse($response);

        $this->assertFalse($ids->contains($this->projectB->id));
        $this->assertEquals([$this->projectA->id], $ids->all());
    }

    // ------------------------------------------------------------------
    // 3 — admin remains unrestricted
    // ------------------------------------------------------------------

    public function test_index_admin_is_scope_unrestricted(): void
    {
        $response = $this->actingAs($this->admin)->get(route('modules.index'))->assertOk();
        $ids = $this->projectsFromIndexResponse($response);

        $this->assertTrue($ids->contains($this->projectA->id));
        $this->assertTrue($ids->contains($this->projectB->id));
    }

    // ------------------------------------------------------------------
    // 4 — PM / viewer preserve current (unrestricted) behavior
    // ------------------------------------------------------------------

    public function test_index_preserves_pm_and_viewer_behavior(): void
    {
        foreach (['project_manager', 'viewer'] as $role) {
            $user = $this->makeUserWithRole($role);

            $response = $this->actingAs($user)->get(route('modules.index'))->assertOk();
            $ids = $this->projectsFromIndexResponse($response);

            $this->assertTrue($ids->contains($this->projectA->id), $role);
            $this->assertTrue($ids->contains($this->projectB->id), $role);
        }
    }

    // ------------------------------------------------------------------
    // 5 — employer behavior unchanged pending DR-EMP-01
    // ------------------------------------------------------------------

    public function test_index_preserves_employer_behavior_pending_dr_emp_01(): void
    {
        $employer = $this->makeUserWithRole('employer');

        $response = $this->actingAs($employer)->get(route('modules.index'))->assertOk();
        $ids = $this->projectsFromIndexResponse($response);

        $this->assertTrue($ids->contains($this->projectA->id));
        $this->assertTrue($ids->contains($this->projectB->id));
    }

    // ------------------------------------------------------------------
    // 6 — show authorizes via ModulePolicy (Project Scope)
    // ------------------------------------------------------------------

    public function test_show_authorizes_project_scope(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);

        $this->actingAs($supervisor)
            ->get(route('modules.show', $this->projectA->id))
            ->assertOk();

        // Direct-ID enumeration into a foreign project is refused (403).
        $this->actingAs($supervisor)
            ->get(route('modules.show', $this->projectB->id))
            ->assertForbidden();

        // Unrestricted scope still reaches it.
        $this->actingAs($this->admin)
            ->get(route('modules.show', $this->projectB->id))
            ->assertOk();
    }

    // ------------------------------------------------------------------
    // 7 — update authorizes via ModulePolicy (Module → Project scope)
    // ------------------------------------------------------------------

    public function test_update_authorizes_module_scope(): void
    {
        $moduleA = $this->makeModules($this->projectA, $this->admin, [100])->first();
        $moduleB = $this->makeModules($this->projectB, $this->admin, [100])->first();

        $actor = $this->scopedWriteActor();

        $this->actingAs($actor)
            ->post(route('modules.update', $moduleA->id), ['name' => 'Scoped Rename'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('modules', ['id' => $moduleA->id, 'name' => 'Scoped Rename']);

        $this->actingAs($actor)
            ->post(route('modules.update', $moduleB->id), ['name' => 'Foreign Rename'])
            ->assertForbidden();

        $this->assertDatabaseMissing('modules', ['id' => $moduleB->id, 'name' => 'Foreign Rename']);
    }

    // ------------------------------------------------------------------
    // 8 — store authorizes ModulePolicy::create against the request project
    // ------------------------------------------------------------------

    public function test_store_authorizes_create_against_the_request_project(): void
    {
        $actor = $this->scopedWriteActor();

        // Proof of "no permission dependency": the actor holds roles but zero
        // permissions, and creation still succeeds on an in-scope project.
        $this->assertCount(0, $actor->getAllPermissions());

        $this->actingAs($actor)
            ->post(route('modules.store'), [
                'project_id' => $this->projectA->id,
                'modules' => [['name' => 'Scoped Module', 'weight' => 100]],
            ])
            ->assertRedirect(route('modules.show', $this->projectA->id));

        $this->assertDatabaseHas('modules', [
            'project_id' => $this->projectA->id,
            'name' => 'Scoped Module',
        ]);

        // Foreign project: ModulePolicy::create denies BEFORE any write.
        $this->actingAs($actor)
            ->post(route('modules.store'), [
                'project_id' => $this->projectB->id,
                'modules' => [['name' => 'Foreign Module', 'weight' => 100]],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('modules', [
            'project_id' => $this->projectB->id,
            'name' => 'Foreign Module',
        ]);
    }

    // ------------------------------------------------------------------
    // 9 — rebalance authorizes ModulePolicy::rebalance (resource = Project)
    // ------------------------------------------------------------------

    public function test_rebalance_authorizes_project_resource(): void
    {
        $modulesA = $this->makeModules($this->projectA, $this->admin, [100]);
        $modulesB = $this->makeModules($this->projectB, $this->admin, [100]);

        $actor = $this->scopedWriteActor();

        $this->actingAs($actor)
            ->post(route('modules.rebalance', $this->projectA->id), [
                'weights' => [$modulesA->first()->id => 100],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->actingAs($actor)
            ->post(route('modules.rebalance', $this->projectB->id), [
                'weights' => [$modulesB->first()->id => 100],
            ])
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // 10 — stage rebalance authorizes ModulePolicy::rebalanceStages
    //      (route binds a MODULE, not a ModuleStage)
    // ------------------------------------------------------------------

    public function test_rebalance_stages_authorizes_module_resource(): void
    {
        $moduleA = $this->makeModules($this->projectA, $this->admin, [100])->first();
        $moduleB = $this->makeModules($this->projectB, $this->admin, [100])->first();

        $actor = $this->scopedWriteActor();

        $stagesA = $moduleA->stages()->orderBy('sort_order')->get();
        $analysis = $stagesA->firstWhere('stage_code', 'analysis');
        $coding = $stagesA->firstWhere('stage_code', 'coding');

        // In scope: proceeds to the service (sum stays 100).
        $this->actingAs($actor)
            ->post(route('modules.stages.rebalance', $moduleA->id), [
                'weights' => [$analysis->id => 20, $coding->id => 30],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('module_stages', ['id' => $analysis->id, 'weight' => 20]);

        // Foreign module: refused by the policy before the service runs.
        $this->actingAs($actor)
            ->post(route('modules.stages.rebalance', $moduleB->id), [
                'weights' => [$moduleB->stages()->first()->id => 20],
            ])
            ->assertForbidden();

        // The refusal is authorization, not a service weight error.
        $this->actingAs($actor)
            ->post(route('modules.stages.rebalance', $moduleB->id), [
                'weights' => [$moduleB->stages()->first()->id => 20],
            ])
            ->assertSessionMissing('error');
    }

    // ------------------------------------------------------------------
    // 11 & 12 — no ModuleStagePolicy, no new permission in I-2
    // ------------------------------------------------------------------

    public function test_no_module_stage_policy_is_introduced_in_i2(): void
    {
        $this->assertFalse(class_exists(\App\Policies\ModuleStagePolicy::class));
        $this->assertFileDoesNotExist(app_path('Policies/ModuleStagePolicy.php'));
    }

    public function test_no_new_module_permission_is_introduced(): void
    {
        foreach (['view modules', 'manage modules', 'rebalance modules', 'rebalance stages'] as $name) {
            $this->assertSame(0, Permission::where('name', $name)->count(), $name);
        }

        $this->assertSame(0, Permission::where('name', 'like', '%module%')->count());
    }
}
