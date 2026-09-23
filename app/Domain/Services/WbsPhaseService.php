<?php

namespace App\Domain\Services;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\Enums\WbsPhaseCompletionStatus;
use App\Domain\Exceptions\WbsPhaseTransitionException;
use App\Models\Project;
use App\Models\User;
use App\Models\WbsPhase;
use App\Models\WbsPhaseChecklistItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Owns WBS Phase completion and its checklist.
 *
 * A WBS Phase has NO business weight and is never part of a weight calculation
 * — it only has a time window, an expected output, a checklist and a
 * Boolean/status completion whose final authority is the Project Supervisor.
 *
 * Checklist items carry no computational weight either: they exist purely to
 * say complete / incomplete, by whom and when.
 */
class WbsPhaseService
{
    /**
     * Fixed text required by DEC-017 for new phases, because
     * `wbs_phases.expected_output` is still NOT NULL and remains LEGACY. The
     * real output contract of a phase is its checklist.
     */
    public const DEFAULT_EXPECTED_OUTPUT = 'مشاهدهٔ Checklist';

    public function __construct(
        protected AuditServiceInterface $auditService
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createPhase(Project $project, array $attributes, User $actor): WbsPhase
    {
        return DB::transaction(function () use ($project, $attributes) {
            $attributes['project_id'] = $project->id;
            $attributes['expected_output'] = $attributes['expected_output'] ?? self::DEFAULT_EXPECTED_OUTPUT;
            $attributes['completion_status'] = WbsPhaseCompletionStatus::Pending->value;

            return WbsPhase::create($attributes);
        });
    }

    public function addChecklistItem(
        WbsPhase $phase,
        User $actor,
        string $title,
        ?string $description = null,
        int $sortOrder = 0
    ): WbsPhaseChecklistItem {
        return WbsPhaseChecklistItem::create([
            'wbs_phase_id' => $phase->id,
            'title' => $title,
            'description' => $description,
            'is_completed' => false,
            'sort_order' => $sortOrder,
        ]);
    }

    public function completeChecklistItem(WbsPhaseChecklistItem $item, User $actor): WbsPhaseChecklistItem
    {
        return DB::transaction(function () use ($item, $actor) {
            $item->is_completed = true;
            $item->completed_at = now();
            $item->completed_by = $actor->id;
            $item->save();

            $this->auditService->log(
                'wbs_phase_checklist_item_completed',
                $item,
                $actor,
                ['is_completed' => false, 'completed_at' => null, 'completed_by' => null],
                ['is_completed' => true, 'completed_at' => $item->completed_at?->toIso8601String(), 'completed_by' => $actor->id]
            );

            return $item;
        });
    }

    public function reopenChecklistItem(WbsPhaseChecklistItem $item, User $actor, ?string $reason = null): WbsPhaseChecklistItem
    {
        return DB::transaction(function () use ($item, $actor, $reason) {
            $old = [
                'is_completed' => (bool) $item->is_completed,
                'completed_at' => $item->completed_at?->toIso8601String(),
                'completed_by' => $item->completed_by,
            ];

            $item->is_completed = false;
            $item->completed_at = null;
            $item->completed_by = null;
            $item->save();

            $this->auditService->log(
                'wbs_phase_checklist_item_reopened',
                $item,
                $actor,
                $old,
                ['is_completed' => false, 'reason' => $reason]
            );

            return $item;
        });
    }

    /**
     * Supervisor declares the phase completed.
     *
     * Completion is independent of task approval state: every task of the phase
     * may be approved while the phase is still deliberately left unapproved.
     * The Supervisor is the final authority, so no checklist completeness rule
     * is imposed here.
     */
    public function complete(
        WbsPhase $phase,
        User $supervisor,
        ?string $comment = null,
        ?Carbon $actualCompletion = null
    ): WbsPhase {
        return DB::transaction(function () use ($phase, $supervisor, $comment, $actualCompletion) {
            $this->assertPending($phase);

            $phase->completion_status = WbsPhaseCompletionStatus::Completed->value;
            $phase->actual_completion = $actualCompletion ?? now();
            $phase->supervisor_comment = $comment;
            $phase->supervisor_approved_at = now();
            $phase->supervisor_approved_by = $supervisor->id;
            $phase->save();

            $this->auditService->log(
                'wbs_phase_completed',
                $phase,
                $supervisor,
                ['completion_status' => WbsPhaseCompletionStatus::Pending->value],
                [
                    'completion_status' => WbsPhaseCompletionStatus::Completed->value,
                    'actual_completion' => $phase->actual_completion?->toIso8601String(),
                    'comment' => $comment,
                ]
            );

            return $phase;
        });
    }

    /**
     * Supervisor declares the phase not completed. A written reason is
     * mandatory — enforced here and by `chk_wbs_rejection_reason`.
     */
    public function markNotCompleted(WbsPhase $phase, User $supervisor, string $comment): WbsPhase
    {
        if (trim($comment) === '') {
            throw WbsPhaseTransitionException::reasonRequired();
        }

        return DB::transaction(function () use ($phase, $supervisor, $comment) {
            $this->assertPending($phase);

            $phase->completion_status = WbsPhaseCompletionStatus::NotCompleted->value;
            $phase->supervisor_comment = $comment;
            $phase->supervisor_approved_at = now();
            $phase->supervisor_approved_by = $supervisor->id;
            $phase->save();

            $this->auditService->log(
                'wbs_phase_not_completed',
                $phase,
                $supervisor,
                ['completion_status' => WbsPhaseCompletionStatus::Pending->value],
                ['completion_status' => WbsPhaseCompletionStatus::NotCompleted->value, 'reason' => $comment]
            );

            return $phase;
        });
    }

    /**
     * Reopens a decided phase, returning it to `pending` and clearing the
     * supervisor decision columns. The previous decision survives in the audit
     * trail's old_values.
     */
    public function reopen(WbsPhase $phase, User $supervisor, ?string $reason = null): WbsPhase
    {
        return DB::transaction(function () use ($phase, $supervisor, $reason) {
            if ($phase->completion_status === WbsPhaseCompletionStatus::Pending->value) {
                throw WbsPhaseTransitionException::alreadyPending($phase->id);
            }

            $old = [
                'completion_status' => $phase->completion_status,
                'supervisor_approved_at' => $phase->supervisor_approved_at?->toIso8601String(),
                'supervisor_approved_by' => $phase->supervisor_approved_by,
                'actual_completion' => $phase->actual_completion?->toIso8601String(),
            ];

            $phase->completion_status = WbsPhaseCompletionStatus::Pending->value;
            $phase->actual_completion = null;
            $phase->supervisor_comment = null;
            $phase->supervisor_approved_at = null;
            $phase->supervisor_approved_by = null;
            $phase->save();

            $this->auditService->log(
                'wbs_phase_reopened',
                $phase,
                $supervisor,
                $old,
                ['completion_status' => WbsPhaseCompletionStatus::Pending->value, 'reason' => $reason]
            );

            return $phase;
        });
    }

    private function assertPending(WbsPhase $phase): void
    {
        if ($phase->completion_status !== WbsPhaseCompletionStatus::Pending->value) {
            throw WbsPhaseTransitionException::notPending($phase->id, (string) $phase->completion_status);
        }
    }
}
