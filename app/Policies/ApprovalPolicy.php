<?php

namespace App\Policies;

use App\Domain\Rules\ProjectScopeService;
use App\Models\Approval;
use App\Models\User;

/**
 * V1.11 — Approval Phase 2 · I-1 (DEC-056 · DEC-058 · DEC-059 · DEC-050):
 * Resource Authorization for the two-tier TASK Approval.
 *
 *   ApprovalPolicy = Role AND Permission AND Project Scope.
 *
 * Resource resolution (I-2): the Gate maps `App\Models\Approval` to this
 * policy (explicit registration — DEC-058). The controller authorizes with
 * an UNSAVED Approval lookahead instance carrying the target `task_id`, so
 * the scope of the target task is enforced before any row is written.
 *
 * Separation of concerns (locked — Design Gate §6/§7):
 *   - Role boundary: technical = admin|supervisor · final = admin|employer.
 *     A Direct User Permission grant NEVER bypasses the role boundary
 *     (Permission ≠ Role ≠ Project Scope — DEC-050/DEC-059).
 *   - Permission: `technical_approval` / `final_approval` (existing catalog,
 *     nothing invented).
 *   - Project Scope: ALWAYS delegated to the canonical ProjectScopeService
 *     (DEC-056) — no parallel scope logic. Employer scope stays unresolved
 *     (DR-EMP-01 OPEN): employerBranch() returns null today, which preserves
 *     the current Q1/OD-2 fall-through — this policy does NOT invent an
 *     unrestricted fallback and does NOT touch employerBranch().
 *   - Business invariants (contractor-null guard, tier→state preconditions,
 *     task-already-approved lock, audit) remain EXCLUSIVELY in
 *     ApprovalService — never duplicated here.
 *
 * Scope path (DEC-056): Approval → task_id → Task → Project.
 */
class ApprovalPolicy
{
    public function __construct(
        protected ProjectScopeService $projectScope
    ) {}

    // ------------------------------------------------------------------
    // createTechnical — POST tasks/{task}/approvals (approval_type=technical)
    // ------------------------------------------------------------------

    public function createTechnical(User $user, Approval $approval): bool
    {
        // Role boundary first (DEC-059): admin | supervisor.
        if (! $user->hasRole(['admin', 'supervisor'])) {
            return false;
        }

        // Permission layer (existing catalog — DEC-050 grant-only).
        if (! $user->can('technical_approval')) {
            return false;
        }

        // Project Scope (DEC-056) — canonical service only, via the target
        // task carried by the lookahead instance.
        $task = $approval->task;

        if ($task === null) {
            return false;
        }

        return $this->projectScope->canAccessProject($task->project, $user);
    }

    // ------------------------------------------------------------------
    // createFinal — POST tasks/{task}/approvals (approval_type=final)
    // ------------------------------------------------------------------

    public function createFinal(User $user, Approval $approval): bool
    {
        // Role boundary (DEC-059): admin | employer. A supervisor holding a
        // direct `final_approval` grant is DENIED here.
        if (! $user->hasRole(['admin', 'employer'])) {
            return false;
        }

        if (! $user->can('final_approval')) {
            return false;
        }

        $task = $approval->task;

        if ($task === null) {
            return false;
        }

        // Project Scope (DEC-056). For role=employer the resolution basis is
        // DR-EMP-01 (OPEN): employerBranch() currently returns null → the
        // Q1/OD-2 fall-through applies. Fail-closed by design if the service
        // ever yields an empty scope; NO unrestricted fallback is invented
        // here and employerBranch() is NOT modified (I-2 boundary).
        return $this->projectScope->canAccessProject($task->project, $user);
    }
}
