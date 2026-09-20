<?php

namespace App\Http\Controllers\Web;

use App\Domain\Exceptions\ContractorScopeViolationException;
use App\Domain\Services\TaskAssignmentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\AssignTaskRequest;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;

class TaskAssignmentController extends Controller
{
    public function __construct(
        protected TaskAssignmentService $assignmentService
    ) {}

    public function store(AssignTaskRequest $request, Task $task): RedirectResponse
    {
        $user = auth()->user();

        if ($user->contractor_id && $task->contractor_id !== $user->contractor_id) {
            abort(403, 'شما دسترسی به این وظیفه ندارید.');
        }

        try {
            $this->assignmentService->assign(
                $request->toDto(),
                $user
            );

            return redirect()->route('tasks.show', $task->id)->with('status', 'وظیفه با موفقیت ارجاع داده شد.');
        } catch (ContractorScopeViolationException $e) {
            return redirect()->route('tasks.show', $task->id)->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->route('tasks.show', $task->id)->with('error', $e->getMessage());
        }
    }
}
