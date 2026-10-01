<?php

namespace App\Policies;

use App\Domain\Rules\ProjectScopeService;
use App\Models\ModuleStage;
use App\Models\User;

/**
 * V1.11 — Module Phase · I-4 (DEC-061 · DEC-063): Resource Authorization for
 * a single ModuleStage row (modules.stages.show — the ONLY Stage-resource
 * HTTP surface in the repository; the fixed nine-stage catalogue has no
 * create/update/delete/weight endpoints — DEC-038/039).
 *
 * Scope chain (fail-closed, canonical service only):
 *   ModuleStage -> Module -> Project -> ProjectScopeService::canAccessProject
 *
 * Separation of concerns (locked — same doctrine as ModulePolicy):
 *   - Policy = Resource Authorization ONLY. Business invariants (stage weight
 *     sum = 100, weight lock, approval workflow, mode) stay EXCLUSIVELY in
 *     ModuleStageService / StageProgressApprovalService / DB triggers and are
 *     NEVER duplicated here.
 *   - NO permission check: the stage surface holds no catalog permission
 *     (DEC-064 = Role + Project Scope, no new permissions — none invented).
 *   - NO role mirror: the route middleware `role:admin|project_manager|
 *     employer|management|supervisor|viewer` is the authoritative role
 *     boundary; the policy adds Project Scope only. (The rebalanceStages
 *     mirror lives on ModulePolicy per DEC-066, NOT here.)
 *   - Unrestricted scope (admin/management) is a SCOPE bypass only — it never
 *     bypasses invariants (V11-01, DEC-050).
 *   - Employer: NO identity is invented. Scope stays fail-open pending
 *     DR-EMP-01 (OPEN) — ProjectScopeService returns null for employer and
 *     current behavior is preserved untouched.
 *   - Fail-closed: if the ModuleStage -> Module -> Project relation chain
 *     cannot be resolved, view() returns false. No unrestricted fallback.
 *
 * REBALANCE — deliberately NOT here: POST /modules/{module}/stages/rebalance
 * binds a Module (route resource), so its authorization remains
 * ModulePolicy::rebalanceStages(User, Module) (DEC-066, I-1/I-2). Moving it
 * to a Stage-level ability would authorize an operation whose request does
 * not address a single stage.
 *
 * Registered via Laravel auto-discovery: App\Models\ModuleStage maps to
 * App\Policies\ModuleStagePolicy by convention (no Gate::policy needed —
 * DEC-050; AppServiceProvider stays untouched).
 */
class ModuleStagePolicy
{
    public function __construct(
        protected ProjectScopeService $projectScope
    ) {}

    // ------------------------------------------------------------------
    // view — modules.stages.show (GET stages/{stage})
    // ------------------------------------------------------------------

    public function view(User $user, ModuleStage $stage): bool
    {
        $project = $stage->module?->project;

        if ($project === null) {
            return false; // fail-closed: unresolved scope relation chain
        }

        return $this->projectScope->canAccessProject($project, $user);
    }
}
