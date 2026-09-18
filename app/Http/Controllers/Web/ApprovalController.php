<?php

namespace App\Http\Controllers\Web;

use App\Domain\Services\ApprovalService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreApprovalRequest;
use App\Models\Task;

class ApprovalController extends Controller
{
    public function __construct(
        protected ApprovalService $approvalService
    ) {}

    public function store(StoreApprovalRequest $request, Task $task)
    {
        $this->approvalService->processApproval(
            $request->toDto(),
            auth()->user()
        );

        $statusMessage = $request->input('status') === 'approved'
            ? 'وظیفه تایید شد.'
            : 'وضعیت وظیفه به رد شده تغییر یافت.';

        return redirect()->route('tasks.show', $task->id)->with('status', $statusMessage);
    }
}
