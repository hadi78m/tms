<?php

namespace App\Http\Controllers\Web;

use App\Domain\Exceptions\WbsPhaseTransitionException;
use App\Domain\Rules\ProjectScopeService;
use App\Domain\Services\WbsPhaseService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreChecklistItemRequest;
use App\Http\Requests\Web\StoreWbsPhaseRequest;
use App\Http\Requests\Web\WbsDecisionRequest;
use App\Models\Project;
use App\Models\WbsPhase;
use App\Models\WbsPhaseChecklistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * V1.9 (DEC-039) — WBS Phase / Checklist UI.
 *
 * WBS phases own NO computational weight and are never part of a progress
 * calculation (BD-01); the checklist is the real output contract of a phase
 * (DEC-017). All transitions (complete / not_completed / reopen) are owned by
 * WbsPhaseService with the supervisor as the final authority.
 *
 * This UI is not a second task-management system: it only manages the phase
 * records and their checklists.
 *
 * V1.11 — WBS I-1 (WBS-01..05): Resource Authorization via the dedicated
 * WbsPhasePolicy. Scope is delegated EXCLUSIVELY to ProjectScopeService
 * (WBS-02, fail-closed); role boundaries stay in the route middleware
 * (unchanged); ALL business invariants stay in WbsPhaseService; DEC-052
 * (wbs_phases.weight) is untouched and deferred.
 */
class WbsPhaseController extends Controller
{
    public function __construct(
        protected WbsPhaseService $phaseService,
        protected ProjectScopeService $projectScope
    ) {}

    public function index(): View
    {
        // V1.11 WBS I-1 (WBS-04 = A): Project Scope retrieval filter via the
        // canonical service. Supervisors see only phases of projects with an
        // ACTIVE supervisor membership; admin/management unrestricted (scope
        // bypass ONLY); PM/viewer/employer keep current behavior (null scope);
        // contractor stays blocked by the route middleware. The default
        // project key `project_id` is used unchanged.
        $phases = $this->projectScope
            ->applyProjectScope(WbsPhase::query(), Auth::user())
            ->with(['project', 'checklistItems'])
            ->orderBy('project_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // V1.11 WBS I-1 (WBS-04b = A): the project selector/filter uses the
        // SAME project-scope semantics — a scoped supervisor never sees
        // unrelated projects in the dropdown. Column is `id`: the projects
        // table has no `project_id` column.
        $projects = $this->projectScope
            ->applyProjectScope(Project::query(), Auth::user(), 'id')
            ->orderBy('id')
            ->get();

        return view('wbs-phases.index', compact('phases', 'projects'));
    }

    public function show(WbsPhase $phase): View
    {
        // V1.11 WBS I-1 (WBS-02): WbsPhasePolicy::view — direct project scope,
        // fail-closed when WbsPhase -> Project cannot be resolved.
        $this->authorize('view', $phase);

        $phase->load(['project', 'checklistItems.completedByUser', 'supervisorApprover']);

        return view('wbs-phases.show', compact('phase'));
    }

    public function store(StoreWbsPhaseRequest $request): RedirectResponse
    {
        $project = Project::findOrFail($request->input('project_id'));

        // V1.11 WBS I-1: the request-body project_id is authorized BEFORE
        // creation via WbsPhasePolicy::create — the resolved Project instance
        // is passed through the policy so Project Scope applies. project_id is
        // never trusted as sufficient authorization.
        $this->authorize('create', [WbsPhase::class, $project]);

        $this->phaseService->createPhase($project, $request->phaseAttributes(), Auth::user());

        return redirect()
            ->route('wbs-phases.index')
            ->with('status', 'فاز WBS با موفقیت ایجاد شد.');
    }

    public function addChecklistItem(WbsPhase $phase, StoreChecklistItemRequest $request): RedirectResponse
    {
        // V1.11 WBS I-1 (WBS-05): checklist operations authorize the PARENT
        // WbsPhase resource before the mutation. The phase/item integrity
        // check and all checklist invariants stay in the service unchanged.
        $this->authorize('addChecklistItem', $phase);

        $this->phaseService->addChecklistItem(
            $phase,
            Auth::user(),
            $request->title(),
            $request->description()
        );

        return redirect()
            ->route('wbs-phases.show', $phase->id)
            ->with('status', 'آیتم چک‌لیست با موفقیت اضافه شد.');
    }

    public function completeChecklistItem(WbsPhase $phase, WbsPhaseChecklistItem $item): RedirectResponse
    {
        // V1.11 WBS I-1 (WBS-05): parent-resource authorization first; the
        // existing phase/item integrity abort(404) remains UNCHANGED.
        $this->authorize('completeChecklistItem', $phase);

        if ($item->wbs_phase_id !== $phase->id) {
            abort(404, 'این آیتم متعلق به این فاز نیست.');
        }

        $this->phaseService->completeChecklistItem($item, Auth::user());

        return redirect()
            ->route('wbs-phases.show', $phase->id)
            ->with('status', 'آیتم چک‌لیست تکمیل شد.');
    }

    public function reopenChecklistItem(WbsPhase $phase, WbsPhaseChecklistItem $item): RedirectResponse
    {
        // V1.11 WBS I-1 (WBS-05): parent-resource authorization first; the
        // existing phase/item integrity abort(404) remains UNCHANGED.
        $this->authorize('reopenChecklistItem', $phase);

        if ($item->wbs_phase_id !== $phase->id) {
            abort(404, 'این آیتم متعلق به این فاز نیست.');
        }

        $this->phaseService->reopenChecklistItem($item, Auth::user());

        return redirect()
            ->route('wbs-phases.show', $phase->id)
            ->with('status', 'آیتم چک‌لیست بازگشایی شد.');
    }

    /**
     * Supervisor decision on the phase (complete / not_completed / reopen).
     */
    public function decide(WbsPhase $phase, WbsDecisionRequest $request): RedirectResponse
    {
        // V1.11 WBS I-1 (WBS-03/03b): WbsPhasePolicy::decide — role mirror of
        // `role:supervisor` + Project Scope (active supervisor membership in
        // the phase's project). Admin remains blocked (WBS-03b); all phase
        // transition invariants stay in WbsPhaseService.
        $this->authorize('decide', $phase);

        $supervisor = Auth::user();

        try {
            if ($request->isReopen()) {
                $this->phaseService->reopen($phase, $supervisor, $request->comment());

                return redirect()
                    ->route('wbs-phases.show', $phase->id)
                    ->with('status', 'فاز WBS بازگشایی شد.');
            }

            if ($request->isComplete()) {
                $this->phaseService->complete($phase, $supervisor, $request->comment());

                return redirect()
                    ->route('wbs-phases.show', $phase->id)
                    ->with('status', 'فاز WBS تکمیل‌شده اعلام شد.');
            }

            $this->phaseService->markNotCompleted($phase, $supervisor, (string) $request->comment());

            return redirect()
                ->route('wbs-phases.show', $phase->id)
                ->with('status', 'فاز WBS تکمیل‌نشده ثبت شد.');
        } catch (WbsPhaseTransitionException $e) {
            return redirect()
                ->route('wbs-phases.show', $phase->id)
                ->with('error', $e->getMessage());
        }
    }
}
