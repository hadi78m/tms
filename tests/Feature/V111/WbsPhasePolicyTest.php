<?php

namespace Tests\Feature\V111;

use App\Domain\DTOs\AssignProjectSupervisorData;
use App\Domain\Services\ProjectMembershipService;
use App\Models\Project;
use App\Models\User;
use App\Models\WbsPhase;
use App\Policies\WbsPhasePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Feature\V19\Concerns\V19Fixtures;
use Tests\TestCase;

/**
 * V1.11 — WBS Phase · I-1 (WBS-01..05): WbsPhasePolicy unit matrix.
 *
 * The policy is exercised DIRECTLY (no HTTP) so each ability's contract is
 * pinned independently of the route middleware, which remains the separate,
 * authoritative role boundary.
 *
 * Pinned layers:
 *   Project Scope  = ProjectScopeService (canonical, NOT duplicated)
 *   Role boundary  = route middleware (unchanged); decide() additionally
 *                    mirrors `role:supervisor` (WBS-03 · WBS-03b)
 *   Permissions    = NONE exist for WBS and NONE bypass the policy
 */
class WbsPhasePolicyTest extends TestCase
{
    use RefreshDatabase, V19Fixtures;

    private WbsPhasePolicy $policy;

    private Project $projectA;

    private Project $projectB;

    private WbsPhase $phaseA;

    private WbsPhase $phaseB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = app(WbsPhasePolicy::class);
        $admin = $this->makeUserWithRole('admin');
        $this->projectA = $this->makeProject(1);
        $this->projectB = $this->makeProject(2);
        $this->phaseA = $this->makeWbsPhase($this->projectA, $admin, 'Phase A');
        $this->phaseB = $this->makeWbsPhase($this->projectB, $admin, 'Phase B');
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    private function scopeSupervisorTo(User $user, Project $project): void
    {
        app(ProjectMembershipService::class)->assignSupervisor(
            new AssignProjectSupervisorData(
                project_id: $project->id,
                user_id: $user->id,
                assigned_by: $user->id
            ),
            $user
        );
    }

    /** Supervisor with an ACTIVE membership in project A only. */
    private function supervisorA(): User
    {
        $user = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorTo($user, $this->projectA);

        return $user;
    }

    /** Supervisor whose only A-membership has ENDED (history retained). */
    private function supervisorEndedA(): User
    {
        $user = $this->supervisorA();
        app(ProjectMembershipService::class)->endActiveSupervisor($this->projectA, $user);

        return $user->fresh();
    }

    // ==================================================================
    // view — scope via WbsPhase -> Project (fail-closed)
    // ==================================================================

    public function test_view_allows_supervisor_with_active_membership(): void
    {
        $this->assertTrue($this->policy->view($this->supervisorA(), $this->phaseA));
    }

    public function test_view_denies_foreign_project_supervisor(): void
    {
        $this->assertFalse($this->policy->view($this->supervisorA(), $this->phaseB));
    }

    public function test_view_denies_supervisor_without_membership(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');

        // Empty scope denies even the "own" project — not just foreign ones.
        $this->assertFalse($this->policy->view($supervisor, $this->phaseA));
        $this->assertFalse($this->policy->view($supervisor, $this->phaseB));
    }

    public function test_view_denies_ended_membership_supervisor(): void
    {
        $this->assertFalse($this->policy->view($this->supervisorEndedA(), $this->phaseA));
    }

    public function test_view_fails_closed_when_project_relation_is_missing(): void
    {
        $orphan = $this->phaseA->replicate(['id']);
        $orphan->setRelation('project', null);

        $this->assertFalse($this->policy->view($this->supervisorA(), $orphan));
        $this->assertFalse($this->policy->view($this->makeUserWithRole('admin'), $orphan));
    }

    // ==================================================================
    // view — role semantics preserved via ProjectScopeService (untouched)
    // ==================================================================

    public function test_view_admin_and_management_are_unrestricted(): void
    {
        $this->assertTrue($this->policy->view($this->makeUserWithRole('admin'), $this->phaseB));
        $this->assertTrue($this->policy->view($this->makeUserWithRole('management'), $this->phaseB));
    }

    public function test_view_pm_semantics_preserved(): void
    {
        // Q1/DEC-048: PM derives no project scope — current behavior kept.
        $this->assertTrue($this->policy->view($this->makeUserWithRole('project_manager'), $this->phaseB));
    }

    public function test_view_viewer_semantics_preserved(): void
    {
        $this->assertTrue($this->policy->view($this->makeUserWithRole('viewer'), $this->phaseB));
    }

    public function test_view_employer_semantics_preserved_dr_emp_01_open(): void
    {
        // DR-EMP-01 stays OPEN: no employer identity is invented; the
        // fail-open current behavior is pinned, not endorsed.
        $this->assertTrue($this->policy->view($this->makeUserWithRole('employer'), $this->phaseB));
    }

    // ==================================================================
    // create — scope over the RESOLVED Project (request-body target)
    // ==================================================================

    public function test_create_allows_scoped_supervisor_for_own_project(): void
    {
        $this->assertTrue($this->policy->create($this->supervisorA(), $this->projectA));
    }

    public function test_create_denies_scoped_supervisor_for_foreign_project(): void
    {
        $this->assertFalse($this->policy->create($this->supervisorA(), $this->projectB));
        $this->assertFalse($this->policy->create($this->makeUserWithRole('supervisor'), $this->projectA));
    }

