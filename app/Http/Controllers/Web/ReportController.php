<?php

namespace App\Http\Controllers\Web;

use App\Domain\Services\ProgressReportService;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports. V1.9 (DEC-039/040/041):
 *
 * - The stage-based progress KPI now reads the authoritative stage source
 *   `SUM(approved_amount)` over ACTIVE approvals (approved + not superseded)
 *   via ProgressReportService. The legacy `SUM(tasks.weight)` reader (TD-1) is
 *   removed from the affected calculation — there is no fallback: if the stage
 *   data cannot be read, the report fails loudly.
 * - The KPI label is «مبلغ تأییدشدهٔ مراحل» (DEC-041). It is an operational
 *   approved amount, never a payable/payment figure.
 * - Task-level reports (total task weight, delayed tasks, CSV exports) are
 *   unrelated to stage progress and remain unchanged (DEC-040 does not touch
 *   them).
 */
class ReportController extends Controller
{
    public function __construct(
        protected ProgressReportService $progressReportService
    ) {}

    public function index(Request $request)
    {
        // V1.10 — DEC-046 (5-1 Project Filter + 5-2 Period Filter).
        $projectId = $request->filled('project_id') ? (int) $request->input('project_id') : null;

        // 5-2: the period is measured on the approval decision date
        // (decided_at) — the only authoritative "when it counted" timestamp.
        $periodFrom = $request->filled('from') ? $request->input('from') : null;
        $periodTo = $request->filled('to') ? $request->input('to') : null;

        $this->assertValidPeriod($periodFrom, $periodTo);

        // Legacy task-level indicators (unchanged in V1.9 — task reports are
        // outside the DEC-040 stage-report scope).
        $totalWeight = Task::sum('weight');

        $delayedTasks = Task::where('status', '!=', 'completed')
            ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
            ->whereNotNull('planned_due_at')
            ->where('planned_due_at', '<', now())
            ->count();

        // V1.9 stage-based progress — approved_amount is the single source.
        // V1.10 (DEC-046): project and period filters are applied in the
        // service; DEC-040 semantics (excluded stage-less count) are untouched.
        $stageProgress = $this->progressReportService->stageProgress(
            $projectId,
            $periodFrom !== null || $periodTo !== null
                ? ['from' => $periodFrom, 'to' => $periodTo]
                : null
        );

        $projects = Project::orderBy('name')->get(['id', 'name']);

        return view('reports.index', [
            'totalWeight' => $totalWeight,
            'delayedTasks' => $delayedTasks,
            'stageProgress' => $stageProgress,
            'projects' => $projects,
            'filters' => [
                'project_id' => $projectId,
                'from' => $periodFrom,
                'to' => $periodTo,
            ],
        ]);
    }

    private function assertValidPeriod(?string $from, ?string $to): void
    {
        foreach (['from' => $from, 'to' => $to] as $label => $value) {
            if ($value !== null && strtotime($value) === false) {
                abort(422, "پارامتر تاریخ {$label} نامعتبر است.");
            }
        }

        if ($from !== null && $to !== null && strtotime($from) > strtotime($to)) {
            abort(422, 'بازهٔ زمانی نامعتبر است: شروع پس از پایان.');
        }
    }

    public function export(Request $request)
    {
        $type = $request->query('type', 'all_tasks');

        // V1.10 — DEC-046 (5-3 Stage CSV): stage-based CSV export, honoring the
        // same project/period filters as the HTML report (DEC-040 semantics).
        if ($type === 'stage_progress') {
            return $this->exportStageProgress($request);
        }

        $response = new StreamedResponse(function () use ($type) {
            $handle = fopen('php://output', 'w');

            // Add UTF-8 BOM for Excel compatibility with Persian
            fwrite($handle, "\xEF\xBB\xBF");

            if ($type === 'approved_tasks') {
                fputcsv($handle, ['شناسه', 'عنوان', 'وزن', 'پروژه', 'پیمانکار', 'تاریخ تایید نهایی']);
                $tasks = Task::with(['project', 'contractor'])->where('status', 'approved')->get();
                foreach ($tasks as $task) {
                    fputcsv($handle, [
                        $task->id,
                        $task->title,
                        $task->weight,
                        $task->project->name ?? '-',
                        $task->contractor->name ?? '-',
                        jdate($task->updated_at)->format('Y/m/d H:i'),
                    ]);
                }
            } else {
                fputcsv($handle, ['شناسه', 'عنوان', 'وزن', 'وضعیت', 'تاریخ شروع', 'مهلت انجام', 'تاخیر']);
                $tasks = Task::all();
                foreach ($tasks as $task) {
                    $delay = ($task->planned_due_at && $task->planned_due_at < now() && $task->status != 'completed') ? 'بله' : 'خیر';
                    fputcsv($handle, [
                        $task->id,
                        $task->title,
                        $task->weight,
                        $task->status,
                        $task->planned_start_at ? jdate($task->planned_start_at)->format('Y/m/d') : '-',
                        $task->planned_due_at ? jdate($task->planned_due_at)->format('Y/m/d') : '-',
                        $delay,
                    ]);
                }
            }
            fclose($handle);
        });

        $filename = "report_{$type}_".jdate()->format('Ymd_His').'.csv';
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }

    private function exportStageProgress(Request $request): StreamedResponse
    {
        $projectId = $request->filled('project_id') ? (int) $request->input('project_id') : null;
        $periodFrom = $request->filled('from') ? $request->input('from') : null;
        $periodTo = $request->filled('to') ? $request->input('to') : null;

        $this->assertValidPeriod($periodFrom, $periodTo);

        $stageProgress = $this->progressReportService->stageProgress(
            $projectId,
            $periodFrom !== null || $periodTo !== null
                ? ['from' => $periodFrom, 'to' => $periodTo]
                : null
        );

        $response = new StreamedResponse(function () use ($stageProgress) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['پروژه', 'ماژول', 'مرحله', 'وزن تخصیص‌یافته', 'مبلغ تأییدشدهٔ مراحل']);

            foreach ($stageProgress['rows'] as $row) {
                fputcsv($handle, [
                    $row['stage']->module->project->name ?? '-',
                    $row['stage']->module->name,
                    $row['stage']->name,
                    number_format($row['allocated'], 2),
                    number_format($row['approved'], 2),
                ]);
            }

            fclose($handle);
        });

        $filename = 'report_stage_progress_'.jdate()->format('Ymd_His').'.csv';
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }
}
