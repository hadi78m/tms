<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Support\TaskBoard\BoardColumns;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $myTasksQuery = Task::query();

        if ($user->contractor_id) {
            $myTasksQuery->where('contractor_id', $user->contractor_id);
            $myTasksCount = $myTasksQuery->count();
        } else {
            // Phase 5B-1 — the review queue.
            // 'completed' is NOT a TaskStatus (the enum has 9 real statuses) so
            // this metric was structurally always 0. The statuses whose pending
            // action belongs to a reviewer are exactly the ones the Board groups
            // into its «در انتظار بررسی» column — resolved from that single
            // canonical source instead of a second hard-coded list.
            $myTasksCount = Task::whereIn('status', BoardColumns::statusesOf('review'))->count();
        }

        // Breached SLAs
        $breachedSlapCount = Task::whereHas('slaRecords', function ($q) {
            $q->where('is_breached', true);
        })->when($user->contractor_id, function ($q) use ($user) {
            $q->where('contractor_id', $user->contractor_id);
        })->count();

        // Status distribution
        $statusDistribution = Task::select('status', \DB::raw('count(*) as total'))
            ->when($user->contractor_id, function ($q) use ($user) {
                $q->where('contractor_id', $user->contractor_id);
            })
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return view('dashboard', compact('myTasksCount', 'breachedSlapCount', 'statusDistribution'));
    }
}
