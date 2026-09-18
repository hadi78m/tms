<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Task;
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
            // For managers/supervisors, "my tasks" could mean tasks needing review or just total tasks
            $myTasksCount = Task::where('status', 'completed')->count(); // example: needing review
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
