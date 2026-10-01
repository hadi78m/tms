<?php

namespace Tests\Feature\V111;

use App\Domain\DTOs\AssignProjectSupervisorData;
use App\Domain\Services\ProjectMembershipService;
use App\Models\Module;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\Feature\V18\Concerns\V18Fixtures;
use Tests\TestCase;

/**
 * V1.11 — Module Phase · I-4 (DEC-061 · DEC-063): ModuleStagePolicy unit layer.
 *
 * Covers the policy object directly (same infrastructure-gate style as
 * ModulePolicyTest): scope resolution, role behavior, fail-closed relation
 * chain, auto-discovery and the no-permission / no-extra-ability surface.
 * HTTP wiring lives in ModuleStageScopeWiringTest.
 */
class ModuleStagePolicyTest extends TestCase
{
    use RefreshDatabase, V18Fixtures;

    private \App\Policies\ModuleStagePolicy $policy;

    private Project $projectA;

    private Project $projectB;

    private Module $moduleA;

    private Module $moduleB;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = app(\App\Policies\ModuleStagePolicy::class);

        $this->admin = $this->makeUserWithRole('admin');
        $this->projectA = $this->makeProject(1);
        $this->projectB = $this->makeProject(2);

        $this->moduleA = $this->makeModules($this->projectA, $this->admin, [100])->first();
        $this->moduleB = $this->makeModules($this->projectB, $this->admin, [100])->first();
    }

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

    private function stageOf(Module $module, string $code = 'analysis')
    {
        return $module->stages()->firstWhere('stage_code', $code);
    }

    // ------------------------------------------------------------------
    // Discovery + ability surface
    // ------------------------------------------------------------------

    public function test_module_stage_policy_is_discovered_by_the_gate(): void
    {
        // Auto-discovery: App\Models\ModuleStage -> App\Policies\ModuleStagePolicy
        // (no Gate::policy registration exists or is needed — DEC-050).
        $this->assertSame(
            \App\Policies\ModuleStagePolicy::class,
            Gate::getPolicyFor($this->stageOf($this->moduleA))::class
        );
    }

    public function test_policy_has_exactly_the_view_ability(): void
    {
        $methods = collect((new \ReflectionClass(\App\Policies\ModuleStagePolicy::class))
            ->getMethods(\ReflectionMethod::IS_PUBLIC))
            ->filter(fn ($m) => ! str_starts_with($m->getName(), '__'))
            ->map(fn ($m) => $m->getName())
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['view'], $methods);
    }

    public function test_policy_declares_no_forbidden_abilities(): void
    {
        $class = new \ReflectionClass(\App\Policies\ModuleStagePolicy::class);

        foreach (['update', 'delete', 'destroy', 'archive', 'restore', 'updateWeight', 'manage', 'rebalance', 'rebalanceStages'] as $ability) {
            $this->assertFalse($class->hasMethod($ability), $ability);
        }
    }

    public function test_policy_depends_on_no_permission(): void
    {
        $this->makeUserWithRole('viewer');
        $this->assertSame(0, Permission::where('name', 'like', '%stage%')->count());
        $this->assertSame(0, Permission::where('name', 'like', '%module%')->count());
    }

    // ------------------------------------------------------------------
    // Scope + role behavior
    // ------------------------------------------------------------------

    public function test_supervisor_with_membership_views_same_project_stage(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);

        $this->assertTrue($this->policy->view($supervisor, $this->stageOf($this->moduleA)));
    }

    public function test_supervisor_with_membership_denied_foreign_project_stage(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);

        $this->assertFalse($this->policy->view($supervisor, $this->stageOf($this->moduleB)));
    }

    public function test_supervisor_without_membership_is_denied(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');

        $this->assertFalse($this->policy->view($supervisor, $this->stageOf($this->moduleA)));
    }

    public function test_admin_is_unrestricted_across_projects(): void
    {
        $this->assertTrue($this->policy->view($this->admin, $this->stageOf($this->moduleB)));
    }

    public function test_pm_and_viewer_preserve_current_behavior(): void
    {
        foreach (['project_manager', 'viewer'] as $role) {
            $user = $this->makeUserWithRole($role);

            $this->assertTrue($this->policy->view($user, $this->stageOf($this->moduleB)), $role);
        }
    }

    public function test_employer_preserves_current_fail_open_behavior(): void
    {
        // DR-EMP-01 OPEN: no employer project identity exists, ProjectScopeService
        // returns null → allowed. Pinning current behavior, NOT a decision.
        $employer = $this->makeUserWithRole('employer');

        $this->assertTrue($this->policy->view($employer, $this->stageOf($this->moduleB)));
    }

    public function test_contractor_is_denied_by_the_role_boundary_not_the_policy(): void
    {
        // Contractor denial is Layer M (route middleware, stages.show is in the
        // read group the contractor never reaches) — the policy itself is pure
        // scope and contractor carries no scope restriction.
        $contractor = $this->makeUserWithRole('contractor');

        $this->assertTrue($this->policy->view($contractor, $this->stageOf($this->moduleA)));
    }

    // ------------------------------------------------------------------
    // Fail-closed relation chain
    // ------------------------------------------------------------------

    public function test_view_is_fail_closed_when_module_relation_is_missing(): void
    {
        $stage = $this->stageOf($this->moduleA);

        // Unset the resolved relation without touching the DB row.
        $stage->setRelation('module', null);

        $this->assertFalse($this->policy->view($this->admin, $stage));
    }

    public function test_view_is_fail_closed_when_project_relation_is_missing(): void
    {
        $stage = $this->stageOf($this->moduleA);

        $module = $this->moduleA;
        $module->setRelation('project', null);
        $stage->setRelation('module', $module);

        $this->assertFalse($this->policy->view($this->admin, $stage));
    }
}
