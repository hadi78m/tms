<?php

namespace Tests\Feature\V111;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\DTOs\AssignProjectSupervisorData;
use App\Domain\Enums\ProjectMembershipType;
use App\Domain\Exceptions\InvalidProjectMembershipException;
use App\Domain\Exceptions\ProjectMembershipRaceConditionException;
use App\Domain\Rules\ProjectScopeService;
use App\Domain\Services\ProjectMembershipService;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\V19\Concerns\V19Fixtures;
use Tests\TestCase;

use function app;

/**
 * V1.11 — V11-01: Project Membership infrastructure (DEC-048 · OD-1/OD-6/OD-8).
 *
 * Covers: assignment, replacement (OD-6-d), idempotency, historical retention,
 * one-active-Supervisor cardinality (service + DB backstop), FK integrity,
 * audit trail, and the ProjectScopeService role branches.
 *
 * OD-8: NO backfill — the migration creates an EMPTY table and this suite
 * creates its own memberships explicitly.
 */
class ProjectMembershipTest extends TestCase
{
    use RefreshDatabase, V19Fixtures;

    private Project $project;

    private User $admin;

    private User $supervisorA;

    private User $supervisorB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makeProject(1);
        $this->admin = $this->makeUserWithRole('admin');
        $this->supervisorA = $this->makeUserWithRole('supervisor');
        $this->supervisorB = $this->makeUserWithRole('supervisor');
    }

    private function service(): ProjectMembershipService
    {
        return app(ProjectMembershipService::class);
    }

    private function assign(Project $project, User $user, ?User $actor = null, ?string $reason = null): ProjectMembership
    {
        return $this->service()->assignSupervisor(
            new AssignProjectSupervisorData(
                project_id: $project->id,
                user_id: $user->id,
                assigned_by: ($actor ?? $this->admin)->id,
                reason: $reason
            ),
            $actor ?? $this->admin
        );
    }

    // ------------------------------------------------------------------
    // T-1 — create an active Supervisor assignment + audit
    // ------------------------------------------------------------------

    public function test_creates_active_supervisor_assignment_with_audit(): void
    {
        $membership = $this->assign($this->project, $this->supervisorA, reason: 'initial controlled assignment');

        $this->assertTrue($membership->wasRecentlyCreated);
        $this->assertTrue($membership->isActive());
        $this->assertNull($membership->ended_at);
        $this->assertSame(ProjectMembershipType::Supervisor->value, $membership->membership_type);
        $this->assertSame($this->admin->id, $membership->assigned_by);
        $this->assertNotNull($membership->started_at);

        $this->assertDatabaseHas('activity_logs', ['action' => 'project_supervisor_assigned']);
    }

    // ------------------------------------------------------------------
    // T-4/T-5 — replacement ends the previous active assignment (OD-6-d)
    // ------------------------------------------------------------------

    public function test_replacement_ends_previous_assignment_and_retains_history(): void
    {
        $first = $this->assign($this->project, $this->supervisorA);
        $second = $this->assign($this->project, $this->supervisorB);

        $this->assertTrue($second->wasRecentlyCreated);
        $this->assertSame($this->supervisorB->id, $second->user_id);
        $this->assertTrue($second->isActive());

        // The previous assignment is ended, NOT deleted (historical retention).
        $first->refresh();
        $this->assertNotNull($first->ended_at);
        $this->assertFalse($first->isActive());
        $this->assertDatabaseHas('project_memberships', ['id' => $first->id]);

        // Exactly one active supervisor for the project.
        $this->assertSame(1, ProjectMembership::query()
            ->where('project_id', $this->project->id)
            ->where('membership_type', ProjectMembershipType::Supervisor->value)
            ->whereNull('ended_at')
            ->count());

        $this->assertDatabaseHas('activity_logs', ['action' => 'project_supervisor_ended']);
    }

    // ------------------------------------------------------------------
    // T-6 — idempotency: assigning the active supervisor again is a no-op
    // ------------------------------------------------------------------

    public function test_reassigning_same_active_supervisor_is_idempotent(): void
    {
        $first = $this->assign($this->project, $this->supervisorA);
        $second = $this->assign($this->project, $this->supervisorA);

        $this->assertFalse($second->wasRecentlyCreated);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ProjectMembership::query()->where('project_id', $this->project->id)->count());
    }

    // ------------------------------------------------------------------
    // T-7 — DB backstop: a second active supervisor row is rejected by the
    // partial unique index even without the service
    // ------------------------------------------------------------------

    public function test_database_rejects_second_active_supervisor(): void
    {
        $this->assign($this->project, $this->supervisorA);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('project_memberships')->insert([
            'project_id' => $this->project->id,
            'user_id' => $this->supervisorB->id,
            'membership_type' => ProjectMembershipType::Supervisor->value,
            'started_at' => now(),
            'ended_at' => null,
            'assigned_by' => $this->admin->id,
        ]);
    }

    // ------------------------------------------------------------------
    // T-8 — race condition mapping (idx_active_project_supervisor → domain exception)
    //
    // The race window itself is cross-transaction and cannot exist under
    // RefreshDatabase (single connection/transaction), and any visible active
    // row is legitimately ended by the service before INSERT. The DB backstop
    // itself is proven by T-7; here we prove the mapping deterministically
    // with the exact exception shape Postgres produces (23505 unique
    // violation → UniqueConstraintViolationException, which extends
    // QueryException, message containing the index name).
    // ------------------------------------------------------------------

    public function test_unique_violation_maps_to_race_condition_exception(): void
    {
        // Anonymous subclass exposing the protected mapper for direct testing.
        $service = new class(app(AuditServiceInterface::class)) extends ProjectMembershipService
        {
            public function exposeIsRaceViolation(\Illuminate\Database\QueryException $e): bool
            {
                return $this->isSupervisorRaceViolation($e);
            }
        };

        // Faithful Postgres shape: SQLSTATE 23505 → Laravel raises
        // UniqueConstraintViolationException (extends QueryException) whose
        // message contains the index name — exactly what the service sees.
        $race = new \Illuminate\Database\UniqueConstraintViolationException(
            config('database.default'),
            'insert into "project_memberships" (project_id, user_id, membership_type, started_at, ended_at, assigned_by) values ($1, $2, $3, $4, $5, $6)',
            [],
            new \PDOException(
                'SQLSTATE[23505]: ERROR: duplicate key value violates unique constraint "idx_active_project_supervisor" DETAIL: Key (project_id)=(1) already exists.',
                23505
            )
        );
        $this->assertTrue($service->exposeIsRaceViolation($race));

        // Any other QueryException must NOT be swallowed into the race
        // exception — it is rethrown as-is.
        $other = new \Illuminate\Database\QueryException(
            config('database.default'),
            'select * from "projects"',
            [],
            new \PDOException('SQLSTATE[42P01]: ERROR: relation "nope" does not exist')
        );
        $this->assertFalse($service->exposeIsRaceViolation($other));

        // A QueryException from a DIFFERENT constraint (e.g. chk_pm_dates)
        // must also not be mapped to the race exception.
        $checkViolation = new \Illuminate\Database\UniqueConstraintViolationException(
            config('database.default'),
            'insert into "project_memberships" ...',
            [],
            new \PDOException(
                'SQLSTATE[23514]: ERROR: new row for relation "project_memberships" violates check constraint "chk_pm_dates"',
                23514
            )
        );
        $this->assertFalse($service->exposeIsRaceViolation($checkViolation));
    }

    // ------------------------------------------------------------------
    // T-9 — invalid period (ended_at < started_at) rejected by chk_pm_dates
    // ------------------------------------------------------------------

    public function test_database_rejects_ended_at_before_started_at(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('project_memberships')->insert([
            'project_id' => $this->project->id,
            'user_id' => $this->supervisorA->id,
            'membership_type' => ProjectMembershipType::Supervisor->value,
            'started_at' => now(),
            'ended_at' => now()->subDay(),
            'assigned_by' => $this->admin->id,
        ]);
    }

    // ------------------------------------------------------------------
    // T-10 — FK restrict: deleting a project row with memberships is rejected
    // (raw SQL delete — the Eloquent model uses SoftDeletes and would only
    // set deleted_at, which never touches the FK)
    // ------------------------------------------------------------------

    public function test_foreign_keys_restrict_deletion(): void
    {
        $this->assign($this->project, $this->supervisorA);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('projects')->where('id', $this->project->id)->delete();
    }

    // ------------------------------------------------------------------
    // T-11 — a project without any supervisor is valid (OD-8 / B-2)
    // ------------------------------------------------------------------

    public function test_project_without_supervisor_is_valid(): void
    {
        $this->assertNull($this->project->activeSupervisor()->first());
        $this->assertSame(0, $this->project->memberships()->count());
    }

    // ------------------------------------------------------------------
    // T-15/S-16 — deletion is blocked at the model layer (no delete path)
    // ------------------------------------------------------------------

    public function test_membership_rows_cannot_be_deleted(): void
    {
        $membership = $this->assign($this->project, $this->supervisorA);

        $this->assertFalse($membership->delete());
        $this->assertDatabaseHas('project_memberships', ['id' => $membership->id]);
    }

    // ------------------------------------------------------------------
    // T-22 — assigned_by must be the acting user (audit identity)
    // ------------------------------------------------------------------

    public function test_assigned_by_must_be_the_acting_user(): void
    {
        $this->expectException(InvalidProjectMembershipException::class);

        $this->service()->assignSupervisor(
            new AssignProjectSupervisorData(
                project_id: $this->project->id,
                user_id: $this->supervisorA->id,
                assigned_by: $this->supervisorB->id // not the actor
            ),
            $this->admin
        );
    }

    // ------------------------------------------------------------------
    // Scope: supervisor branch (assigned-based, active-only)
    // ------------------------------------------------------------------

    public function test_scope_sees_only_currently_assigned_projects(): void
    {
        $project2 = $this->makeProject(2);
        $endedProject = $this->makeProject(3);

        $this->assign($this->project, $this->supervisorA);
        $this->assign($endedProject, $this->supervisorA);
        $this->service()->endActiveSupervisor($endedProject, $this->admin);

        $scope = app(ProjectScopeService::class);

        $ids = $scope->accessibleProjectIds($this->supervisorA);
        $this->assertSame([$this->project->id], $ids->all());

        $this->assertTrue($scope->canAccessProject($this->project, $this->supervisorA));
        $this->assertFalse($scope->canAccessProject($project2, $this->supervisorA));
        // Ended assignment ⇒ no operational scope, but history retained.
        $this->assertFalse($scope->canAccessProject($endedProject, $this->supervisorA));
        $this->assertDatabaseHas('project_memberships', [
            'project_id' => $endedProject->id,
            'user_id' => $this->supervisorA->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Scope: admin / management unrestricted (project scope only)
    // ------------------------------------------------------------------

    public function test_admin_and_management_are_unrestricted(): void
    {
        $scope = app(ProjectScopeService::class);

        $this->assertTrue($scope->isUnrestricted($this->admin));
        $this->assertNull($scope->accessibleProjectIds($this->admin));

        $management = $this->makeUserWithRole('management');
        $this->assertTrue($scope->isUnrestricted($management));
        $this->assertNull($scope->accessibleProjectIds($management));
    }

    // ------------------------------------------------------------------
    // Scope: employer deferred — no scope is derived (OD-2)
    // ------------------------------------------------------------------

    public function test_employer_scope_is_deferred_not_derived(): void
    {
        $employer = $this->makeUserWithRole('employer');
        $scope = app(ProjectScopeService::class);

        $this->assertFalse($scope->isUnrestricted($employer));
        // DEFERRED (OD-2): no scope is derived from the role, the contractor
        // or the contract — current behavior preserved.
        $this->assertNull($scope->accessibleProjectIds($employer));
    }

    // ------------------------------------------------------------------
    // Scope: project_manager / viewer — Q1 decision: preserve current behavior
    // ------------------------------------------------------------------

    public function test_project_manager_and_viewer_keep_current_behavior(): void
    {
        $scope = app(ProjectScopeService::class);

        $pm = $this->makeUserWithRole('project_manager');
        $viewer = $this->makeUserWithRole('viewer');

        $this->assertNull($scope->accessibleProjectIds($pm));
        $this->assertNull($scope->accessibleProjectIds($viewer));
    }

    // ------------------------------------------------------------------
    // Scope: applyProjectScope / applyProjectScopeVia on queries
    // ------------------------------------------------------------------

    public function test_query_scope_filters_and_bypasses(): void
    {
        $project2 = $this->makeProject(2);
        $this->assign($this->project, $this->supervisorA);

        $scope = app(ProjectScopeService::class);

        $visible = $scope->applyProjectScope(Project::query(), $this->supervisorA, 'id');
        $this->assertSame([$this->project->id], $visible->pluck('id')->all());

        $visibleVia = $scope->applyProjectScopeVia(
            Project::query(),
            $this->supervisorA,
            'memberships'
        );
        $this->assertSame(1, $visibleVia->count());

        // Unrestricted actor: query untouched.
        $this->assertSame(2, $scope->applyProjectScope(Project::query(), $this->admin, 'id')->count());
    }

    // ------------------------------------------------------------------
    // Command: controlled initial assignment (Q3) — actor is real, traceable
    // ------------------------------------------------------------------

    public function test_command_requires_real_actor_and_assigns_through_service(): void
    {
        $this->artisan('project:assign-supervisor', [
            'project' => $this->project->id,
            'user' => $this->supervisorA->national_code,
            '--actor' => $this->admin->national_code,
            '--reason' => 'controlled initial assignment',
        ])->assertSuccessful();

        $this->assertDatabaseHas('project_memberships', [
            'project_id' => $this->project->id,
            'user_id' => $this->supervisorA->id,
            'assigned_by' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'project_supervisor_assigned']);
    }

    public function test_command_fails_without_actor(): void
    {
        $this->artisan('project:assign-supervisor', [
            'project' => $this->project->id,
            'user' => $this->supervisorA->national_code,
        ])->assertFailed();

        $this->assertDatabaseMissing('project_memberships', [
            'project_id' => $this->project->id,
        ]);
    }

    public function test_command_fails_with_unknown_actor(): void
    {
        $this->artisan('project:assign-supervisor', [
            'project' => $this->project->id,
            'user' => $this->supervisorA->national_code,
            '--actor' => '0000000000',
        ])->assertFailed();

        $this->assertDatabaseMissing('project_memberships', [
            'project_id' => $this->project->id,
        ]);
    }
}
