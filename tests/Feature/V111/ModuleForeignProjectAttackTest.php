<?php

namespace Tests\Feature\V111;

use App\Domain\DTOs\AssignProjectSupervisorData;
use App\Domain\Services\ProjectMembershipService;
use App\Http\Controllers\Web\ModuleStageController;
use App\Models\Module;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Feature\V19\Concerns\V19Fixtures;
use Tests\TestCase;

/**
 * V1.11 — Module Phase · I-3 (DEC-060 · DEC-062 · DEC-064 · DEC-065 · DEC-066):
 * foreign-project attack matrix for the Module HTTP surface wired in I-2.
 *
 * TEST-FIRST security validation: this phase adds NO production code. It proves
 * that the I-2 authorization actually rejects cross-project / unauthorized
 * resource access at HTTP level, and it pins WHICH layer does the rejecting.
 *
 * Two distinct denial layers exist — never conflated:
 *   Layer M (route middleware)  role:admin|pm|employer (write) ·
 *                               role:...|supervisor|viewer (read)
 *   Layer P (ModulePolicy)      Project Scope via ProjectScopeService
 *
 * Actors used (scope is the only axis that separates them):
 *   admin / management        unrestricted scope, admin also in the write mw
 *   scopedPm                  project_manager + ACTIVE supervisor membership
 *                             in project A → passes Layer M, Layer P restricts
 *   supervisorA               supervisor + ACTIVE membership in A (Layer M read)
 *   supervisorNoMembership    supervisor with no membership (Layer M read)
 *   pm / viewer               preserve current behavior (scope null)
 *   employer                  current fail-open behavior — DR-EMP-01 OPEN
 *   contractor                denied by Layer M across the surface
 *
 * Distinction enforced by tests (see test_business_invariant_*):
 *   A. resource authorization failure → 403, Layer M or Layer P
 *   B. business invariant failure     → 302 + error flash (service-owned)
 * A service/invariant failure is NEVER asserted as an authorization failure.
 */
class ModuleForeignProjectAttackTest extends TestCase
{
    use RefreshDatabase, V19Fixtures;

    private Project $projectA;

    private Project $projectB;

    private Module $moduleA;

    private Module $moduleB;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUserWithRole('admin');
        $this->projectA = $this->makeProject(1);
        $this->projectB = $this->makeProject(2);

