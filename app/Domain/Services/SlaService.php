<?php

namespace App\Domain\Services;

use App\Domain\Enums\SlaEventType;
use App\Domain\Enums\SlaStatus;
use App\Domain\Enums\SlaType;
use App\Domain\Exceptions\SlaAlreadyStartedException;
use App\Domain\Exceptions\SlaAlreadyStoppedException;
use App\Models\SlaRecord;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SlaService
{
    public function startResponseSla(Task $task, ?User $actor = null): SlaRecord
    {
        return DB::transaction(function () use ($task, $actor) {
            $existing = $task->slaRecords()->where('sla_type', SlaType::Response->value)->whereNull('stopped_at')->exists();
            if ($existing) {
                throw new SlaAlreadyStartedException('Response SLA is already active for this task.');
            }

            $sla = SlaRecord::create([
                'task_id' => $task->id,
                'sla_type' => SlaType::Response->value,
                'started_at' => now(),
                'target_duration' => 60, // e.g. 60 mins
                'status' => SlaStatus::Active->value,
            ]);

            $sla->events()->create([
                'event_type' => SlaEventType::Start->value,
                'occurred_at' => now(),
                'user_id' => $actor?->id,
                'reason' => 'Task assigned',
            ]);

            return $sla;
        });
    }

    public function startResolutionSla(Task $task, ?User $actor = null): SlaRecord
    {
        return DB::transaction(function () use ($task, $actor) {
            $existing = $task->slaRecords()->where('sla_type', SlaType::Resolution->value)->whereNull('stopped_at')->exists();
            if ($existing) {
                throw new SlaAlreadyStartedException('Resolution SLA is already active for this task.');
            }

            $sla = SlaRecord::create([
                'task_id' => $task->id,
                'sla_type' => SlaType::Resolution->value,
                'started_at' => now(),
                'target_duration' => 120, // e.g. 120 mins
                'status' => SlaStatus::Active->value,
            ]);

            $sla->events()->create([
                'event_type' => SlaEventType::Start->value,
                'occurred_at' => now(),
                'user_id' => $actor?->id,
                'reason' => 'Task assigned',
            ]);

            return $sla;
        });
    }

    public function recordValidContractorResponse(Task $task, User $actor): void
    {
        $activeAssignment = $task->activeAssignment;
        if (! $activeAssignment || $activeAssignment->user_id !== $actor->id) {
            return; // Not the actively assigned contractor
        }

        try {
            $this->stopResponseSla($task, $actor, 'Valid contractor response received');
        } catch (SlaAlreadyStoppedException $e) {
            // It's fine if it's already stopped, we just ignore subsequent comments
        }
    }

    public function stopResponseSla(Task $task, ?User $actor = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($task, $actor, $reason) {
            $sla = $task->slaRecords()
                ->where('sla_type', SlaType::Response->value)
                ->whereNull('stopped_at')
                ->lockForUpdate()
                ->first();

            if (! $sla) {
                throw new SlaAlreadyStoppedException('No active Response SLA found to stop.');
            }

            $now = now();
            $sla->stopped_at = $now;
            $sla->actual_duration = (int) round(($now->getTimestamp() - $sla->started_at->getTimestamp()) / 60);

            if ($sla->actual_duration > $sla->target_duration) {
                $sla->is_breached = true;
                $sla->breached_at = $now;
            }

            $sla->status = SlaStatus::Stopped->value;
            $sla->save();

            $sla->events()->create([
                'event_type' => SlaEventType::Stop->value,
                'occurred_at' => $now,
                'user_id' => $actor?->id,
                'reason' => $reason,
            ]);
        });
    }

    public function stopResolutionSla(Task $task, ?User $actor = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($task, $actor, $reason) {
            $sla = $task->slaRecords()
                ->where('sla_type', SlaType::Resolution->value)
                ->whereNull('stopped_at')
                ->lockForUpdate()
                ->first();

            if (! $sla) {
                throw new SlaAlreadyStoppedException('No active Resolution SLA found to stop.');
            }

            $now = now();
            $sla->stopped_at = $now;
            $sla->actual_duration = (int) round(($now->getTimestamp() - $sla->started_at->getTimestamp()) / 60);

            if ($sla->actual_duration > $sla->target_duration) {
                $sla->is_breached = true;
                $sla->breached_at = $now;
            }

            $sla->status = SlaStatus::Stopped->value;
            $sla->save();

            $sla->events()->create([
                'event_type' => SlaEventType::Stop->value,
                'occurred_at' => $now,
                'user_id' => $actor?->id,
                'reason' => $reason,
            ]);
        });
    }
}
