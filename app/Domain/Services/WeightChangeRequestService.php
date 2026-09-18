<?php

namespace App\Domain\Services;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\Exceptions\InvalidTaskTransitionException;
use App\Domain\Exceptions\InvalidWeightException;
use App\Domain\Exceptions\PendingRequestExistsException;
use App\Models\Task;
use App\Models\User;
use App\Models\WeightChangeRequest;
use Illuminate\Support\Facades\DB;

class WeightChangeRequestService
{
    public function __construct(
        protected AuditServiceInterface $auditService
    ) {}

    public function requestChange(Task $task, User $actor, float $newWeight, string $reason): WeightChangeRequest
    {
        return DB::transaction(function () use ($task, $actor, $newWeight, $reason) {
            // Cannot have multiple pending requests
            if ($task->weightChangeRequests()->where('status', 'pending')->exists()) {
                throw new PendingRequestExistsException('There is already a pending weight change request for this task.');
            }

            if ($newWeight < 0 || $newWeight > 100) {
                throw new InvalidWeightException('Weight must be between 0 and 100.');
            }

            $request = WeightChangeRequest::create([
                'task_id' => $task->id,
                'requested_by' => $actor->id,
                'old_weight' => $task->weight,
                'new_weight' => $newWeight,
                'reason' => $reason,
                'status' => 'pending',
            ]);

            $this->auditService->log(
                'task_weight_change_requested',
                $task,
                $actor,
                [],
                $request->toArray()
            );

            return $request;
        });
    }

    public function approveRequest(WeightChangeRequest $request, User $actor): void
    {
        DB::transaction(function () use ($request, $actor) {
            // Lock request
            $request = WeightChangeRequest::where('id', $request->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== 'pending') {
                throw new InvalidTaskTransitionException('Only pending requests can be approved.');
            }

            // Lock task
            $task = Task::where('id', $request->task_id)->lockForUpdate()->firstOrFail();

            $oldWeight = $task->weight;
            $newWeight = $request->new_weight;

            $request->status = 'approved';
            $request->approved_by = $actor->id;
            $request->approved_at = now();
            $request->save();

            $task->weight = $newWeight;
            $task->save();

            $this->auditService->log(
                'task_weight_changed',
                $task,
                $actor,
                ['weight' => $oldWeight],
                ['weight' => $newWeight, 'request_id' => $request->id]
            );
        });
    }

    public function rejectRequest(WeightChangeRequest $request, User $actor, string $reason): void
    {
        DB::transaction(function () use ($request, $actor) {
            $request = WeightChangeRequest::where('id', $request->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== 'pending') {
                throw new InvalidTaskTransitionException('Only pending requests can be rejected.');
            }

            $request->status = 'rejected';
            $request->approved_by = $actor->id;
            $request->approved_at = now();
            // Assuming we log rejection reason somewhere, either in the same record or audit
            $request->save();

            // task remains unchanged. Log audit for rejection if needed
            $this->auditService->log(
                'task_weight_change_rejected',
                $request->task,
                $actor,
                [],
                $request->toArray()
            );
        });
    }
}
