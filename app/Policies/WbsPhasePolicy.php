<?php

namespace App\Policies;

use App\Domain\Rules\ProjectScopeService;
use App\Models\Project;
use App\Models\User;
use App\Models\WbsPhase;

/**
 * V1.11 — WBS Phase · I-1 (WBS-01 · WBS-02 · WBS-03 · WBS-03b · WBS-05):
 * Resource Authorization for the WbsPhase surface.
 *
 * Dedicated policy (WBS-01 = A) — WBS authorization is NOT folded into
 * TaskPolicy / ModulePolicy. The policy answers ONLY:
 *   "May this actor access this WBS resource in this project?"
 *
 * Scope chain (WBS-02 = A — direct, canonical service only, fail-closed):
 *   WbsPhase -> Project -> ProjectScopeService::canAccessProject(...)
 * No membership logic is duplicated here; ProjectScopeService is the single
 * canonical source (V11-01, DEC-048/DEC-050).
 *
 * Separation of concerns (locked):
 *   - Policy = Resource Authorization ONLY. All WbsPhase business invariants
 *     (completion authority, pending-only completion, terminal-only reopen,
 *     mandatory rejection reason, checklist↔phase integrity, initial phase
 *     state, DB completion-state constraints, audit of transitions) stay
 *     EXCLUSIVELY in WbsPhaseService / DB — never duplicated here.
 *   - NO permission check: the WBS surface holds no catalog permission and
 *     NONE is invented (same doctrine as DEC-059/DEC-064).
 *   - Role boundaries stay in the route middleware (authoritative and
 *     UNCHANGED): read = admin|pm|employer|management|supervisor|viewer,
 *     write = admin|pm|employer, decide = supervisor. The only policy-side
 *     role mirror is `decide` (WBS-03), mirroring `role:supervisor` exactly —
 *     informational desync protection, same pattern as DEC-059.
 *   - WBS-03b: Admin remains UNABLE to decide. Admin's unrestricted PROJECT
 *     SCOPE never implies WBS abilities; the mirror keeps that true even if
 *     middleware ever changes.
 *   - Fail-closed: if the WbsPhase -> Project relation cannot be resolved,
 *     every phase-scoped ability returns false. No null => open fallback.
 *   - Employer: NO identity is invented (DR-EMP-01 OPEN). Scope stays
 *     fail-open for employer, preserving current behavior untouched.
 *   - DEC-052 (wbs_phases.weight) is DEFERRED — weight semantics are not
 *     this policy's concern and nothing here touches weight.
 *
 * Abilities are derived strictly from the approved design (no invented
 * abilities): view / create / addChecklistItem / completeChecklistItem /
 * reopenChecklistItem / decide. No update/delete/archive/restore exists —
 * no WbsPhase lifecycle HTTP surface exists (DEC-038/039).
 *
 * Checklist operations (WBS-05 = A) authorize the PARENT WbsPhase resource
 * with distinct abilities per operation. The controller's phase/item
 * integrity check (`abort(404)`) is NOT replaced — it remains the
 * resource-resolution guard; the policy adds project scope on top.
 *
 * Registered via Laravel auto-discovery: App\Models\WbsPhase maps to
 * App\Policies\WbsPhasePolicy by convention (no Gate::policy needed —
 * DEC-050; AppServiceProvider stays untouched).
 */
class WbsPhasePolicy
{
    public function __construct(
        protected ProjectScopeService $projectScope
    ) {}

    // ------------------------------------------------------------------
    // view — wbs-phases.show (GET wbs-phases/{phase})
    // ------------------------------------------------------------------

    public function view(User $user, WbsPhase $phase): bool
    {
        return $this->canAccessPhaseProject($phase, $user);
    }

    // ------------------------------------------------------------------
    // create — wbs-phases.store (target Project resolved from request body)
    // ------------------------------------------------------------------

    public function create(User $user, Project $project): bool
    {
        return $this->projectScope->canAccessProject($project, $user);
    }

    // ------------------------------------------------------------------
    // Checklist operations (WBS-05) — authorize the PARENT WbsPhase.
    //   addChecklistItem      = wbs-phases.checklist-items.store
    //   completeChecklistItem = wbs-phases.checklist-items.complete
    //   reopenChecklistItem   = wbs-phases.checklist-items.reopen
    // Deliberately separate abilities: add is a definition act, complete /
    // reopen are checklist-state transitions with distinct service guards.
    // ------------------------------------------------------------------

    public function addChecklistItem(User $user, WbsPhase $phase): bool
    {
        return $this->canAccessPhaseProject($phase, $user);
    }

    public function completeChecklistItem(User $user, WbsPhase $phase): bool
    {
        return $this->canAccessPhaseProject($phase, $user);
    }

    public function reopenChecklistItem(User $user, WbsPhase $phase): bool
    {
        return $this->canAccessPhaseProject($phase, $user);
    }

    // ------------------------------------------------------------------
    // decide — wbs-phases.decide (complete / not_completed / reopen)
    //
    // WBS-03 (A): the supervisor must (1) pass the existing role boundary,
    // (2) hold an ACTIVE supervisor membership for the WBS project, and
    // (3) access a WbsPhase belonging to that project.
    // WBS-03b (B): Admin remains unable to decide — unrestricted project
    // scope does NOT imply WBS abilities.
    // ------------------------------------------------------------------

    public function decide(User $user, WbsPhase $phase): bool
    {
        // Role mirror of `role:supervisor` (WBS-03 · WBS-03b): admin /
        // management / pm / employer / viewer / contractor are denied here
        // even when project scope would allow them.
        if (! $user->hasRole('supervisor')) {
            return false;
        }

        return $this->canAccessPhaseProject($phase, $user);
    }

    // ------------------------------------------------------------------
    // Scope resolution (WBS-02) — canonical service only, fail-closed.
    // ------------------------------------------------------------------

    private function canAccessPhaseProject(WbsPhase $phase, User $user): bool
    {
        $project = $phase->project;

        if ($project === null) {
            return false; // fail-closed: unresolved scope relation
        }

        return $this->projectScope->canAccessProject($project, $user);
    }
}
