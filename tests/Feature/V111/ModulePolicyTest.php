<?php

namespace Tests\Feature\V111;

use App\Domain\DTOs\AssignProjectSupervisorData;
use App\Domain\Rules\ProjectScopeService;
use App\Domain\Services\ModuleService;
use App\Models\Module;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Feature\V18\Concerns\V18Fixtures;
use Tests\TestCase;

/**
 * V1.11 — Module Phase · I-1 (DEC-060 · DEC-062 · DEC-064 · DEC-065 · DEC-066):
 * ModulePolicy unit/policy-level behavior.
 *
 * Authorization composition under test:
 *   Role boundary  = route middleware (authoritative — NOT tested here)
 *   Policy         = Project Scope via ProjectScopeService (canonical)
 *                    + role mirror ONLY on rebalanceStages (W-B, DEC-066)
 *   Service Guard  = untouched (invariants stay in ModuleService/
 *                    ModuleStageService — a policy-allowed call still fails
 *                    in the service when an invariant forbids it)
 *
 * I-1 scope: NO controller wiring, NO middleware change, NO query scope, NO
 * ModuleStagePolicy (I-4) — the policy object itself is exercised directly
 * (infrastructure gate), exactly like TaskPolicyTest / StageProgressApprovalPolicyTest.
 *
 * Scope semantics are consumed from ProjectScopeService — NO new scope rule
 * is invented for any role. Employer behavior = current service behavior
 * (scope null → allowed) because DR-EMP-01 / OD-2 remain OPEN and must not
 * be guessed (DEC-057 open dependency).
 */
class ModulePolicyTest extends TestCase
{
    use RefreshDatabase, V18Fixtures;

    private \App\Policies\ModulePolicy $policy;

    private Project $projectA;

    private Project $projectB;

    private Module $moduleA;

    private Module $moduleB;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = app(\App\Policies\ModulePolicy::class);

        $this->admin = $this->makeUserWithRole('admin');
        $this->projectA = $this->makeProject(1);
        $this->projectB = $this->makeProject(2);

