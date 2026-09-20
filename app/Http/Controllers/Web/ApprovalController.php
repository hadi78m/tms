<?php

namespace App\Http\Controllers\Web;

use App\Domain\Enums\ApprovalStatus;
use App\Domain\Enums\ApprovalType;
use App\Domain\Enums\TaskStatus;
use App\Domain\Services\ApprovalService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreApprovalRequest;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;

class ApprovalController extends Controller
{
    public function __construct(
        protected ApprovalService $approvalService
    ) {}

    public function store(StoreApprovalRequest $request, Task $task): RedirectResponse
    {
        $actor = auth()->user();
        $approvalType = $request->input('approval_type');
        $status = $request->input('status');
        $comment = $request->input('comment');

        // بررسی مجوز: تایید فنی تنها توسط کاربران دارای مجوز technical_approval
        if ($approvalType === ApprovalType::Technical->value) {
            if (! $actor->can('technical_approval')) {
                return redirect()->route('tasks.show', $task->id)
                    ->with('error', 'شما مجوز انجام تایید فنی را ندارید.');
            }

            if ($task->status !== TaskStatus::UnderReview->value) {
                return redirect()->route('tasks.show', $task->id)
                    ->with('error', 'تایید فنی تنها در وضعیت «در حال بررسی» ممکن است.');
            }

            $this->approvalService->recordTechnicalApproval($task, $actor, $status, $comment);

            $message = $status === ApprovalStatus::Approved->value
                ? 'تایید فنی (ناظر) با موفقیت ثبت شد. وظیفه به مرحله تایید بهره‌بردار رفت.'
                : 'وظیفه برای اصلاح بازگردانده شد.';

            return redirect()->route('tasks.show', $task->id)->with('status', $message);
        }

        // بررسی مجوز: تایید نهایی تنها توسط کاربران دارای مجوز final_approval
        if ($approvalType === ApprovalType::Final->value) {
            if (! $actor->can('final_approval')) {
                return redirect()->route('tasks.show', $task->id)
                    ->with('error', 'شما مجوز انجام تایید نهایی را ندارید.');
            }

            if ($task->status !== TaskStatus::SupervisorApproved->value) {
                return redirect()->route('tasks.show', $task->id)
                    ->with('error', 'تایید نهایی تنها پس از تایید فنی ناظر ممکن است.');
            }

            $this->approvalService->recordFinalApproval($task, $actor, $status, $comment);

            $message = $status === ApprovalStatus::Approved->value
                ? 'تایید نهایی (بهره‌بردار) با موفقیت ثبت شد. وظیفه به وضعیت «تایید شده» رفت.'
                : 'وظیفه پس از بررسی بهره‌بردار برای اصلاح بازگردانده شد.';

            return redirect()->route('tasks.show', $task->id)->with('status', $message);
        }

        return redirect()->route('tasks.show', $task->id)
            ->with('error', 'نوع تایید نامعتبر است.');
    }
}
