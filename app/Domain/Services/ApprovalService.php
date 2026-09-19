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

    /**
     * Record a technical approval (supervisor only).
     * Transitions task: under_review → supervisor_approved (approve) or → needs_rework.
     */
    public function recordTechnicalApproval(Task $task, User $actor, string $status, ?string $comment = null): Approval
    {
        return DB::transaction(function () use ($task, $actor, $status, $comment) {
            if ($actor->contractor_id !== null) {
                throw new InvalidApprovalException('Contractor cannot perform Technical Approval.');
            }

            if ($task->status === TaskStatus::Approved->value) {
                throw new TaskAlreadyApprovedException('Cannot add approvals to an already approved task.');
            }

            if ($task->status !== TaskStatus::UnderReview->value) {
                throw new InvalidTaskTransitionException(
                    'Technical Approval can only be performed when task is under_review.'
                );
            }

            $sequence = Approval::where('task_id', $task->id)->max('sequence') + 1;

            $approval = Approval::create([
                'task_id' => $task->id,
                'approval_type' => ApprovalType::Technical->value,
                'sequence' => $sequence,
                'status' => $status,
                'requested_at' => now(),
                'acted_by' => $actor->id,
                'acted_at' => now(),
                'comment' => $comment,
            ]);

            if ($status === ApprovalStatus::Approved->value) {
                $task->status = TaskStatus::SupervisorApproved->value;
                $task->supervisor_approved_at = now();
            } elseif ($status === ApprovalStatus::NeedsRework->value) {
                $task->status = TaskStatus::NeedsRework->value;
            }
            $task->save();

            $this->auditService->log('task_approval_recorded', $task, $actor, [], $approval->toArray());

            return $approval;
        });
    }

    /**
     * Record a final approval (employer only).
     * Transitions task: supervisor_approved → approved (approve) or → needs_rework.
     */
    public function recordFinalApproval(Task $task, User $actor, string $status, ?string $comment = null): Approval
    {
        return DB::transaction(function () use ($task, $actor, $status, $comment) {
            if ($actor->contractor_id !== null) {
                throw new InvalidApprovalException('Contractor cannot perform Final Approval.');
            }

            if ($task->status === TaskStatus::Approved->value) {
                throw new TaskAlreadyApprovedException('Cannot add approvals to an already approved task.');
            }

            if ($task->status !== TaskStatus::SupervisorApproved->value) {
                throw new InvalidTaskTransitionException(
                    'Final Approval can only be performed when task is supervisor_approved.'
                );
            }

            $sequence = Approval::where('task_id', $task->id)->max('sequence') + 1;

            $approval = Approval::create([
                'task_id' => $task->id,
                'approval_type' => ApprovalType::Final->value,
                'sequence' => $sequence,
                'status' => $status,
                'requested_at' => now(),
                'acted_by' => $actor->id,
                'acted_at' => now(),
                'comment' => $comment,
            ]);

            if ($status === ApprovalStatus::Approved->value) {
                $task->status = TaskStatus::Approved->value;
                $task->approved_at = now();
            } elseif ($status === ApprovalStatus::NeedsRework->value) {
                $task->status = TaskStatus::NeedsRework->value;
            }
            $task->save();

            $this->auditService->log('task_approval_recorded', $task, $actor, [], $approval->toArray());
            $this->auditService->log('task_final_approved', $task, $actor, [], $approval->toArray());

            return $approval;
        });
    }

    /**
     * Legacy method — routes to the correct tier based on approval_type.
     *
     * @deprecated Use recordTechnicalApproval() or recordFinalApproval() directly.
     */
    public function recordApproval(Task $task, User $actor, string $approvalType, string $status, ?string $comment = null): Approval
    {
        if ($approvalType === ApprovalType::Technical->value) {
            return $this->recordTechnicalApproval($task, $actor, $status, $comment);
        }

        return $this->recordFinalApproval($task, $actor, $status, $comment);
    }
}