    public function test_create_admin_unrestricted(): void
    {
        $this->assertTrue($this->policy->create($this->makeUserWithRole('admin'), $this->projectB));
    }

    // ==================================================================
    // checklist abilities — parent WbsPhase scope (WBS-05)
    // ==================================================================

    public function test_checklist_abilities_follow_parent_phase_scope(): void
    {
        $supervisor = $this->supervisorA();

        $this->assertTrue($this->policy->addChecklistItem($supervisor, $this->phaseA));
        $this->assertTrue($this->policy->completeChecklistItem($supervisor, $this->phaseA));
        $this->assertTrue($this->policy->reopenChecklistItem($supervisor, $this->phaseA));

        $this->assertFalse($this->policy->addChecklistItem($supervisor, $this->phaseB));
        $this->assertFalse($this->policy->completeChecklistItem($supervisor, $this->phaseB));
        $this->assertFalse($this->policy->reopenChecklistItem($supervisor, $this->phaseB));
    }

    public function test_checklist_abilities_fail_closed_without_membership_or_ended_membership(): void
    {
        $noMembership = $this->makeUserWithRole('supervisor');

        $this->assertFalse($this->policy->addChecklistItem($noMembership, $this->phaseA));
        $this->assertFalse($this->policy->completeChecklistItem($noMembership, $this->phaseA));
        $this->assertFalse($this->policy->reopenChecklistItem($noMembership, $this->phaseA));

        $ended = $this->supervisorEndedA();
        $this->assertFalse($this->policy->addChecklistItem($ended, $this->phaseA));
    }

    // ==================================================================
    // decide — role mirror (WBS-03/03b) + active membership scope
    // ==================================================================

    public function test_decide_allows_supervisor_with_active_membership_in_own_project(): void
    {
        $this->assertTrue($this->policy->decide($this->supervisorA(), $this->phaseA));
    }

    public function test_decide_denies_foreign_project_supervisor(): void
    {
        $this->assertFalse($this->policy->decide($this->supervisorA(), $this->phaseB));
    }

    public function test_decide_denies_supervisor_without_membership(): void
    {
        $this->assertFalse($this->policy->decide($this->makeUserWithRole('supervisor'), $this->phaseA));
    }

    public function test_decide_denies_ended_membership_supervisor(): void
    {
        $this->assertFalse($this->policy->decide($this->supervisorEndedA(), $this->phaseA));
    }

    public function test_decide_denies_admin_despite_unrestricted_scope_wbs_03b(): void
    {
        $this->assertFalse($this->policy->decide($this->makeUserWithRole('admin'), $this->phaseA));
        $this->assertFalse($this->policy->decide($this->makeUserWithRole('management'), $this->phaseA));
    }

    public function test_decide_denies_other_roles_even_in_scope(): void
    {
        $pm = $this->makeUserWithRole('project_manager');
        $this->scopeSupervisorTo($pm, $this->projectA);

        $this->assertFalse($this->policy->decide($pm, $this->phaseA));
        $this->assertFalse($this->policy->decide($this->makeUserWithRole('employer'), $this->phaseA));
        $this->assertFalse($this->policy->decide($this->makeUserWithRole('viewer'), $this->phaseA));
        $this->assertFalse($this->policy->decide($this->makeUserWithRole('contractor'), $this->phaseA));
    }

    // ==================================================================
    // No permission-based bypass
    // ==================================================================

    public function test_direct_permission_grant_cannot_bypass_policy(): void
    {
        // A direct grant of an unrelated catalog permission must never widen
        // Project Scope or the decide role mirror (no new permission is
        // introduced by I-1 — none is invented).
        $user = $this->makeUserWithRole('supervisor');
        $permission = Permission::findOrCreate('manage projects', 'web');
        $user->givePermissionTo($permission);

        $this->assertFalse($this->policy->view($user, $this->phaseA));
        $this->assertFalse($this->policy->decide($user, $this->phaseA));
        $this->assertFalse($this->policy->addChecklistItem($user, $this->phaseA));
    }

    public function test_membership_without_supervisor_role_cannot_decide(): void
    {
        // WBS-03: the decide role boundary is independent of membership —
        // a non-supervisor with an active supervisor-typed membership row
        // (contractor role) is still denied by the role mirror.
        $contractor = $this->makeUserWithRole('contractor');
        $this->scopeSupervisorTo($contractor, $this->projectA);

        $this->assertFalse($this->policy->decide($contractor, $this->phaseA));
    }

    // ==================================================================
    // Approved ability surface — nothing invented
    // ==================================================================

    public function test_policy_exposes_exactly_the_approved_abilities(): void
    {
        $approved = ['view', 'create', 'addChecklistItem', 'completeChecklistItem', 'reopenChecklistItem', 'decide'];

        foreach ($approved as $ability) {
            $this->assertTrue(method_exists(WbsPhasePolicy::class, $ability), "missing ability: {$ability}");
        }

        foreach (['update', 'delete', 'archive', 'restore', 'rebalance', 'decideFor'] as $invented) {
            $this->assertFalse(method_exists(WbsPhasePolicy::class, $invented), "invented ability: {$invented}");
        }
    }

    public function test_role_role_helper_creates_web_guard_roles(): void
    {
        // Guards the fixture contract used across the WBS matrix.
        $this->role('supervisor');
        $this->assertTrue(Role::where('name', 'supervisor')->where('guard_name', 'web')->exists());
    }
}