        $this->moduleA = $this->makeModules($this->projectA, $this->admin, [100])->first();
        $this->moduleB = $this->makeModules($this->projectB, $this->admin, [100])->first();
    }

    private function scopeSupervisorToProjectA(User $supervisor): void
    {
        app(\App\Domain\Services\ProjectMembershipService::class)->assignSupervisor(
            new AssignProjectSupervisorData(
                project_id: $this->projectA->id,
                user_id: $supervisor->id,
                assigned_by: $this->admin->id
            ),
            $this->admin
        );
    }

    // ------------------------------------------------------------------
    // Infrastructure: auto-discovery + ability surface
    // ------------------------------------------------------------------

    public function test_module_policy_is_discoverable_and_resolves_from_container(): void
    {
        $this->assertInstanceOf(\App\Policies\ModulePolicy::class, $this->policy);

        // Gate knows the model's policy via Laravel auto-discovery — no
        // Gate::policy registration exists (DEC-050 convention, no conflict).
        $this->assertSame(\App\Policies\ModulePolicy::class, Gate::getPolicyFor($this->moduleA)::class);
    }

    public function test_policy_has_exactly_the_six_real_abilities(): void
    {
        $methods = collect((new \ReflectionClass(\App\Policies\ModulePolicy::class))
            ->getMethods(\ReflectionMethod::IS_PUBLIC))
            ->filter(fn ($m) => ! str_starts_with($m->getName(), '__'))
            ->map(fn ($m) => $m->getName())
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'create', 'rebalance', 'rebalanceStages', 'update', 'view', 'viewProject',
        ], $methods);
    }

    public function test_policy_has_no_delete_ability(): void
    {
        // No module delete HTTP surface exists (DEC-038/039) — inventing a
        // policy ability would authorize an operation the app cannot perform.
        $class = new \ReflectionClass(\App\Policies\ModulePolicy::class);

        $this->assertFalse($class->hasMethod('delete'));
        $this->assertFalse($class->hasMethod('destroy'));
        $this->assertFalse($class->hasMethod('archive'));
        $this->assertFalse($class->hasMethod('restore'));
    }

    public function test_policy_depends_on_no_permission(): void
    {
        // DEC-064 = Role + Project Scope: no module permission exists in the
        // catalog, none was created (I-1 scope lock), and the policy must not
        // consult any permission. The catalog pin is the repository-wide
        // regression assertion.
        $names = Permission::query()->pluck('name')->all();

        foreach (['manage modules', 'manage module stages', 'rebalance modules', 'rebalance module stages', 'edit module weight'] as $forbidden) {
            $this->assertNotContains($forbidden, $names);
        }

        // And the policy passes for a role holding NO permission at all —
        // scope is the only gate (admin fixture carries no synced permissions
        // in this suite, proving no permission is consulted).
        $this->assertTrue($this->policy->viewProject($this->admin, $this->projectA));
    }

    // ------------------------------------------------------------------
    // viewProject — resource = Project (modules.index / modules.show)
    // ------------------------------------------------------------------

    public function test_view_project_admin_and_management_are_scope_unrestricted(): void
    {
        $management = $this->makeUserWithRole('management');

        $this->assertTrue($this->policy->viewProject($this->admin, $this->projectA));
        $this->assertTrue($this->policy->viewProject($this->admin, $this->projectB));
        $this->assertTrue($this->policy->viewProject($management, $this->projectA));
        $this->assertTrue($this->policy->viewProject($management, $this->projectB));
    }

    public function test_view_project_pm_and_viewer_preserve_current_behavior(): void
    {
        // Q1 (DEC-048): PM / viewer derive no project scope (null =
        // unrestricted) — current behavior preserved, no new rule.
        $pm = $this->makeUserWithRole('project_manager');
        $viewer = $this->makeUserWithRole('viewer');

        $this->assertTrue($this->policy->viewProject($pm, $this->projectA));
        $this->assertTrue($this->policy->viewProject($pm, $this->projectB));
        $this->assertTrue($this->policy->viewProject($viewer, $this->projectA));
    }

    public function test_view_project_employer_preserves_current_scope_behavior(): void
    {
        // Employer scope is NOT derived (OD-2 deferred / DR-EMP-01 OPEN).
        // The policy consumes employerBranch() via the service — no local
        // employer rule is invented here.
        $employer = $this->makeUserWithRole('employer');

        $this->assertTrue($this->policy->viewProject($employer, $this->projectA));
        $this->assertNull(app(ProjectScopeService::class)->accessibleProjectIds($employer));
    }

    public function test_view_project_supervisor_is_membership_scoped(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');

        // No membership anywhere → denied on both projects.
        $this->assertFalse($this->policy->viewProject($supervisor, $this->projectA));
        $this->assertFalse($this->policy->viewProject($supervisor, $this->projectB));

        // Membership on A → A allowed, B still denied.
        $this->scopeSupervisorToProjectA($supervisor);
        $this->assertTrue($this->policy->viewProject($supervisor, $this->projectA));
        $this->assertFalse($this->policy->viewProject($supervisor, $this->projectB));
    }

    public function test_view_project_contractor_preserves_current_behavior(): void
    {
        // Contractor derives no project scope in ProjectScopeService (its
        // isolation is actor-side, untouched — OQ-32/DEC-051 remain blocked).
        Role::findOrCreate('contractor', 'web');
        $contractor = User::factory()->create(['contractor_id' => $this->projectA->contractor_id]);
        $contractor->assignRole('contractor');

        $this->assertTrue($this->policy->viewProject($contractor, $this->projectA));
    }

    // ------------------------------------------------------------------
    // view — resource = Module (scope via Module → Project, fail-closed)
    // ------------------------------------------------------------------

    public function test_view_allows_module_in_accessible_project(): void
    {
        $this->assertTrue($this->policy->view($this->admin, $this->moduleA));
        $this->assertTrue($this->policy->view($this->admin, $this->moduleB));
    }

    public function test_view_denies_supervisor_for_foreign_project_module(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);

        $this->assertTrue($this->policy->view($supervisor, $this->moduleA));
        $this->assertFalse($this->policy->view($supervisor, $this->moduleB));
    }

    public function test_view_is_fail_closed_when_project_relation_is_missing(): void
    {
        // An unsaved Module with no resolvable project must fail CLOSED —
        // no null => unrestricted fallback exists.
        $orphan = new Module(['project_id' => 0]);

        $this->assertFalse($this->policy->view($this->admin, $orphan));
        $this->assertFalse($this->policy->update($this->admin, $orphan));
        $this->assertFalse($this->policy->rebalanceStages($this->admin, $orphan));
    }

    // ------------------------------------------------------------------
    // create — resource = Project (modules.store: project_id from body)
    // ------------------------------------------------------------------

    public function test_create_allows_scope_for_target_project(): void
    {
        $pm = $this->makeUserWithRole('project_manager');

        $this->assertTrue($this->policy->create($this->admin, $this->projectA));
        $this->assertTrue($this->policy->create($pm, $this->projectB)); // Q1: PM scope null
    }

    public function test_create_denies_supervisor_outside_membership(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);

        // A supervisor must NOT be able to define modules even in an
        // in-scope project via this gate — wait: scope allows A. The policy
        // is scope-only; the ROLE boundary (write mw) stays upstream.
        $this->assertTrue($this->policy->create($supervisor, $this->projectA));
        $this->assertFalse($this->policy->create($supervisor, $this->projectB));
    }

    // ------------------------------------------------------------------
    // update — resource = Module
    // ------------------------------------------------------------------

    public function test_update_follows_module_project_scope(): void
    {
        $pm = $this->makeUserWithRole('project_manager');
        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);

        $this->assertTrue($this->policy->update($this->admin, $this->moduleA));
        $this->assertTrue($this->policy->update($pm, $this->moduleB));

        $this->assertTrue($this->policy->update($supervisor, $this->moduleA));
        $this->assertFalse($this->policy->update($supervisor, $this->moduleB));
    }

    // ------------------------------------------------------------------
    // rebalance — resource = Project; NOT an alias of create
    // ------------------------------------------------------------------

    public function test_rebalance_follows_project_scope(): void
    {
        $pm = $this->makeUserWithRole('project_manager');
        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);

        $this->assertTrue($this->policy->rebalance($this->admin, $this->projectA));
        $this->assertTrue($this->policy->rebalance($pm, $this->projectB));

        $this->assertTrue($this->policy->rebalance($supervisor, $this->projectA));
        $this->assertFalse($this->policy->rebalance($supervisor, $this->projectB));
    }

    public function test_rebalance_is_a_distinct_ability_from_create(): void
    {
        // Semantic distinction is pinned at the surface level: rebalance is
        // its own policy method (see ability-surface test) — this test pins
        // that the class does NOT delegate by aliasing (both methods exist
        // independently and are individually invokable).
        $class = new \ReflectionClass(\App\Policies\ModulePolicy::class);

        $this->assertTrue($class->hasMethod('create'));
        $this->assertTrue($class->hasMethod('rebalance'));

        $create = $class->getMethod('create');
        $rebalance = $class->getMethod('rebalance');

        $this->assertNotSame(
            $create->getFileName().'@'.$create->getStartLine(),
            $rebalance->getFileName().'@'.$rebalance->getStartLine()
        );
    }

    // ------------------------------------------------------------------
    // rebalanceStages — resource = Module; role mirror (W-B, DEC-066)
    // ------------------------------------------------------------------

    public function test_rebalance_stages_allows_mirror_roles_within_scope(): void
    {
        $pm = $this->makeUserWithRole('project_manager');
        $employer = $this->makeUserWithRole('employer');

        // admin / PM / employer are the exact route mw list — scope applies.
        $this->assertTrue($this->policy->rebalanceStages($this->admin, $this->moduleA));
        $this->assertTrue($this->policy->rebalanceStages($pm, $this->moduleB));   // Q1: scope null
        $this->assertTrue($this->policy->rebalanceStages($employer, $this->moduleA)); // OD-2 fail-open (DR-EMP-01 OPEN)
    }

    public function test_rebalance_stages_applies_project_scope_to_mirror_roles(): void
    {
        // V1.11 reality: a bare project_manager / employer carries NO project
        // scope restriction yet (PM = Q1 null scope; employer = OD-2
        // fail-open, DR-EMP-01 OPEN), so scope cannot be exercised through a
        // single-role actor. This test pins that rebalanceStages genuinely
        // CONSULTS ProjectScopeService by using an actor who is BOTH
        // mirror-allowed (project_manager) AND scope-restricted (active
        // supervisor membership in project A): the role mirror passes him in,
        // then project scope decides the project.
        $actor = $this->makeUserWithRole('project_manager');
        Role::findOrCreate('supervisor', 'web');
        $actor->assignRole('supervisor');
        $actor = $actor->fresh();
        $this->scopeSupervisorToProjectA($actor);

        $this->assertTrue($this->policy->rebalanceStages($actor, $this->moduleA));  // in-scope project
        $this->assertFalse($this->policy->rebalanceStages($actor, $this->moduleB)); // foreign project → scope denies

        $this->assertTrue($this->policy->rebalanceStages($this->admin, $this->moduleB)); // unrestricted scope
    }

    public function test_rebalance_stages_role_mirror_denies_out_of_boundary_roles(): void
    {
        // supervisor / management / viewer must be denied BY THE MIRROR even
        // when project scope would allow them — the mirror is desync
        // protection for the financial-ceiling-adjacent weight operation.
        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);
        $management = $this->makeUserWithRole('management');
        $viewer = $this->makeUserWithRole('viewer');

        foreach ([$supervisor, $management, $viewer] as $actor) {
            $this->assertFalse($this->policy->rebalanceStages($actor, $this->moduleA), get_class($actor));
            $this->assertFalse($this->policy->rebalanceStages($actor, $this->moduleB));
        }

        // Contractor likewise denied regardless of scope.
        Role::findOrCreate('contractor', 'web');
        $contractor = User::factory()->create(['contractor_id' => $this->projectA->contractor_id]);
        $contractor->assignRole('contractor');
        $this->assertFalse($this->policy->rebalanceStages($contractor, $this->moduleA));
    }

    public function test_rebalance_stages_direct_grant_cannot_bypass_role_mirror(): void
    {
        // DEC-050: a direct permission grant must never open the role
        // boundary. No module permission exists, so the strongest available
        // cross-grant (an unrelated catalog permission) must not help a
        // mirror-excluded role.
        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);

        Permission::firstOrCreate(['name' => 'technical_approval', 'guard_name' => 'web']);
        $supervisor->givePermissionTo('technical_approval');
        $supervisor->refresh();

        $this->assertFalse($this->policy->rebalanceStages($supervisor, $this->moduleA));
    }

    // ------------------------------------------------------------------
    // Invariant separation: Policy must NOT replace Service Guards
    // ------------------------------------------------------------------

    public function test_policy_allows_but_service_guard_still_enforces_invariants(): void
    {
        // The policy happily allows an in-scope actor to rebalance stages…
        $this->assertTrue($this->policy->rebalanceStages($this->admin, $this->moduleA));

        // …but the SERVICE owns the invariant: a weight map whose resulting
        // sum breaks 100 is rejected by ModuleStageService regardless of the
        // policy decision. The policy never duplicates this rule.
        $stage = $this->moduleA->stages()->first();

        $this->expectException(\App\Domain\Exceptions\StageWeightException::class);

        app(\App\Domain\Services\ModuleStageService::class)->rebalance($this->moduleA, [
            $stage->id => 99,
        ], $this->admin);
    }
}
