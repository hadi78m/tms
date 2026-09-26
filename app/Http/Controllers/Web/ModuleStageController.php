<?php

namespace App\Http\Controllers\Web;

use App\Domain\Exceptions\StageWeightException;
use App\Domain\Exceptions\StageWeightLockedException;
use App\Domain\Services\ModuleStageService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\RebalanceStagesRequest;
use App\Models\Module;
use App\Models\ModuleStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * V1.9 (DEC-039) — Stage UI + Stage Weight Management.
 *
 * Stages are a fixed nine-stage catalogue provisioned with the module; there is
 * no create/delete HTTP surface. Weight moves only through
 * ModuleStageService::rebalance() (partial map, resulting sum must stay 100)
 * and is locked once any approval row exists for a stage (OQ-05 = 3A). The
 * service owns the lock, the invariant and the audit — this controller only
 * translates the outcome into flash messages.
 */
class ModuleStageController extends Controller
{
    public function __construct(
        protected ModuleStageService $stageService
    ) {}

    public function show(ModuleStage $stage): View
    {
        $stage->load([
            'module.stages.approvals',
            'approvals.proposer',
            'approvals.finalApprover',
            'tasks',
        ]);

        return view('modules.stages.show', [
            'stage' => $stage,
            'module' => $stage->module,
            'approvedWeight' => $stage->approvedWeight(),
            'remainingWeight' => $stage->remainingWeight(),
            'isWeightLocked' => $stage->isWeightLocked(),
        ]);
    }

    /**
     * Rebalance stage weights of a module (partial map, sum must stay 100).
     */
    public function rebalance(Module $module, RebalanceStagesRequest $request): RedirectResponse
    {
        try {
            $this->stageService->rebalance($module, $request->weightsByStageId(), Auth::user());
        } catch (StageWeightLockedException $e) {
            return redirect()
                ->route('modules.show', $module->project_id)
                ->with('error', 'وزن برخی مراحل پس از اولین ثبت تأیید پیشرفت قفل شده است: '.$e->getMessage());
        } catch (StageWeightException $e) {
            return redirect()
                ->route('modules.show', $module->project_id)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('modules.show', $module->project_id)
            ->with('status', 'توزیع وزن مراحل با موفقیت به‌روزرسانی شد.');
    }
}
