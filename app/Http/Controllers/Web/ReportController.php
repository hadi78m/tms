<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index()
    {
        // Sample data for the dashboard
        $approvedWeight = Task::where('status', 'approved')->sum('weight');
        $totalWeight = Task::sum('weight');

        $delayedTasks = Task::where('status', '!=', 'completed')
            ->whereNotNull('planned_due_at')
            ->where('planned_due_at', '<', now())
            ->count();

        return view('reports.index', compact('approvedWeight', 'totalWeight', 'delayedTasks'));
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
