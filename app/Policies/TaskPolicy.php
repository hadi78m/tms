<?php

namespace App\Policies;

use App\Domain\Rules\ProjectScopeService;
use App\Domain\Rules\TaskScopeService;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

/**
 * V1.11 — DEC-049 Phase 1 · I-1 (DEC-054 · DEC-055 · DEC-050 · DEC-048 G-C):
 * Resource Authorization for Task.
 *
 *   TaskPolicy = Effective Permission (create/assign only) AND Project Scope
 *                AND actor-side Contractor isolation.
 *
 * Separation of concerns (locked):
 *   - Permission ≠ Project Scope (DEC-050 §12): a grant can never open a
 *     project outside the actor's scope, and scope can never supply a
 *     permission the actor does not hold.
 *   - Business invariants stay in the Service Guards and are NEVER
 *     duplicated here: TaskStateTransition state machine, Final-Approval
 *     stage lock (DEC-043), supervisor-only stage assignment (DEC-042),
 *     assignee contractor scope + assignment race mapping
 *     (TaskAssignmentService), audit mutation rules. Policy denial is
 *     resource-level; service guards still protect every write.
 *   - Unrestricted project scope (admin/management) is a SCOPE bypass only —
 *     it does not bypass permission or invariants (V11-01, OD-2/OD-8).
 *
 * Abilities are derived strictly from real use cases (no invented abilities):
 *   view / create / startProgress / submitForReview / manageStages / assign.
 *   `update` and `destroy` deliberately DO NOT exist — `Route::resource`
 *   declares those verbs but no controller methods exist (requests hit 405),
 *   so a policy method would authorize an operation the app cannot perform.
 *
 * Registered via Laravel auto-discovery (no Gate::define anywhere).
 * Contractor isolation: the actor-side comparison is delegated to
 * TaskScopeService (the canonical contractor scope) — not re-implemented.
 */
class TaskPolicy
{
    public function __construct(
        protected ProjectScopeService $projectScope,
        protected TaskScopeService $taskScope
    ) {}

    // ------------------------------------------------------------------
    // view — tasks.show
    // ------------------------------------------------------------------

    public function view(User $user, Task $task): bool
    {
        return $this->withinScope($user, $task);
    }

    // ------------------------------------------------------------------
    // create — tasks.store (target project passed by the caller)
    // ------------------------------------------------------------------

    public function create(User $user, Project $project): bool
    {
        // Permission first (DEC-054/055 wiring in a later gate uses the same
        // check): only admin/PM hold `create tasks` (InitialTmsSeeder).
        if (! $user->can('create tasks')) {
            return false;
        }

        // Project scope over the TARGET project (no Task model exists yet).
        if (! $this->canAccessProject($user, $project)) {
            return false;
        }

        // Actor-side contractor isolation for the target project's contractor.
        if ($user->contractor_id !== null
            && (int) $project->contractor_id !== (int) $user->contractor_id) {
            return false;
        }

        return true;
    }

    // ------------------------------------------------------------------
    // startProgress — tasks.start
    // ------------------------------------------------------------------

    public function startProgress(User $user, Task $task): bool
    {
        return $this->withinScope($user, $task);
    }

    // ------------------------------------------------------------------
    // submitForReview — tasks.submit
    // ------------------------------------------------------------------

    public function submitForReview(User $user, Task $task): bool
    {
        return $this->withinScope($user, $task);
    }

    // ------------------------------------------------------------------
    // manageStages — tasks.stage (role mw + service guard remain authoritative)
    // ------------------------------------------------------------------

    public function manageStages(User $user, Task $task): bool
    {
        return $this->withinScope($user, $task);
    }

    // ------------------------------------------------------------------
    // assign — tasks.assign (permission + scope; service guards the rest)
    // ------------------------------------------------------------------

    public function assign(User $user, Task $task): bool
    {
        if (! $user->can('assign tasks')) {
            return false;
        }

        return $this->withinScope($user, $task);
    }

    // ------------------------------------------------------------------
    // Shared layer composition
    // ------------------------------------------------------------------

    /**
     * Project Scope AND actor-side contractor isolation for a task.
     * (Assignee-side contractor scope is a Service Guard — TaskAssignmentService.)
     */
    protected function withinScope(User $user, Task $task): bool
    {
        if (! $this->canAccessProject($user, $task->project)) {
            return false;
        }

        return $this->taskScope->canAccess($task, $user);
    }

    protected function canAccessProject(User $user, Project $project): bool
    {
        return $this->projectScope->canAccessProject($project, $user);
    }
}
