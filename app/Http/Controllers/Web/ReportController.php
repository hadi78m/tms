<?php

namespace App\Http\Controllers\Web;

use App\Domain\Services\ProgressReportService;
use App\Http\Controllers\Controller;
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

    public function index()
    {
        // Legacy task-level indicators (unchanged in V1.9 — task reports are
        // outside the DEC-040 stage-report scope).
        $totalWeight = Task::sum('weight');

        $delayedTasks = Task::where('status', '!=', 'completed')
            ->whereNotNull('planned_due_at')
            ->where('planned_due_at', '<', now())
            ->count();

        // V1.9 stage-based progress — approved_amount is the single source.
        $stageProgress = $this->progressReportService->stageProgress(null);

        return view('reports.index', [
            'totalWeight' => $totalWeight,
            'delayedTasks' => $delayedTasks,
            'stageProgress' => $stageProgress,
        ]);
    }

    public function export(Request $request)
    {
        $type = $request->query('type', 'all_tasks');

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
}
