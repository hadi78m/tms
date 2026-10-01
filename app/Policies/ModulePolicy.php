<?php

namespace App\Policies;

use App\Domain\Rules\ProjectScopeService;
use App\Models\Module;
use App\Models\Project;
use App\Models\User;

/**
 * V1.11 — Module Phase · I-1 (DEC-060 · DEC-062 · DEC-064 · DEC-065 · DEC-066):
 * Resource Authorization for the Module surface.
 *
 *   ModulePolicy = Resource Authorization (Project Scope)
 *                  + role-mirror ONLY where DEC-066 (W-B) mandates it.
 *
 * Separation of concerns (locked — same doctrine as TaskPolicy /
 * StageProgressApprovalPolicy):
 *   - Policy = Resource Authorization ONLY. Business invariants stay in
 *     ModuleService / ModuleStageService (SUM = 100, parent locks, weight
 *     lock, already-defined) and are NEVER duplicated here.
 *   - NO permission check anywhere: the module surface holds no catalog
 *     permission (DEC-064 = Role + Project Scope, the DEC-059 stage-progress
 *     pattern). Direct grants are therefore not a factor; role boundary stays
 *     in the route middleware (authoritative).
 *   - Unrestricted project scope (admin/management) is a SCOPE bypass only —
 *     it never bypasses invariants (V11-01, DEC-050).
 *   - Fail-closed: if the resource → Project relation cannot be resolved,
 *     every ability returns false. No null => unrestricted fallback exists.
 *
 * Abilities are derived strictly from real routes (no invented abilities):
 *   viewProject / view / create / update / rebalance / rebalanceStages.
 *   `delete` deliberately DOES NOT exist — no module delete HTTP surface
 *   exists (DEC-038/039) and archive/restore are service-only (no route).
 *
 * rebalance vs create vs rebalanceStages are deliberately separate abilities:
 *   create          = defining the module set of a project (modules.store,
 *                     project resolved from the request body).
 *   rebalance       = redistributing MODULE weights (modules.rebalance).
 *   rebalanceStages = redistributing STAGE weights of one module
 *                     (modules.stages.rebalance — the route binds a Module,
 *                     so this ability lives on the Module resource, not on
 *                     ModuleStage; DEC-066 / W-B adds the role mirror here).
 *
 * Registered via Laravel auto-discovery (no Gate::policy / Gate::define —
 * DEC-050 convention; no mapping conflict exists for Module).
 */
class ModulePolicy
{
    public function __construct(
        protected ProjectScopeService $projectScope
    ) {}

    // ------------------------------------------------------------------
    // viewProject — modules.index / modules.show (resource = Project)
    // ------------------------------------------------------------------

    public function viewProject(User $user, Project $project): bool
    {
        return $this->projectScope->canAccessProject($project, $user);
    }

    // ------------------------------------------------------------------
    // view — a single Module row (scope via Module → Project)
    // ------------------------------------------------------------------

    public function view(User $user, Module $module): bool
    {
        $project = $module->project;

        if ($project === null) {
            return false; // fail-closed: unresolved scope relation
        }

        return $this->projectScope->canAccessProject($project, $user);
    }

    // ------------------------------------------------------------------
    // create — modules.store (target project resolved from request body)
    // ------------------------------------------------------------------

    public function create(User $user, Project $project): bool
    {
        return $this->projectScope->canAccessProject($project, $user);
    }

    // ------------------------------------------------------------------
    // update — modules.update (non-weight attributes)
    // ------------------------------------------------------------------

    public function update(User $user, Module $module): bool
    {
        $project = $module->project;

        if ($project === null) {
            return false; // fail-closed: unresolved scope relation
        }

        return $this->projectScope->canAccessProject($project, $user);
    }

    // ------------------------------------------------------------------
    // rebalance — modules.rebalance (MODULE weights, resource = Project).
    // Deliberately NOT an alias of create: rebalance operates on an existing
    // structure and is a distinct use case with its own tests.
    // ------------------------------------------------------------------

    public function rebalance(User $user, Project $project): bool
    {
        return $this->projectScope->canAccessProject($project, $user);
    }

    // ------------------------------------------------------------------
    // rebalanceStages — modules.stages.rebalance (STAGE weights of one
    // module; the route binds a Module — DEC-066).
    //
    // Role mirror (W-B): exactly the current route middleware list
    // `role:admin|project_manager|employer`. This mirror is informational
    // desync protection (the DEC-059 stage-progress pattern): the middleware
    // stays the authoritative role boundary. supervisor / management /
    // viewer / contractor are denied here even when project scope would
    // allow them, because this is a WEIGHT (financial-ceiling-adjacent)
    // operation.
    // ------------------------------------------------------------------

    public function rebalanceStages(User $user, Module $module): bool
    {
        if (! $user->hasRole(['admin', 'project_manager', 'employer'])) {
            return false;
        }

        $project = $module->project;

        if ($project === null) {
            return false; // fail-closed: unresolved scope relation
        }

        return $this->projectScope->canAccessProject($project, $user);
    }
}
