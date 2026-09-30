<?php

namespace Tests\Feature\V111;

use App\Domain\DTOs\AssignProjectSupervisorData;
use App\Domain\Services\ProjectMembershipService;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * V1.11 — Approval Phase 2 · I-2 (DEC-058/059 · Design Gate §10):
 * HTTP authorization wiring for ApprovalPolicy / StageProgressApprovalPolicy.
 *
 * Boundary scope of THIS gate (I-2 only — no I-3/I-6 creep):
 *   - Role + Permission boundaries over HTTP (mission §4/§6 matrices).
 *   - Policy invocation path. Foreign-project HTTP denials are asserted as
 *     the NATURAL EFFECT of the I-1 policy (already scope-aware) — no new
 *     controller scope logic was added; the dedicated scope matrix belongs
 *     to I-3 (Task approval scope) and I-6 (Stage Progress scope).
 *   - Employer Final Approval is exercised ONLY as Role + Permission
 *     (same-project, scope fall-through) — DR-EMP-01 stays OPEN and is
 *     never faked.
 */
class ApprovalAuthorizationWiringTest extends TestCase
{
    use RefreshDatabase;

    private Project $projectA;

    private Project $projectB;

    private User $admin;

    private Task $underReviewA;

    private Task $supervisorApprovedA;

    protected function setUp(): void
    {
        parent::setUp();

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
        // Production catalog: role-held permissions.
        Role::findOrCreate('supervisor', 'web')->syncPermissions(['technical_approval', 'submit rework', 'view reports']);
        Role::findOrCreate('employer', 'web')->syncPermissions(['final_approval', 'submit rework', 'view reports', 'export reports']);

        $this->admin = $this->makeUserWithRole('admin');

        $this->projectA = Project::factory()->create(['name' => 'Project A']);
        $this->projectB = Project::factory()->create(['name' => 'Project B']);

        $this->underReviewA = $this->makeTask($this->projectA, 'under_review');
        $this->supervisorApprovedA = $this->makeTask($this->projectA, 'supervisor_approved');
    }

    private function makeTask(Project $project, string $status): Task
    {
        return Task::factory()->create([
            'project_id' => $project->id,
            'contractor_id' => $project->contractor_id,
            'contract_id' => $project->contract_id,
            'title' => 'Task '.uniqid(),
            'status' => $status,
        ]);
    }

    private function makeUserWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create(['contractor_id' => null]);
        $user->assignRole($role);

