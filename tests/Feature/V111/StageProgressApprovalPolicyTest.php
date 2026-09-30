<?php

namespace Tests\Feature\V111;

use App\Domain\DTOs\AssignProjectSupervisorData;
use App\Domain\Services\ModuleService;
use App\Domain\Services\ProjectMembershipService;
use App\Models\Project;
use App\Models\StageProgressApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Feature\V18\Concerns\V18Fixtures;

/**
 * V1.11 — Approval Phase 2 · I-1 (DEC-056/058 · Closure Gate Finding A):
 * StageProgressApprovalPolicy unit-level behavior.
 *
 * Authorization boundary under test (NO permission — none invented):
 *   Role mirror + Project Scope via ProjectScopeService.
 *
 * Fixtures reuse V18Fixtures (makeProject / makeModules) — the established
 * Module/Stage factory path; no seeder is run.
 */
class StageProgressApprovalPolicyTest extends TestCase
{
    use RefreshDatabase, V18Fixtures;

    private \App\Policies\StageProgressApprovalPolicy $policy;

    private Project $projectA;

    private Project $projectB;

    private \App\Models\ModuleStage $stageA;

    private \App\Models\ModuleStage $stageB;

    protected function setUp(): void
    {
        parent::setUp();

        // Catalog slice for role creation only — no new permission anywhere.
        foreach (['technical_approval', 'final_approval'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        Role::findOrCreate('admin', 'web');

        $this->policy = app(\App\Policies\StageProgressApprovalPolicy::class);

        $this->projectA = $this->makeProject(1);
        $this->projectB = $this->makeProject(2);

        $creator = $this->makeUserWithRole('admin');
        $this->stageA = $this->makeModules($this->projectA, $creator, [100])->first()
            ->stages()->where('stage_code', 'analysis')->first();
        $this->stageB = $this->makeModules($this->projectB, $creator, [100])->first()
            ->stages()->where('stage_code', 'analysis')->first();
    }

    private function makeApproval(\App\Models\ModuleStage $stage, User $proposer): StageProgressApproval
    {
        return StageProgressApproval::create([
            'module_stage_id' => $stage->id,
            'module_id' => $stage->module_id,
            'proposed_amount' => 5,
            'status' => 'pending',
            'proposed_by' => $proposer->id,
        ]);
    }

    /** Unsaved StageProgressApproval lookahead carrying the target module (I-2 signature). */
    private function lookahead(\App\Models\ModuleStage $stage): StageProgressApproval
    {
        return new StageProgressApproval(['module_id' => $stage->module_id]);
    }

    // ------------------------------------------------------------------
    // propose — allowed role + same project / foreign project
    // ------------------------------------------------------------------

    public function test_supervisor_propose_allows_same_project(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');
        $admin = $this->makeUserWithRole('admin');

        app(ProjectMembershipService::class)->assignSupervisor(
            new AssignProjectSupervisorData(project_id: $this->projectA->id, user_id: $supervisor->id, assigned_by: $admin->id),
            $admin
        );

        $this->assertTrue($this->policy->propose($supervisor, $this->lookahead($this->stageA)));
        $this->assertFalse($this->policy->propose($supervisor, $this->lookahead($this->stageB)));
    }

    public function test_admin_propose_is_scope_unrestricted(): void
    {
        $admin = $this->makeUserWithRole('admin');

        $this->assertTrue($this->policy->propose($admin, $this->lookahead($this->stageA)));
        $this->assertTrue($this->policy->propose($admin, $this->lookahead($this->stageB)));
    }

    // ------------------------------------------------------------------
    // adjust / decide — scope resolved through denormalized module_id
    // ------------------------------------------------------------------

    public function test_adjust_is_scope_denied_for_foreign_project(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');
        $admin = $this->makeUserWithRole('admin');

        app(ProjectMembershipService::class)->assignSupervisor(
            new AssignProjectSupervisorData(project_id: $this->projectA->id, user_id: $supervisor->id, assigned_by: $admin->id),
            $admin
        );

        $foreign = $this->makeApproval($this->stageB, $admin);

        $this->assertFalse($this->policy->adjust($supervisor, $foreign));
    }

    public function test_decide_by_supervisor_allows_own_project(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');
        $admin = $this->makeUserWithRole('admin');

        app(ProjectMembershipService::class)->assignSupervisor(
            new AssignProjectSupervisorData(project_id: $this->projectA->id, user_id: $supervisor->id, assigned_by: $admin->id),
            $admin
        );

        $own = $this->makeApproval($this->stageA, $admin);
        $foreign = $this->makeApproval($this->stageB, $admin);

        $this->assertTrue($this->policy->decide($supervisor, $own));
        $this->assertFalse($this->policy->decide($supervisor, $foreign));
    }

    public function test_decide_denies_non_supervisor_roles(): void
    {
        $employer = $this->makeUserWithRole('employer');
        $approval = $this->makeApproval($this->stageA, $employer);

        $this->assertFalse($this->policy->decide($employer, $approval));
    }

    // ------------------------------------------------------------------
    // Role boundary + direct grant semantics (Finding A)
    // ------------------------------------------------------------------

    public function test_disallowed_roles_are_denied_despite_any_grant(): void
    {
        // Contractor + management + viewer are outside the propose role-mw
        // mirror; even a direct grant of an unrelated catalog permission
        // must NOT open the role boundary (Permission ≠ Role).
        foreach (['contractor', 'management', 'viewer'] as $index => $role) {
            $user = $this->makeUserWithRole($role, $index === 0 ? $this->projectA->contractor_id : null);
            $user->givePermissionTo('technical_approval');
            $user->refresh();

            $this->assertFalse($this->policy->propose($user, $this->lookahead($this->stageA)), $role);
            $this->assertFalse($this->policy->adjust($user, $this->makeApproval($this->stageA, $user)), $role);
            $this->assertFalse($this->policy->decide($user, $this->makeApproval($this->stageA, $user)), $role);
        }
    }

    public function test_direct_permission_grant_does_not_create_a_role_boundary_bypass(): void
    {
        // Employer holds final_approval (role-granted in prod) — a stage-
        // progress decision is still role-mirrored to supervisor only.
        $employer = $this->makeUserWithRole('employer');
        $employer->givePermissionTo('final_approval');
        $employer->refresh();

        $this->assertFalse($this->policy->decide($employer, $this->makeApproval($this->stageA, $employer)));
    }

    public function test_no_new_permission_was_created_for_stage_progress(): void
    {
        // Architecture pin (Closure Gate Finding A / DEC-059): the catalog
        // must contain NO stage-progress permission.
        $names = Permission::query()->pluck('name')->all();

        $this->assertNotContains('propose stage progress', $names);
        $this->assertNotContains('adjust stage progress', $names);
        $this->assertNotContains('decide stage progress', $names);
        $this->assertNotContains('manage stage progress', $names);
    }

    /**
     * I-4 verification (STEP 12): a supervisor with NO active membership is
     * denied on every ability — no automatic membership is created and the
     * empty scope set fail-closes (DEC-048/OD-6-a). Policy-level cell; the
     * HTTP foreign-project matrix belongs to I-6.
     */
    public function test_supervisor_without_membership_is_denied_on_all_abilities(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor'); // no membership anywhere

        $this->assertFalse($this->policy->propose($supervisor, $this->lookahead($this->stageA)));
        $this->assertFalse($this->policy->adjust($supervisor, $this->makeApproval($this->stageA, $supervisor)));
        $this->assertFalse($this->policy->decide($supervisor, $this->makeApproval($this->stageA, $supervisor)));
    }
}
