<?php

namespace App\Domain\Services;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\DTOs\AssignProjectSupervisorData;
use App\Domain\Enums\ProjectMembershipType;
use App\Domain\Exceptions\InvalidProjectMembershipException;
use App\Domain\Exceptions\ProjectMembershipRaceConditionException;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * V1.11 — V11-01 (DEC-048 / OD-1 · OD-6): the CANONICAL Project Membership
 * assignment service. Every business rule for assignments lives here —
 * callers (command/HTTP) only validate input and resolve the actor.
 *
 * Owner-locked rules:
 *   1. Only the 'supervisor' membership type exists in V1.11 (OD-2: Employer DEFERRED).
 *   2. The assignee must be an existing, active user.
 *   3. The project must exist.
 *   4/5. At most ONE active Supervisor per Project (DB backstop:
 *        idx_active_project_supervisor); replacement ends the previous active
 *        assignment (ended_at = now), retains history, then creates the new one.
 *   6. No future-dated assignments (OD-6-c): started_at = now().
 *   7. ended_at is never set before started_at (also chk_pm_dates).
 *   8. assigned_by must be a real user (actor resolved by caller).
 *   9. Historical rows are never deleted; there is no delete path here.
 *
 * Concurrency (C-14/C-15): DB::transaction + parent lock (Project) +
 * row lock (active membership) + partial unique index mapped to
 * ProjectMembershipRaceConditionException — same doctrine as
 * TaskAssignmentService.
 */
class ProjectMembershipService
{
    public function __construct(
        protected AuditServiceInterface $auditService
    ) {}

    /**
     * Assign (or replace) the active Supervisor of a project.
     *
     * Idempotent: assigning the currently-active supervisor again is a no-op.
     */
    public function assignSupervisor(AssignProjectSupervisorData $data, User $actor): ProjectMembership
    {
        if (! in_array($actor->id, [$data->assigned_by], true)) {
            throw new InvalidProjectMembershipException('assigned_by must be the acting user.');
        }

        try {
            return DB::transaction(function () use ($data, $actor) {
                /** @var Project $project */
                $project = Project::where('id', $data->project_id)->lockForUpdate()->firstOrFail();

                $assignee = User::findOrFail($data->user_id);

                if (! $assignee->is_active) {
                    throw new InvalidProjectMembershipException('The assignee user is not active.');
                }

                // Current active supervisor assignment, locked.
                /** @var ProjectMembership|null $active */
                $active = ProjectMembership::where('project_id', $project->id)
                    ->where('membership_type', ProjectMembershipType::Supervisor->value)
                    ->whereNull('ended_at')
                    ->lockForUpdate()
                    ->first();

                // Idempotency: same user already active → no-op.
                if ($active && $active->user_id === $data->user_id) {
                    return $active;
                }

                // OD-6-d: end the previous active assignment — never delete it.
                if ($active) {
                    $active->ended_at = now();
                    $active->save();

                    $this->auditService->log(
                        'project_supervisor_ended',
                        $active,
                        $actor,
                        ['user_id' => $active->user_id],
                        ['user_id' => null, 'ended_at' => $active->ended_at->toIso8601String()]
                    );
                }

                $new = ProjectMembership::create([
                    'project_id' => $project->id,
                    'user_id' => $assignee->id,
                    'membership_type' => ProjectMembershipType::Supervisor->value,
                    'started_at' => now(),
                    'ended_at' => null,
                    'assigned_by' => $actor->id,
                    'reason' => $data->reason,
                ]);

                $this->auditService->log(
                    'project_supervisor_assigned',
                    $new,
                    $actor,
                    $active ? ['user_id' => $active->user_id] : [],
                    ['user_id' => $assignee->id, 'reason' => $data->reason]
                );

                return $new;
            });
        } catch (QueryException $e) {
            // C-15 — map the partial unique index violation to a domain exception.
            if ($this->isSupervisorRaceViolation($e)) {
                throw new ProjectMembershipRaceConditionException;
            }

            throw $e;
        }
    }

    /**
     * C-15 — does this QueryException report a violation of the partial unique
     * index idx_active_project_supervisor (one active Supervisor per project)?
     *
     * Extracted for testability: the race window itself is cross-transaction
     * and cannot be reproduced under RefreshDatabase, but Postgres reports the
     * index name in the violation message, so the mapping can be verified with
     * a faithful UniqueConstraintViolationException (code 23505).
     */
    protected function isSupervisorRaceViolation(QueryException $e): bool
    {
        return str_contains($e->getMessage(), 'idx_active_project_supervisor');
    }

    /**
     * End the active Supervisor assignment of a project without replacement.
     * History is retained; the row is never deleted.
     */
    public function endActiveSupervisor(Project $project, User $actor, ?string $reason = null): ?ProjectMembership
    {
        return DB::transaction(function () use ($project, $actor, $reason) {
            /** @var ProjectMembership|null $active */
            $active = ProjectMembership::where('project_id', $project->id)
                ->where('membership_type', ProjectMembershipType::Supervisor->value)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            if ($active === null) {
                return null;
            }

            $active->ended_at = now();
            $active->save();

            $this->auditService->log(
                'project_supervisor_ended',
                $active,
                $actor,
                ['user_id' => $active->user_id],
                ['user_id' => null, 'ended_at' => $active->ended_at->toIso8601String(), 'reason' => $reason]
            );

            return $active;
        });
    }
}
