<?php

namespace App\Http\Controllers\Web;

use App\Domain\Enums\ApprovalStatus;
use App\Domain\Enums\ApprovalType;
use App\Models\Approval;
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

        // V1.11 Approval Phase 2 (DEC-058/059 · I-2): resource authorization
        // = ApprovalPolicy (Role + Permission + Project Scope). The former
        // raw ->can('technical_approval'/'final_approval') checks are fully
        // covered by the policy's permission layer — no duplicate check.
        // The Gate resolves ApprovalPolicy via the Approval resource class
        // (explicit registration); the UNSAVED lookahead instance carries
        // the target task so the policy scopes against the TARGET project
        // before any row is written. No controller scope logic is added;
        // the cross-project HTTP matrix is completed in I-3.
        $this->authorize(
            $approvalType === ApprovalType::Final->value ? 'createFinal' : 'createTechnical',
            new Approval(['task_id' => $task->id])
        );

        // بررسی مجوز (V1.11 I-2: توسط ApprovalPolicy::createTechnical پوشش داده می‌شود —
        // Role + permission + Project Scope)
        if ($approvalType === ApprovalType::Technical->value) {
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

        // بررسی مجوز (V1.11 I-2: توسط ApprovalPolicy::createFinal پوشش داده می‌شود —
        // Role + permission + Project Scope)
        if ($approvalType === ApprovalType::Final->value) {
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
