<?php

namespace App\Policies;

use App\Domain\Rules\ProjectScopeService;
use App\Models\Project;
use App\Models\User;

/**
 * V1.11 — V11-01 (DR-11-1 ③ = G-C): Resource Authorization for Project.
 *
 *   Policy     = Permission AND Project Scope AND resource relationship
 *   Service    = Business Invariants (never duplicated here)
 *   Permission ≠ Project Scope (DEC-050): holding the permission does not
 *   grant access to every project; scope alone does not grant the action.
 *
 * Registered via Laravel auto-discovery (no Gate::define, DEC-050).
 * Rejection surfaces as 403 (AuthorizationException), consistent with the
 * existing abort(403, ...) convention.
 *
 * Only abilities backed by real routes/use cases are defined (no
 * update/delete — no such approved operations exist for projects).
 */
class ProjectPolicy
{
    public function __construct(
        protected ProjectScopeService $scopeService
    ) {}

    /**
     * List projects (modules.index renders the project list).
     * Row-level filtering is enforced by ProjectScopeService::applyProjectScope.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * View a single project (modules.show: projects/{project}/modules).
     */
    public function view(User $user, Project $project): bool
    {
        return $this->scopeService->canAccessProject($project, $user);
    }

    /**
     * Manage the structure of a project (modules.store/rebalance/update,
     * wbs-phases.store). Permission is enforced at the route layer
     * (role:admin|project_manager|employer) and by the services' invariants;
     * this ability adds the project-scope requirement.
     */
    public function manageStructure(User $user, Project $project): bool
    {
        return $this->scopeService->canAccessProject($project, $user);
    }
}
