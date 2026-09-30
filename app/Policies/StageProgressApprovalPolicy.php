<?php

namespace App\Policies;

use App\Domain\Rules\ProjectScopeService;
use App\Models\StageProgressApproval;
use App\Models\User;

/**
 * V1.11 — Approval Phase 2 · I-1 (DEC-056 · DEC-058 · Closure Gate Finding A):
 * Resource Authorization for Stage Progress Approvals.
 *
 * Authorization boundary (Closure Gate §14.1 — Finding A):
 *   Role boundary (route middleware, unchanged and authoritative)
 *   + Project Scope (THIS policy, via ProjectScopeService)
 *   + Service Guard (mode + business invariants — StageProgressApprovalService).
 *
 * Resource resolution (I-2): the Gate maps `App\Models\StageProgressApproval`
 * to this policy (explicit registration — DEC-058). Controllers authorize
 * with a StageProgressApproval instance: the real row for adjust/decide, or
 * an UNSAVED lookahead carrying the target `module_id` for propose.
 *
 *   NO permission exists in the catalog for stage-progress operations and
 *   NONE is invented here (DEC-059). The policy performs role-consistency
 *   checks only — informational mirroring of the route middleware lists so
 *   a future middleware change cannot silently desync the two layers.
 *
 * Mode (`progress_approval_mode`) is NOT duplicated here — it remains the
 * Service Guard's workflow authorization. Business invariants (one active
 * pending, cumulative ceiling, parent lock, approved ≤ proposed, bounds,
 * supersede chain, immutability, audit, state transitions) stay
 * EXCLUSIVELY in StageProgressApprovalService.
 *
 * Registered via explicit Gate::policy (no Gate::define — DEC-050).
 *
 * Scope paths (DEC-056 — never conflated):
 *   propose       : lookahead(module_id) → Module → Project
 *   adjust/decide : StageProgressApproval → module_id (denormalized) → Module → Project
 */
class StageProgressApprovalPolicy
{
    public function __construct(
        protected ProjectScopeService $projectScope
    ) {}

    // ------------------------------------------------------------------
    // propose / supersede — POST stages/{stage}/progress-approvals
    // (supersede flows through the same store route via supersedes_approval_id)
    // Resource: unsaved StageProgressApproval lookahead with module_id set.
    // ------------------------------------------------------------------

    public function propose(User $user, StageProgressApproval $approval): bool
    {
        // Role-consistency mirror of `role:admin|project_manager|employer|supervisor`.
        if (! $user->hasRole(['admin', 'project_manager', 'employer', 'supervisor'])) {
            return false;
        }

        return $this->canAccessApprovalProject($approval, $user);
    }

    // ------------------------------------------------------------------
    // adjust — POST progress-approvals/{approval}/adjust
    // ------------------------------------------------------------------

    public function adjust(User $user, StageProgressApproval $approval): bool
    {
        // Role-consistency mirror of `role:admin|project_manager|employer|supervisor`.
        if (! $user->hasRole(['admin', 'project_manager', 'employer', 'supervisor'])) {
            return false;
        }

        return $this->canAccessApprovalProject($approval, $user);
    }

    // ------------------------------------------------------------------
    // decide — POST progress-approvals/{approval}/decide
    // Role mirror of `role:supervisor` (no permission exists — DEC-059).
    // ------------------------------------------------------------------

    public function decide(User $user, StageProgressApproval $approval): bool
    {
        if (! $user->hasRole('supervisor')) {
            return false;
        }

        return $this->canAccessApprovalProject($approval, $user);
    }

    // ------------------------------------------------------------------
    // Scope resolution (DEC-056) — canonical service only, fail-closed.
    // ------------------------------------------------------------------

    private function canAccessApprovalProject(StageProgressApproval $approval, User $user): bool
    {
        // Denormalized module_id is the canonical denormalized path (V1.9
        // DEC-021 derivation pattern); used with caution per I-1 — verified
        // against the relation graph in the recon.
        $project = $approval->module?->project;

        if ($project === null) {
            return false;
        }

        return $this->projectScope->canAccessProject($project, $user);
    }
}
