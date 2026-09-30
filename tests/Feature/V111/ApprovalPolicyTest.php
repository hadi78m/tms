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
 * V1.11 — Approval Phase 2 · I-1 (DEC-056/058/059 · Design Gate §10/§14):
 * ApprovalPolicy unit-level behavior (policy infrastructure only — the
 * controller is NOT wired in I-1, so assertions run against the policy
 * resolved from the container, exactly as TaskPolicyTest does).
 *
 * Matrix covered (Design Gate §10 — Technical/Final rows):
 *   admin + technical/final + same project → ALLOW
 *   supervisor + technical + same project  → ALLOW
 *   direct grant WITHOUT required role     → DENY (Permission ≠ Role)
 *   PM / contractor / management / viewer  → DENY (role boundary)
 *   foreign project (scope layers)         → DENY
 *   Employer final-approval scope cells    → EXPECTED OPEN (DR-EMP-01) —
 *     asserted as the deferred fall-through, never faked.
 */
class ApprovalPolicyTest extends TestCase
{
    use RefreshDatabase;

    private \App\Policies\ApprovalPolicy $policy;

    private Project $projectA;

    private Project $projectB;

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

        $this->policy = app(\App\Policies\ApprovalPolicy::class);

        $this->projectA = Project::factory()->create(['name' => 'Project A']);
        $this->projectB = Project::factory()->create(['name' => 'Project B']);
    }

    private function makeTask(Project $project): Task
    {
        return Task::factory()->create([
            'project_id' => $project->id,
            'contractor_id' => $project->contractor_id,
            'contract_id' => $project->contract_id,
            'status' => 'under_review',
        ]);
    }

    /** Unsaved Approval lookahead carrying the target task (I-2 signature). */
    private function lookahead(Task $task): \App\Models\Approval
    {
        return new \App\Models\Approval(['task_id' => $task->id]);
    }

    private function makeUserWithRole(string $role, ?int $contractorId = null): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create(['contractor_id' => $contractorId]);
        $user->assignRole($role);

        return $user->refresh();
    }

    // ------------------------------------------------------------------
    // Role + Permission + same project
    // ------------------------------------------------------------------

    public function test_admin_with_technical_approval_allows_same_project(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $task = $this->makeTask($this->projectA);

        $this->assertTrue($this->policy->createTechnical($admin, $this->lookahead($task)));
    }

    public function test_supervisor_with_technical_approval_allows_same_project(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');
        $supervisor->givePermissionTo('technical_approval');
        $supervisor->refresh();

        // Supervisor scope = projects with an ACTIVE membership only.
        $admin = $this->makeUserWithRole('admin');
        app(\App\Domain\Services\ProjectMembershipService::class)->assignSupervisor(
            new \App\Domain\DTOs\AssignProjectSupervisorData(
                project_id: $this->projectA->id,
                user_id: $supervisor->id,
                assigned_by: $admin->id
            ),
            $admin
        );

        $task = $this->makeTask($this->projectA);

        $this->assertTrue($this->policy->createTechnical($supervisor, $this->lookahead($task)));
    }

    public function test_admin_with_final_approval_allows_same_project(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $task = $this->makeTask($this->projectA);

        $this->assertTrue($this->policy->createFinal($admin, $this->lookahead($task)));
    }

    // ------------------------------------------------------------------
    // Employer — role + permission pass; scope = deferred fall-through
    // (DR-EMP-01 OPEN: asserted as the OD-2 deferred behavior, never faked)
    // ------------------------------------------------------------------

    public function test_employer_with_final_approval_resolves_through_deferred_branch(): void
    {
        $employer = $this->makeUserWithRole('employer');
        // In production the ROLE holds final_approval (seeder); mirror it here.
        $employer->givePermissionTo('final_approval');
        $employer->refresh();
        $task = $this->makeTask($this->projectA);

        // employerBranch() returns null (OD-2 deferred) → scope fall-through
        // preserved — the role+permission layers still gate the operation.
        $this->assertTrue($this->policy->createFinal($employer, $this->lookahead($task)));

        // The dependency is explicitly OPEN — pin it so any accidental
        // employerBranch() change in a later gate is a visible diff.
        $this->assertNull(
            app(\App\Domain\Rules\ProjectScopeService::class)->accessibleProjectIds($employer)
        );
    }

    // ------------------------------------------------------------------
    // Direct grant WITHOUT required role → DENY (Permission ≠ Role)
    // ------------------------------------------------------------------

    public function test_direct_final_approval_grant_without_employer_role_is_denied(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');
        $supervisor->givePermissionTo('final_approval');
        $supervisor->refresh();
        $this->assertTrue($supervisor->can('final_approval'));

        $this->assertFalse($this->policy->createFinal($supervisor, $this->lookahead($this->makeTask($this->projectA))));
    }

    public function test_direct_technical_approval_grant_without_supervisor_role_is_denied(): void
    {
        $viewer = $this->makeUserWithRole('viewer');
        $viewer->givePermissionTo('technical_approval');
        $viewer->refresh();
        $this->assertTrue($viewer->can('technical_approval'));

        $this->assertFalse($this->policy->createTechnical($viewer, $this->lookahead($this->makeTask($this->projectA))));
    }

    // ------------------------------------------------------------------
    // Role boundary — PM / contractor / management / viewer → DENY
    // ------------------------------------------------------------------

    public function test_pm_is_denied_for_both_tiers(): void
    {
        $pm = $this->makeUserWithRole('project_manager');
        $pm->givePermissionTo(['technical_approval', 'final_approval']);
        $pm->refresh();

        $task = $this->makeTask($this->projectA);            $this->assertFalse($this->policy->createTechnical($pm, $this->lookahead($task)));
            $this->assertFalse($this->policy->createFinal($pm, $this->lookahead($task)));
    }

    public function test_contractor_is_denied_for_both_tiers(): void
    {
        $contractor = $this->makeUserWithRole('contractor', $this->projectA->contractor_id);
        $contractor->givePermissionTo(['technical_approval', 'final_approval']);
        $contractor->refresh();

        $task = $this->makeTask($this->projectA);            $this->assertFalse($this->policy->createTechnical($contractor, $this->lookahead($task)));
            $this->assertFalse($this->policy->createFinal($contractor, $this->lookahead($task)));
    }

    public function test_management_and_viewer_are_denied_for_both_tiers(): void
    {
        foreach (['management', 'viewer'] as $role) {
            $user = $this->makeUserWithRole($role);
            $user->givePermissionTo(['technical_approval', 'final_approval']);
            $user->refresh();

            $task = $this->makeTask($this->projectA);

            $this->assertFalse($this->policy->createTechnical($user, $this->lookahead($task)), $role);
            $this->assertFalse($this->policy->createFinal($user, $this->lookahead($task)), $role);
        }
    }

    // ------------------------------------------------------------------
    // Project Scope — foreign project → DENY (scope layer)
    // ------------------------------------------------------------------

    public function test_supervisor_technical_is_denied_on_foreign_project(): void
    {
        // Supervisor scoped to Project A via active membership.
        $supervisor = $this->makeUserWithRole('supervisor');
        $supervisor->givePermissionTo('technical_approval');
        $supervisor->refresh();
        $admin = $this->makeUserWithRole('admin');

        app(\App\Domain\Services\ProjectMembershipService::class)->assignSupervisor(
            new \App\Domain\DTOs\AssignProjectSupervisorData(
                project_id: $this->projectA->id,
                user_id: $supervisor->id,
                assigned_by: $admin->id
            ),
            $admin
        );

        $this->assertTrue($this->policy->createTechnical($supervisor, $this->lookahead($this->makeTask($this->projectA))));
        $this->assertFalse($this->policy->createTechnical($supervisor, $this->lookahead($this->makeTask($this->projectB))));
    }

    public function test_admin_is_scope_unrestricted_for_both_tiers(): void
    {
        $admin = $this->makeUserWithRole('admin');

        $this->assertTrue($this->policy->createTechnical($admin, $this->lookahead($this->makeTask($this->projectB))));
        $this->assertTrue($this->policy->createFinal($admin, $this->lookahead($this->makeTask($this->projectB))));
    }
}