        $this->moduleA = $this->makeModules($this->projectA, $this->admin, [100])->first();
        $this->moduleB = $this->makeModules($this->projectB, $this->admin, [100])->first();
    }

    // ------------------------------------------------------------------
    // Fixtures
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

    /** supervisor with an ACTIVE membership in project A. */
    private function supervisorA(): User
    {
        $user = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($user);

        return $user;
    }

    /**
     * project_manager (passes the write middleware) whose Project Scope is
     * restricted to project A via an active supervisor membership. This is the
     * ONLY way to reach Layer P on a write route in V1.11, since no mirror role
     * is scope-restricted on its own.
     */
    private function scopedPm(): User
    {
        $user = $this->makeUserWithRole('project_manager');
        Role::findOrCreate('supervisor', 'web');
        $user->assignRole('supervisor');
        $user = $user->fresh();
        $this->scopeSupervisorToProjectA($user);

        return $user;
    }

    /**
     * A valid stage-rebalance partial map whose RESULTING sum stays 100
     * (analysis 15→20, coding 35→30), so only authorization can reject it.
     *
     * @return array<string, array<int, float>>
     */
    private function validStageWeights(Module $module): array
    {
        $stages = $module->stages()->orderBy('sort_order')->get();

        return [
            'weights' => [
                $stages->firstWhere('stage_code', 'analysis')->id => 20,
                $stages->firstWhere('stage_code', 'coding')->id => 30,
            ],
        ];
    }

    // ==================================================================
    // 1 & 3 — Scope-restricted actor (membership in A) vs foreign project
    // ==================================================================

    public function test_scoped_supervisor_cannot_view_foreign_project_module(): void
    {
        // Layer P (viewProject). Supervisor is in the read middleware.
        $this->actingAs($this->supervisorA())
            ->get(route('modules.show', $this->projectB->id))
            ->assertForbidden();
    }

    public function test_scoped_supervisor_can_view_own_project_module(): void
    {
        // Layer M passes (read role), Layer P allows (in scope).
        $this->actingAs($this->supervisorA())
            ->get(route('modules.show', $this->projectA->id))
            ->assertOk();
    }

    public function test_scoped_supervisor_cannot_update_foreign_module(): void
    {
        // Layer M: supervisor is NOT in the write middleware.
        $this->actingAs($this->supervisorA())
            ->post(route('modules.update', $this->moduleB->id), ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->assertDatabaseMissing('modules', [
            'id' => $this->moduleB->id,
            'name' => 'Hijacked',
        ]);
    }

    public function test_scoped_supervisor_cannot_rebalance_foreign_project(): void
    {
        // Layer M: supervisor is NOT in the write middleware.
        $this->actingAs($this->supervisorA())
            ->post(route('modules.rebalance', $this->projectB->id), [
                'weights' => [$this->moduleB->id => 100],
            ])
            ->assertForbidden();
    }

    public function test_scoped_supervisor_cannot_rebalance_foreign_stages(): void
    {
        // Layer M: supervisor is NOT in the write middleware.
        $this->actingAs($this->supervisorA())
            ->post(route('modules.stages.rebalance', $this->moduleB->id), $this->validStageWeights($this->moduleB))
            ->assertForbidden();
    }

    public function test_scoped_write_actor_cannot_update_foreign_module(): void
    {
        // Layer P (update): passes Layer M, scope denies project B.
        $this->actingAs($this->scopedPm())
            ->post(route('modules.update', $this->moduleB->id), ['name' => 'Hijacked PM'])
            ->assertForbidden();

        $this->assertDatabaseMissing('modules', [
            'id' => $this->moduleB->id,
            'name' => 'Hijacked PM',
        ]);
    }

    public function test_scoped_write_actor_cannot_rebalance_foreign_project(): void
    {
        // Layer P (rebalance): passes Layer M, scope denies project B.
        $this->actingAs($this->scopedPm())
            ->post(route('modules.rebalance', $this->projectB->id), [
                'weights' => [$this->moduleB->id => 100],
            ])
            ->assertForbidden();
    }

    public function test_scoped_write_actor_cannot_rebalance_foreign_stages(): void
    {
        // Layer P (rebalanceStages): passes Layer M, scope denies project B.
        // The denial is authorization, so no service error flash appears.
        $this->actingAs($this->scopedPm())
            ->post(route('modules.stages.rebalance', $this->moduleB->id), $this->validStageWeights($this->moduleB))
            ->assertForbidden()
            ->assertSessionMissing('error');
    }

    // ==================================================================
    // 2 — Supervisor with NO membership
    // ==================================================================

    public function test_membershipless_supervisor_index_is_empty(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');

        $response = $this->actingAs($supervisor)->get(route('modules.index'))->assertOk();

        $this->assertCount(0, collect($response->viewData('projects')));
    }

    public function test_membershipless_supervisor_cannot_view_named_module(): void
    {
        // Layer P: empty scope → project A denied too (not just foreign).
        $supervisor = $this->makeUserWithRole('supervisor');

        $this->actingAs($supervisor)
            ->get(route('modules.show', $this->projectA->id))
            ->assertForbidden();
    }

    // ==================================================================
    // 4 — Admin remains unrestricted
    // ==================================================================

    public function test_admin_is_unrestricted_across_projects(): void
    {
        $this->actingAs($this->admin)
            ->get(route('modules.show', $this->projectB->id))
            ->assertOk();

        $this->actingAs($this->admin)
            ->post(route('modules.update', $this->moduleB->id), ['name' => 'Admin Rename'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->actingAs($this->admin)
            ->post(route('modules.rebalance', $this->projectB->id), [
                'weights' => [$this->moduleB->id => 100],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->actingAs($this->admin)
            ->post(route('modules.stages.rebalance', $this->moduleB->id), $this->validStageWeights($this->moduleB))
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    // ==================================================================
    // 5 — PM / viewer preserve current behavior (no invented scope)
    // ==================================================================

    public function test_pm_preserves_unrestricted_read_and_write(): void
    {
        $pm = $this->makeUserWithRole('project_manager');

        $this->actingAs($pm)->get(route('modules.show', $this->projectB->id))->assertOk();

        $this->actingAs($pm)
            ->post(route('modules.update', $this->moduleB->id), ['name' => 'PM Rename'])
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_viewer_preserves_read_only_behavior(): void
    {
        $viewer = $this->makeUserWithRole('viewer');

        // Read: preserves current (unrestricted) behavior.
        $this->actingAs($viewer)->get(route('modules.show', $this->projectB->id))->assertOk();

        // Write: Layer M denies (viewer is not a write role).
        $this->actingAs($viewer)
            ->post(route('modules.update', $this->moduleB->id), ['name' => 'Viewer Rename'])
            ->assertForbidden();
    }

    // ==================================================================
    // 6 — Employer: current implementation only (DR-EMP-01 OPEN)
    // ==================================================================

    public function test_employer_current_behavior_is_pinned_without_deciding_dr_emp_01(): void
    {
        // DR-EMP-01 remains OPEN: employer has no project-scope identity, so
        // ProjectScopeService returns null (fail-open). This test pins the
        // CURRENT implementation — it is NOT a decision on employer scope.
        $employer = $this->makeUserWithRole('employer');

        $this->actingAs($employer)->get(route('modules.show', $this->projectB->id))->assertOk();

        $this->actingAs($employer)
            ->post(route('modules.update', $this->moduleB->id), ['name' => 'Employer Rename'])
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    // ==================================================================
    // 7 — Contractor: denied across the whole surface
    // ==================================================================

    public function test_contractor_is_denied_across_the_module_surface(): void
    {
        $contractor = $this->makeUserWithRole('contractor');

        $this->actingAs($contractor)->get(route('modules.index'))->assertForbidden();
        $this->actingAs($contractor)->get(route('modules.show', $this->projectA->id))->assertForbidden();

        $this->actingAs($contractor)->post(route('modules.store'), [
            'project_id' => $this->projectA->id,
            'modules' => [['name' => 'Contractor Module', 'weight' => 100]],
        ])->assertForbidden();

        $this->actingAs($contractor)
            ->post(route('modules.update', $this->moduleA->id), ['name' => 'Contractor Rename'])
            ->assertForbidden();

        $this->actingAs($contractor)
            ->post(route('modules.rebalance', $this->projectA->id), ['weights' => [$this->moduleA->id => 100]])
            ->assertForbidden();

        $this->actingAs($contractor)
            ->post(route('modules.stages.rebalance', $this->moduleA->id), $this->validStageWeights($this->moduleA))
            ->assertForbidden();

        $this->assertDatabaseMissing('modules', ['name' => 'Contractor Module']);
    }

    // ==================================================================
    // 8 — Stage rebalance: authorization target remains Module
    // ==================================================================

    public function test_stage_rebalance_authorization_target_remains_module(): void
    {
        // The route binds a MODULE (modules.stages.rebalance) — the controller
        // parameter type proves the authorization target, not a ModuleStage.
        $method = new \ReflectionMethod(ModuleStageController::class, 'rebalance');
        $this->assertSame(Module::class, (string) $method->getParameters()[0]->getType());

        // The ability lives on ModulePolicy; I-4 (owner-approved) added
        // ModuleStagePolicy but ONLY with a view ability — rebalance
        // authorization stays module-level and must never leak to a stage
        // ability (updateWeight etc.).
        $this->assertTrue(method_exists(\App\Policies\ModulePolicy::class, 'rebalanceStages'));
        $this->assertTrue(class_exists(\App\Policies\ModuleStagePolicy::class));
        $this->assertFalse(method_exists(\App\Policies\ModuleStagePolicy::class, 'rebalanceStages'));
        $this->assertFalse(method_exists(\App\Policies\ModuleStagePolicy::class, 'updateWeight'));
    }

    // ==================================================================
    // Attack A — request-body project_id tampering on store
    // ==================================================================

    public function test_store_body_project_id_tampering_is_denied_before_the_service(): void
    {
        // Project B ALREADY has a module, so a bypassed authorization would
        // reach the service and fail with a "second definition" error flash
        // (302). Asserting 403 proves authorization fired FIRST.
        $this->actingAs($this->scopedPm())
            ->post(route('modules.store'), [
                'project_id' => $this->projectB->id,
                'modules' => [['name' => 'Tampered', 'weight' => 100]],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('modules', ['name' => 'Tampered']);
        $this->assertSame(1, Module::where('project_id', $this->projectB->id)->count());
    }

    // ==================================================================
    // A vs B — authorization denial is never a business-invariant denial
    // ==================================================================

    public function test_business_invariant_failure_is_not_an_authorization_failure(): void
    {
        // In-scope actor, invalid weight sum → business invariant (service)
        // rejects with a redirect + error flash — NOT 403.
        $this->actingAs($this->scopedPm())
            ->post(route('modules.rebalance', $this->projectA->id), [
                'weights' => [$this->moduleA->id => 60],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        // The SAME invalid payload against a foreign project is a 403 —
        // authorization denies before the invariant is ever evaluated.
        $this->actingAs($this->scopedPm())
            ->post(route('modules.rebalance', $this->projectB->id), [
                'weights' => [$this->moduleB->id => 60],
            ])
            ->assertForbidden();
    }
}
