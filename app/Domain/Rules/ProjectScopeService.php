<?php

namespace App\Domain\Rules;

use App\Domain\Enums\ProjectMembershipType;
use App\Domain\Exceptions\ProjectScopeViolationException;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * V1.11 — V11-01 (DEC-048 / DR-11-1 ③ = G-C): the SINGLE canonical source of
 * Project Scope logic. Policies and Query Scopes must consume this service —
 * no controller/policy may hand-roll `whereIn('project_id', ...)`.
 *
 * Separation of concerns (locked):
 *   Permission      ≠ Project Scope          (DEC-050)
 *   Project Scope   ≠ Business Invariants    (Service Guards stay independent)
 *   Unrestricted    = project-scope bypass ONLY — it does NOT bypass
 *                     permissions, business invariants, financial boundaries
 *                     or audit. No Gate::before is used.
 *
 * Role rules (owner-locked, nothing invented):
 *   supervisor        → only projects with an ACTIVE supervisor membership
 *                       (membership_type = supervisor, ended_at IS NULL).
 *   employer          → NOT enforced in V1.11 (OD-2 DEFERRED — current
 *                       behavior preserved). The employerBranch() hook below
 *                       is the single future plug-point.
 *   admin/management  → unrestricted (scope bypass only).
 *   contractor        → UNTOUCHED: the existing contractor scope
 *                       (TaskScopeService + controller filters) keeps working;
 *                       this service is additive.
 *   project_manager/viewer/others → no project scope in V1.11 (Q1 decision:
 *                       preserve current behavior — no new business rule).
 */
class ProjectScopeService
{
    /**
     * True when the actor has no project-scope restriction.
     * (Only project scope — nothing else is bypassed.)
     */
    public function isUnrestricted(User $actor): bool
    {
        return $actor->hasRole('admin') || $actor->hasRole('management');
    }

    /**
     * IDs of the projects the actor may operationally access.
     *
     * @return Collection<int, int>|null null = unrestricted (no filtering);
     *                                   otherwise the allowed project_id set
     */
    public function accessibleProjectIds(User $actor): ?Collection
    {
        if ($this->isUnrestricted($actor)) {
            return null;
        }

        // Employer scope is DEFERRED (OD-2): nothing is derived, no identity
        // is invented. Future plug-point — see employerBranch().

        if ($actor->hasRole('supervisor')) {
            return $this->supervisorBranch($actor);
        }

        // Q1 decision: project_manager / viewer / others keep current
        // behavior — no project scope restriction is derived for them.
        return null;
    }

    public function canAccessProject(Project $project, User $actor): bool
    {
        $ids = $this->accessibleProjectIds($actor);

        if ($ids === null) {
            return true;
        }

        return $ids->contains($project->id);
    }

    /**
     * @throws ProjectScopeViolationException
     */
    public function assertCanAccessProject(Project $project, User $actor): void
    {
        if (! $this->canAccessProject($project, $actor)) {
            throw new ProjectScopeViolationException('User does not have project scope over this project.');
        }
    }

    /**
     * Retrieval-level enforcement for resources WITH a direct project column.
     * Unrestricted actors get the query back untouched.
     */
    public function applyProjectScope(Builder $query, User $actor, string $projectColumn = 'project_id'): Builder
    {
        $ids = $this->accessibleProjectIds($actor);

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($projectColumn, $ids->all());
    }

    /**
     * Retrieval-level enforcement for resources whose project is reachable
     * only through a relation (e.g. Stage → Module → Project).
     */
    public function applyProjectScopeVia(Builder $query, User $actor, string $relation): Builder
    {
        $ids = $this->accessibleProjectIds($actor);

        if ($ids === null) {
            return $query;
        }

        return $query->whereHas($relation, function (Builder $q) use ($ids) {
            $q->whereIn('project_id', $ids->all());
        });
    }

    /**
     * Supervisor = assigned-based: projects with an ACTIVE supervisor
     * membership (ended_at IS NULL). Historical (ended) assignments grant no
     * operational scope — OD-6-a.
     *
     * @return Collection<int, int>
     */
    protected function supervisorBranch(User $actor): Collection
    {
        return ProjectMembership::query()
            ->where('user_id', $actor->id)
            ->where('membership_type', ProjectMembershipType::Supervisor->value)
            ->whereNull('ended_at')
            ->pluck('project_id');
    }

    /**
     * Future Employer plug-point (OD-2 DEFERRED, deliberately unimplemented):
     * when the Owner approves a real Employer → Contract → Project identity
     * link, derive the contract-based project set HERE. Because Policies and
     * Query Scopes only consume this service, adding employer scope later
     * requires zero policy rewrites.
     */
    protected function employerBranch(User $actor): ?Collection
    {
        // DEFERRED — OD-2: repository evidence does not establish an
        // Employer ↔ Contract identity link. Do not derive from the Spatie
        // role, from contractors, or from synced_contracts.
        return null;
    }
}
