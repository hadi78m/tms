<?php

namespace App\Http\Controllers\Web;

use App\Domain\Exceptions\InvalidApprovalException;
use App\Domain\Exceptions\PendingRequestExistsException;
use App\Domain\Exceptions\StageApprovalAuthorizationException;
use App\Domain\Exceptions\StageProgressCapacityExceededException;
use App\Domain\Services\StageProgressApprovalService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\DecideStageProgressApprovalRequest;
use App\Http\Requests\Web\StoreStageProgressApprovalRequest;
use App\Models\ModuleStage;
use App\Models\StageProgressApproval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * V1.9 (DEC-039) — Stage Approval UI.
 *
 * The full approval state machine (one pending per stage, cumulative ceiling,
 * immutability, supersede chain, mode-based authorization, audit) lives in
 * StageProgressApprovalService. This controller calls it and never reproduces
 * a rule; every domain exception becomes a flash error, never a silent fallback.
 */
class StageProgressApprovalController extends Controller
{
    public function __construct(
        protected StageProgressApprovalService $approvalService
    ) {}

    /**
     * Propose a progress amount for a stage (or supersede an existing row).
     *
     * V1.11 Approval Phase 2 (DEC-056/058 · I-2 · Closure Gate Finding A):
     * resource authorization = StageProgressApprovalPolicy::propose —
     * Role boundary stays in the route middleware (authoritative), the policy
     * adds Project Scope via ProjectScopeService (canonical), and the mode /
     * business invariants remain in StageProgressApprovalService. No stage-
     * progress permission exists and none is invented (DEC-059). The Gate
     * resolves StageProgressApprovalPolicy via the StageProgressApproval
     * resource class; the UNSAVED lookahead instance carries the target
     * stage's module_id so the policy scopes before any row is written.
     */
    public function store(ModuleStage $stage, StoreStageProgressApprovalRequest $request): RedirectResponse
    {
        $this->authorize('propose', new StageProgressApproval(['module_id' => $stage->module_id]));

        $actor = Auth::user();

        try {
            $supersedesId = $request->input('supersedes_approval_id');

            if ($supersedesId !== null && $supersedesId !== '') {
                $source = StageProgressApproval::findOrFail((int) $supersedesId);

                $this->approvalService->supersede($source, $actor, $request->proposedAmount(), $request->reason());
            } else {
                $this->approvalService->propose($stage, $actor, $request->proposedAmount(), $request->reason());
            }
        } catch (StageApprovalAuthorizationException|InvalidApprovalException|PendingRequestExistsException $e) {
            return $this->backToStage($stage, $e->getMessage(), false);
        }

        return $this->backToStage($stage, 'پیشنهاد پیشرفت مرحله با موفقیت ثبت شد.', true);
    }

    /**
     * Adjust the proposed amount of a pending row.
     */
    public function adjust(StageProgressApproval $approval, StoreStageProgressApprovalRequest $request): RedirectResponse
    {
        // V1.11 (I-2): scope via StageProgressApprovalPolicy (see store()).
        $this->authorize('adjust', $approval);

        try {
            $this->approvalService->adjust($approval, Auth::user(), $request->proposedAmount(), $request->reason());
        } catch (StageApprovalAuthorizationException|InvalidApprovalException|PendingRequestExistsException $e) {
            return $this->backToStage($approval->moduleStage, $e->getMessage(), false);
        }

        return $this->backToStage($approval->moduleStage, 'مبلغ پیشنهادی با موفقیت اصلاح شد.', true);
    }

    /**
     * Supervisor decision: approve (with amount) or reject (with reason).
     */
    public function decide(StageProgressApproval $approval, DecideStageProgressApprovalRequest $request): RedirectResponse
    {
        // V1.11 (I-2): role boundary = role:supervisor mw; the policy adds
        // Project Scope (supervisor + foreign-project approval = 403).
        $this->authorize('decide', $approval);

        $supervisor = Auth::user();

        try {
            if ($request->isApproval()) {
                $this->approvalService->approve($approval, $supervisor, $request->approvedAmount(), $request->reason());

                return $this->backToStage($approval->moduleStage, 'تأیید پیشرفت مرحله با موفقیت ثبت شد.', true);
            }

            $this->approvalService->reject($approval, $supervisor, (string) $request->reason());

            return $this->backToStage($approval->moduleStage, 'پیشنهاد پیشرفت مرحله رد شد.', true);
        } catch (StageApprovalAuthorizationException|InvalidApprovalException|PendingRequestExistsException|StageProgressCapacityExceededException $e) {
            return $this->backToStage($approval->moduleStage, $e->getMessage(), false);
        }
    }

    private function backToStage(ModuleStage $stage, string $message, bool $success): RedirectResponse
    {
        return redirect()
            ->route('modules.stages.show', $stage->id)
            ->with($success ? 'status' : 'error', $message);
    }
}
