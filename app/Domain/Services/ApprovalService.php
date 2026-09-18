<?php

namespace App\Domain\Services;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\Enums\ApprovalStatus;
use App\Domain\Enums\ApprovalType;
use App\Domain\Enums\TaskStatus;
use App\Domain\Exceptions\InvalidApprovalException;
use App\Domain\Exceptions\InvalidTaskTransitionException;
use App\Domain\Exceptions\TaskAlreadyApprovedException;
use App\Models\Approval;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    public function __construct(
        protected AuditServiceInterface $auditService
    ) {}

    public function recordApproval(Task $task, User $actor, string $approvalType, string $status, ?string $comment = null): Approval
    {
        return DB::transaction(function () use ($task, $actor, $approvalType, $status, $comment) {
            // Check if task is already approved
            if ($task->status === TaskStatus::Approved->value) {
                throw new TaskAlreadyApprovedException('Cannot add approvals to an already approved task.');
            }

            // Prevent Final Approval by Contractor
            if ($approvalType === ApprovalType::Final->value && $actor->contractor_id !== null) {
                throw new InvalidApprovalException('Contractor cannot perform Final Approval.');
            }

            // Final Approval can only be performed when under_review
            if ($approvalType === ApprovalType::Final->value && $task->status !== TaskStatus::UnderReview->value) {
                throw new InvalidTaskTransitionException('Final Approval can only be performed when task is under_review.');
            }

            $sequence = Approval::where('task_id', $task->id)->max('sequence') + 1;

            $approval = Approval::create([
                'task_id' => $task->id,
                'approval_type' => $approvalType,
                'sequence' => $sequence,
                'status' => $status,
                'requested_at' => now(),
                'acted_by' => $actor->id,
                'acted_at' => now(),
                'comment' => $comment,
            ]);

            if ($approvalType === ApprovalType::Final->value) {
                if ($status === ApprovalStatus::Approved->value) {
                    $task->status = TaskStatus::Approved->value;
                } elseif ($status === ApprovalStatus::NeedsRework->value) {
                    $task->status = TaskStatus::NeedsRework->value;
                }
                $task->save();
            }

            $this->auditService->log(
                'task_approval_recorded',
                $task,
                $actor,
                [],
                $approval->toArray()
            );

            if ($approvalType === ApprovalType::Final->value) {
                $this->auditService->log(
                    'task_final_approved',
                    $task,
                    $actor,
                    [],
                    $approval->toArray()
                );
            }

            return $approval;
        });
    }
}