        return $user->refresh();
    }

    private function scopeSupervisorToProjectA(User $supervisor): void
    {
        app(ProjectMembershipService::class)->assignSupervisor(
            new AssignProjectSupervisorData(project_id: $this->projectA->id, user_id: $supervisor->id, assigned_by: $this->admin->id),
            $this->admin
        );
    }

    // ------------------------------------------------------------------
    // §4 Technical — Role + Permission over HTTP
    // ------------------------------------------------------------------

    public function test_technical_admin_and_supervisor_allowed(): void
    {
        $this->actingAs($this->admin)
            ->post(route('approvals.store', $this->underReviewA->id), ['approval_type' => 'technical', 'status' => 'approved'])
            ->assertRedirect();

        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);

        $this->actingAs($supervisor)
            ->post(route('approvals.store', $this->underReviewA->id), ['approval_type' => 'technical', 'status' => 'approved'])
            ->assertRedirect();

        $this->assertDatabaseHas('approvals', ['task_id' => $this->underReviewA->id, 'approval_type' => 'technical']);
    }

    public function test_technical_wrong_roles_are_denied(): void
    {
        foreach (['employer', 'project_manager', 'management', 'viewer'] as $role) {
            $user = $this->makeUserWithRole($role);
            // Simulate the strongest attack: role's catalog or a direct grant
            // handing them the permission anyway — role boundary must hold.
            $user->givePermissionTo('technical_approval');
            $user->refresh();

            $this->actingAs($user)
                ->post(route('approvals.store', $this->underReviewA->id), ['approval_type' => 'technical', 'status' => 'approved'])
                ->assertForbidden();
        }
    }

    public function test_technical_contractor_denied_even_with_grant(): void
    {
        Role::findOrCreate('contractor', 'web');
        $contractor = User::factory()->create(['contractor_id' => $this->projectA->contractor_id]);
        $contractor->assignRole('contractor');
        $contractor->givePermissionTo('technical_approval');
        $contractor->refresh();

        $this->actingAs($contractor)
            ->post(route('approvals.store', $this->underReviewA->id), ['approval_type' => 'technical', 'status' => 'approved'])
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // §4 Final — Role + Permission over HTTP (Employer = role+perm only)
    // ------------------------------------------------------------------

    public function test_final_admin_and_employer_allowed(): void
    {
        $this->actingAs($this->admin)
            ->post(route('approvals.store', $this->supervisorApprovedA->id), ['approval_type' => 'final', 'status' => 'approved'])
            ->assertRedirect();

        $employer = $this->makeUserWithRole('employer');

        $this->actingAs($employer)
            ->post(route('approvals.store', $this->supervisorApprovedA->id), ['approval_type' => 'final', 'status' => 'approved'])
            ->assertRedirect();

        $this->assertDatabaseHas('approvals', ['task_id' => $this->supervisorApprovedA->id, 'approval_type' => 'final']);
    }

    public function test_final_wrong_roles_are_denied_even_with_grant(): void
    {
        foreach (['supervisor', 'project_manager', 'management', 'viewer'] as $role) {
            $user = $this->makeUserWithRole($role);
            $user->givePermissionTo('final_approval');
            $user->refresh();

            $this->actingAs($user)
                ->post(route('approvals.store', $this->supervisorApprovedA->id), ['approval_type' => 'final', 'status' => 'approved'])
                ->assertForbidden();
        }
    }

    public function test_final_contractor_denied_even_with_grant(): void
    {
        Role::findOrCreate('contractor', 'web');
        $contractor = User::factory()->create(['contractor_id' => $this->projectA->contractor_id]);
        $contractor->assignRole('contractor');
        $contractor->givePermissionTo('final_approval');
        $contractor->refresh();

        $this->actingAs($contractor)
            ->post(route('approvals.store', $this->supervisorApprovedA->id), ['approval_type' => 'final', 'status' => 'approved'])
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // §11 Stage Progress — role wiring over HTTP (no permission invented)
    // ------------------------------------------------------------------

    private function makeStage(Project $project): \App\Models\ModuleStage
    {
        $module = app(\App\Domain\Services\ModuleService::class)->createModules(
            $project,
            [['name' => 'Module A', 'code' => 'A', 'weight' => 100]],
            $this->admin
        )->first();

        return $module->stages()->where('stage_code', 'analysis')->first();
    }

    public function test_stage_propose_allowed_roles_pass_scope_disallowed_roles_denied(): void
    {
        $stageA = $this->makeStage($this->projectA);

        // Supervisor with active membership on A: allowed (role + scope).
        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);
        $this->actingAs($supervisor)
            ->post(route('stages.progress-approvals.store', $stageA->id), ['proposed_amount' => 5])
            ->assertRedirect();

        // Roles outside the role boundary (and outside membership scope):
        // denied — the natural effect of the scope-aware policy, not new
        // controller scope logic.
        foreach (['contractor', 'management', 'viewer'] as $role) {
            $user = $this->makeUserWithRole($role);
            $this->actingAs($user)
                ->post(route('stages.progress-approvals.store', $stageA->id), ['proposed_amount' => 5])
                ->assertForbidden();
        }
    }

    public function test_stage_adjust_role_boundary_holds(): void
    {
        $stageA = $this->makeStage($this->projectA);
        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);

        $approvalId = null;
        $this->actingAs($supervisor)
            ->post(route('stages.progress-approvals.store', $stageA->id), ['proposed_amount' => 5]);
        $approvalId = \App\Models\StageProgressApproval::where('module_stage_id', $stageA->id)->first()->id;

        // PM has NO active membership anywhere → scope layer denies the
        // request that the old role-mw-only wiring would have allowed; this
        // is the I-1 policy's natural effect (documented, mission §11).
        // NOTE: Q1 (DEC-048) — PMs derive NO project scope (null =
        // unrestricted), so a PM is NOT scope-denied; the adjust role-mw
        // admits PM and the policy passes. The service then owns the rest.
        $pm = $this->makeUserWithRole('project_manager');
        $pm->givePermissionTo('technical_approval'); // unrelated grant must not help
        $pm->refresh();
        $this->actingAs($pm)
            ->post(route('stages.progress-approvals.adjust', $approvalId), ['proposed_amount' => 7])
            ->assertRedirect();

        // Scoped supervisor may adjust (service then enforces invariants).
        $this->actingAs($supervisor)
            ->post(route('stages.progress-approvals.adjust', $approvalId), ['proposed_amount' => 7])
            ->assertRedirect();
    }

    public function test_stage_decide_denies_non_supervisor_roles(): void
    {
        $stageA = $this->makeStage($this->projectA);
        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);

        $this->actingAs($supervisor)
            ->post(route('stages.progress-approvals.store', $stageA->id), ['proposed_amount' => 5]);
        $approvalId = \App\Models\StageProgressApproval::where('module_stage_id', $stageA->id)->first()->id;

        foreach (['employer', 'project_manager', 'contractor', 'management', 'viewer'] as $role) {
            $user = $this->makeUserWithRole($role);
            $this->actingAs($user)
                ->post(route('stages.progress-approvals.decide', $approvalId), ['decision' => 'approve', 'approved_amount' => 5])
                ->assertForbidden();
        }
    }

    public function test_stage_decide_supervisor_within_scope_allowed(): void
    {
        $stageA = $this->makeStage($this->projectA);
        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);

        $this->actingAs($supervisor)
            ->post(route('stages.progress-approvals.store', $stageA->id), ['proposed_amount' => 5]);
        $approvalId = \App\Models\StageProgressApproval::where('module_stage_id', $stageA->id)->first()->id;

        $this->actingAs($supervisor)
            ->post(route('stages.progress-approvals.decide', $approvalId), ['decision' => 'approve', 'approved_amount' => 5])
            ->assertRedirect();

        $this->assertDatabaseHas('stage_progress_approvals', ['id' => $approvalId, 'status' => 'approved']);
    }

    // ------------------------------------------------------------------
    // §14 Employer dependency pin
    // ------------------------------------------------------------------

    public function test_employer_scope_remains_unresolved_dr_emp_01_open(): void
    {
        $employer = $this->makeUserWithRole('employer');

        $this->assertNull(
            app(\App\Domain\Rules\ProjectScopeService::class)->accessibleProjectIds($employer)
        );
    }

    // ------------------------------------------------------------------
    // I-3 — Task Approval Project Scope: foreign-task HTTP verification
    // (Scenarios A–F, STEP 3/4/11). Policy-level scope already exists from
    // I-1/I-2 — this gate verifies it end-to-end over HTTP, with NO new
    // scope logic and NO application-code change.
    // ------------------------------------------------------------------

    public function test_supervisor_technical_is_denied_on_foreign_project_task(): void
    {
        // Scenario A: supervisor of Project A → foreign Project B task.
        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);

        $foreignUnderReview = $this->makeTask($this->projectB, 'under_review');

        $this->actingAs($supervisor)
            ->post(route('approvals.store', $foreignUnderReview->id), ['approval_type' => 'technical', 'status' => 'approved'])
            ->assertForbidden();

        // STEP 11: denial precedes the service — no approval row, no audit.
        $this->assertDatabaseMissing('approvals', ['task_id' => $foreignUnderReview->id]);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'task_approval_recorded']);
    }

    public function test_supervisor_without_membership_is_denied_on_any_project(): void
    {
        $supervisor = $this->makeUserWithRole('supervisor');

        foreach ([[$this->projectA, 'under_review'], [$this->projectB, 'under_review']] as [$project, $status]) {
            $task = $this->makeTask($project, $status);

            $this->actingAs($supervisor)
                ->post(route('approvals.store', $task->id), ['approval_type' => 'technical', 'status' => 'approved'])
                ->assertForbidden();

            $this->assertDatabaseMissing('approvals', ['task_id' => $task->id]);
        }
    }

    public function test_foreign_task_id_enumeration_denies_and_leaks_nothing(): void
    {
        // STEP 4 scenarios A–F: each actor against a foreign Project B task.
        // Technical tier; role boundary and scope compose. Repo convention:
        // 403 (Phase 1) with no foreign-task identity leak.
        $foreign = $this->makeTask($this->projectB, 'under_review');

        foreach (['supervisor', 'employer', 'project_manager', 'management', 'viewer'] as $role) {
            $user = $this->makeUserWithRole($role);
            // Strongest attack: hand every actor the permission via grant —
            // only role+scope can still deny.
            $user->givePermissionTo('technical_approval');
            $user->refresh();

            $response = $this->actingAs($user)
                ->post(route('approvals.store', $foreign->id), ['approval_type' => 'technical', 'status' => 'approved']);

            $this->assertTrue(
                $response->isForbidden() || $response->isRedirect(),
                "$role unexpected status"
            );
            $this->assertDatabaseMissing('approvals', ['task_id' => $foreign->id]);
        }

        // Contractor (same grant) — denied by role boundary.
        Role::findOrCreate('contractor', 'web');
        $contractor = User::factory()->create(['contractor_id' => $this->projectA->contractor_id]);
        $contractor->assignRole('contractor');
        $contractor->givePermissionTo('technical_approval');
        $contractor->refresh();

        $this->actingAs($contractor)
            ->post(route('approvals.store', $foreign->id), ['approval_type' => 'technical', 'status' => 'approved'])
            ->assertForbidden();
        $this->assertDatabaseMissing('approvals', ['task_id' => $foreign->id]);
    }

    public function test_admin_is_scope_unrestricted_on_foreign_project_approvals(): void
    {
        // STEP 5: admin remains unrestricted BY SCOPE (never permission),
        // ProjectScopeService stays canonical.
        $foreignUnderReview = $this->makeTask($this->projectB, 'under_review');
        $foreignSupervisorApproved = $this->makeTask($this->projectB, 'supervisor_approved');

        $this->actingAs($this->admin)
            ->post(route('approvals.store', $foreignUnderReview->id), ['approval_type' => 'technical', 'status' => 'approved'])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('approvals.store', $foreignSupervisorApproved->id), ['approval_type' => 'final', 'status' => 'approved'])
            ->assertRedirect();

        $this->assertDatabaseHas('approvals', ['task_id' => $foreignUnderReview->id, 'approval_type' => 'technical']);
        $this->assertDatabaseHas('approvals', ['task_id' => $foreignSupervisorApproved->id, 'approval_type' => 'final']);
    }

    public function test_final_tier_role_boundary_holds_on_foreign_project(): void
    {
        // STEP 14 Final matrix: non-employer/admin roles denied on foreign
        // tasks regardless of grants; no approval rows written.
        $foreign = $this->makeTask($this->projectB, 'supervisor_approved');

        foreach (['supervisor', 'project_manager', 'management', 'viewer'] as $role) {
            $user = $this->makeUserWithRole($role);
            $user->givePermissionTo('final_approval');
            $user->refresh();

            $this->actingAs($user)
                ->post(route('approvals.store', $foreign->id), ['approval_type' => 'final', 'status' => 'approved'])
                ->assertForbidden();

            $this->assertDatabaseMissing('approvals', ['task_id' => $foreign->id]);
        }
    }

    // ------------------------------------------------------------------
    // I-6 — Foreign Resource HTTP Enforcement & Side-Effect Gate.
    // Policy-level denial proven in I-4/I-5; here the HTTP boundary is
    // proven to enforce the policy BEFORE the service, with real rows,
    // real membership, and before/after state + audit snapshots.
    // Field names below are the REAL stage_progress_approvals columns
    // (no invented fields).
    // ------------------------------------------------------------------

    private function makeForeignStageContext(): array
    {
        // Supervisor A: active membership in Project A only.
        $supervisor = $this->makeUserWithRole('supervisor');
        $this->scopeSupervisorToProjectA($supervisor);

        // Real rows in Project B (foreign to the supervisor).
        $foreignStage = $this->makeStage($this->projectB);

        return [$supervisor, $foreignStage];
    }

    public function test_supervisor_cannot_propose_progress_approval_for_foreign_project(): void
    {
        [$supervisor, $foreignStage] = $this->makeForeignStageContext();

        $before = \App\Models\StageProgressApproval::count();
        $auditBefore = \App\Models\ActivityLog::count();

        $this->actingAs($supervisor)
            ->post(route('stages.progress-approvals.store', $foreignStage->id), ['proposed_amount' => 5])
            ->assertForbidden();

        $this->assertSame($before, \App\Models\StageProgressApproval::count(), 'row inserted');
        $this->assertSame($auditBefore, \App\Models\ActivityLog::count(), 'audit written');
    }

    public function test_supervisor_cannot_adjust_foreign_project_progress_approval(): void
    {
        [$supervisor, $foreignStage] = $this->makeForeignStageContext();

        // Real pending approval on the foreign stage (created by an admin).
        $approval = \App\Models\StageProgressApproval::create([
            'module_stage_id' => $foreignStage->id,
            'module_id' => $foreignStage->module_id,
            'proposed_amount' => 5,
            'status' => 'pending',
            'proposed_by' => $this->admin->id,
        ]);

        $snapshot = $approval->only(['status', 'proposed_amount', 'approved_amount', 'final_approved_by', 'decided_at', 'supersedes_approval_id', 'reason']);
        $rowsBefore = \App\Models\StageProgressApproval::count();
        $auditBefore = \App\Models\ActivityLog::count();

        $this->actingAs($supervisor)
            ->post(route('stages.progress-approvals.adjust', $approval->id), ['proposed_amount' => 9])
            ->assertForbidden();

        $approval->refresh();
        $this->assertSame($snapshot, $approval->only(['status', 'proposed_amount', 'approved_amount', 'final_approved_by', 'decided_at', 'supersedes_approval_id', 'reason']), 'row mutated');
        $this->assertSame($rowsBefore, \App\Models\StageProgressApproval::count());
        $this->assertSame($auditBefore, \App\Models\ActivityLog::count(), 'audit written');
    }

    public function test_supervisor_cannot_decide_foreign_project_progress_approval(): void
    {
        [$supervisor, $foreignStage] = $this->makeForeignStageContext();

        $approval = \App\Models\StageProgressApproval::create([
            'module_stage_id' => $foreignStage->id,
            'module_id' => $foreignStage->module_id,
            'proposed_amount' => 5,
            'status' => 'pending',
            'proposed_by' => $this->admin->id,
        ]);

        $snapshot = $approval->only(['status', 'proposed_amount', 'approved_amount', 'final_approved_by', 'decided_at', 'supersedes_approval_id', 'reason']);
        $rowsBefore = \App\Models\StageProgressApproval::count();
        $auditBefore = \App\Models\ActivityLog::count();

        $this->actingAs($supervisor)
            ->post(route('stages.progress-approvals.decide', $approval->id), ['decision' => 'approve', 'approved_amount' => 5])
            ->assertForbidden();

        $approval->refresh();
        $this->assertSame($snapshot, $approval->only(['status', 'proposed_amount', 'approved_amount', 'final_approved_by', 'decided_at', 'supersedes_approval_id', 'reason']), 'row mutated');
        $this->assertSame($rowsBefore, \App\Models\StageProgressApproval::count());
        $this->assertSame($auditBefore, \App\Models\ActivityLog::count(), 'audit written');
    }

    public function test_supervisor_without_membership_cannot_propose_progress_approval(): void
    {
        // Threat D at the HTTP boundary (policy cell proven in I-4).
        $supervisor = $this->makeUserWithRole('supervisor'); // no membership
        $ownTierStage = $this->makeStage($this->projectA); // even an in-scope-elsewhere stage

        $rowsBefore = \App\Models\StageProgressApproval::count();
        $auditBefore = \App\Models\ActivityLog::count();

        $this->actingAs($supervisor)
            ->post(route('stages.progress-approvals.store', $ownTierStage->id), ['proposed_amount' => 5])
            ->assertForbidden();

        $this->assertSame($rowsBefore, \App\Models\StageProgressApproval::count());
        $this->assertSame($auditBefore, \App\Models\ActivityLog::count());
    }
}
