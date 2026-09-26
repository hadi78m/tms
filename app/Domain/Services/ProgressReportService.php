<?php

namespace App\Domain\Services;

use App\Models\ModuleStage;
use App\Models\StageProgressApproval;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * V1.9 (DEC-039/040) — read-model for the stage-based progress report.
 *
 * The ONLY progress source is `SUM(approved_amount)` over ACTIVE stage
 * approvals (DEC-026/027 — approved + not superseded), aggregated through the
 * existing `StageProgressApproval::scopeActive()` definition. Legacy
 * `tasks.weight` is not read here (DEC-034 / TD-1).
 *
 * DEC-040: tasks with `module_stage_id IS NULL` are excluded from stage-based
 * aggregation. This is a reporting rule only — such tasks remain valid
 * operational records and are counted separately so the report can say how
 * many were left out instead of silently hiding them.
 *
 * This is a read-only reporting service: no financial calculation exists here.
 * The approved amount is an operational/percentage weight, not a payable sum.
 */
class ProgressReportService
{
    /**
     * Stage-based progress per Module Stage.
     *
     * @param  int|null  $projectId  null = all projects (system-wide report)
     * @return array{
     *     total_allocated: float,
     *     total_approved: float,
     *     excluded_no_stage_tasks: int,
     *     rows: Collection<int, array{
     *         stage: ModuleStage,
     *         approved: float,
     *         allocated: float
     *     }>
     * }
     */
    public function stageProgress(?int $projectId = null): array
    {
        // DEC-040 — stage-based aggregation only covers staged tasks.
        $excludedNoStageTasks = Task::query()
            ->when($projectId !== null, fn ($query) => $query->where('project_id', $projectId))
            ->whereNull('module_stage_id')
            ->count();

        $stageQuery = ModuleStage::query()
            ->join('modules as m', 'm.id', '=', 'module_stages.module_id')
            ->whereNull('m.deleted_at');

        if ($projectId !== null) {
            $stageQuery->where('m.project_id', $projectId);
        }

        $stageIds = $stageQuery
            ->orderBy('m.project_id')
            ->orderBy('m.sort_order')
            ->orderBy('module_stages.sort_order')
            ->selectRaw('module_stages.id as stage_id')
            ->pluck('stage_id');

        if ($stageIds->isEmpty()) {
            return [
                'total_allocated' => 0.0,
                'total_approved' => 0.0,
                'excluded_no_stage_tasks' => $excludedNoStageTasks,
                'rows' => collect(),
            ];
        }

        // Active approved amounts per stage, in one aggregate query. Active =
        // approved AND not superseded (StageProgressApproval::scopeActive).
        $approvedByStage = StageProgressApproval::query()
            ->whereIn('module_stage_id', $stageIds)
            ->active()
            ->groupBy('module_stage_id')
            ->selectRaw('module_stage_id, SUM(approved_amount) as approved_sum')
            ->pluck('approved_sum', 'module_stage_id');

        $stages = ModuleStage::query()
            ->whereIn('module_stages.id', $stageIds)
            ->join('modules as m', 'm.id', '=', 'module_stages.module_id')
            ->join('projects as p', 'p.id', '=', 'm.project_id')
            ->selectRaw('module_stages.*, m.project_id, m.sort_order as module_sort')
            ->orderBy('m.project_id')
            ->orderBy('module_sort')
            ->orderBy('module_stages.sort_order')
            ->with(['module', 'module.project'])
            ->get()
            ->map(function (ModuleStage $stage) use ($approvedByStage): array {
                $approved = round((float) ($approvedByStage[$stage->id] ?? 0), 2);

                return [
                    'stage' => $stage,
                    'approved' => $approved,
                    'allocated' => (float) $stage->weight,
                ];
            });

        return [
            'total_allocated' => round((float) $stages->sum('allocated'), 2),
            'total_approved' => round((float) $stages->sum('approved'), 2),
            'excluded_no_stage_tasks' => $excludedNoStageTasks,
            'rows' => $stages,
        ];
    }
}
