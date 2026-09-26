<?php

namespace App\Http\Controllers\Web;

use App\Domain\Exceptions\WbsPhaseTransitionException;
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
 */
class WbsPhaseController extends Controller
{
    public function __construct(
        protected WbsPhaseService $phaseService
    ) {}

    public function index(): View
    {
        $phases = WbsPhase::with(['project', 'checklistItems'])
            ->orderBy('project_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $projects = Project::orderBy('id')->get();

        return view('wbs-phases.index', compact('phases', 'projects'));
    }

    public function show(WbsPhase $phase): View
    {
        $phase->load(['project', 'checklistItems.completedByUser', 'supervisorApprover']);

        return view('wbs-phases.show', compact('phase'));
    }

    public function store(StoreWbsPhaseRequest $request): RedirectResponse
    {
        $project = Project::findOrFail($request->input('project_id'));

        $this->phaseService->createPhase($project, $request->phaseAttributes(), Auth::user());

        return redirect()
            ->route('wbs-phases.index')
            ->with('status', 'فاز WBS با موفقیت ایجاد شد.');
    }

    public function addChecklistItem(WbsPhase $phase, StoreChecklistItemRequest $request): RedirectResponse
    {
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
