<?php

namespace App\Http\Controllers\Web;

use App\Domain\Services\TaskAssignmentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\AssignTaskRequest;
use App\Models\Task;

class TaskAssignmentController extends Controller
{
    public function __construct(
        protected TaskAssignmentService $assignmentService
    ) {}

    public function store(AssignTaskRequest $request, Task $task)
    {
        $this->assignmentService->assignTask(
            $request->toDto(),
            auth()->user()
        );

        return redirect()->route('tasks.show', $task->id)->with('status', 'وظیفه با موفقیت ارجاع داده شد.');
    }
}
