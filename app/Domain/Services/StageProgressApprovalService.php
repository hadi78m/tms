<?php

namespace App\Domain\Services;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\Enums\StageProgressApprovalStatus;
use App\Domain\Exceptions\InvalidApprovalException;
use App\Domain\Exceptions\PendingRequestExistsException;
use App\Domain\Exceptions\StageApprovalAuthorizationException;
use App\Domain\Exceptions\StageProgressCapacityExceededException;
use App\Models\ModuleStage;
use App\Models\StageProgressApproval;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Owns the cumulative approval ceiling of a Module Stage.
 *
 * Rule: SUM(active approved_amount) <= module_stages.weight, where an approved
 * row counts as "active" only while no newer row supersedes it (DEC-026 ·
 * DEC-027).
 *
 * The ceiling is enforced with the parent-lock pattern, NEVER with
 * `SELECT SUM(...) FOR UPDATE` — PostgreSQL rejects that combination outright:
 *   "FOR UPDATE is not allowed with aggregate functions"
 *
 * Required pattern, in one transaction:
 *   1. SELECT ... FROM module_stages WHERE id = ? FOR UPDATE   (parent lock)
 *   2. SELECT SUM(...)                                          (no FOR UPDATE)
 *   3. validate
 *   4. insert / transition + audit
 *
 * The parent lock must be taken on EVERY mutating path — propose, adjust,
 * approve, reject and supersede — because a lock taken only on approve() would
 * leave the ceiling breakable from the other four.
 */
class StageProgressApprovalService
{
    public const MODE_SUPERVISOR_ONLY = 'supervisor_only';

    public const MODE_EMPLOYER_THEN_SUPERVISOR = 'employer_then_supervisor';

    public const MODE_EITHER_THEN_SUPERVISOR = 'either_then_supervisor';

    public function __construct(
        protected AuditServiceInterface $auditService,
        protected SettingsService $settingsService
    ) {}

    /**
     * Records a proposed amount. Only one pending proposal may exist per stage.
     */
    public function propose(ModuleStage $stage, User $actor, float $proposedAmount, ?string $reason = null): StageProgressApproval
    {
        return DB::transaction(function () use ($stage, $actor, $proposedAmount, $reason) {
            $locked = $this->lockStage($stage->id);

            $this->authorizeProposer($actor);
            $this->assertProposedAmount($proposedAmount);

            if ($this->pendingQuery($locked->id)->exists()) {
                throw new PendingRequestExistsException('There is already a pending stage progress approval for this stage.');
            }

            $approval = StageProgressApproval::create([
                'module_stage_id' => $locked->id,
                'module_id' => $locked->module_id,
                'proposed_amount' => round($proposedAmount, 2),
                'status' => StageProgressApprovalStatus::Pending->value,
                'proposed_by' => $actor->id,
                'reason' => $reason,
            ]);

            $this->auditService->log(
                'stage_progress_proposed',
                $approval,
                $actor,
                [],
                [
                    'module_stage_id' => $locked->id,
                    'proposed_amount' => (float) $approval->proposed_amount,
                    'status' => StageProgressApprovalStatus::Pending->value,
                ]
            );

            return $approval;
        });
    }

    /**
     * Supervisor (or Employer) adjusts the proposed amount of a pending row.
     */
    public function adjust(StageProgressApproval $approval, User $actor, float $proposedAmount, ?string $reason = null): StageProgressApproval
    {
        return DB::transaction(function () use ($approval, $actor, $proposedAmount, $reason) {
            $locked = $this->lockStage($approval->module_stage_id);

            $row = $this->lockApproval($approval->id);
            $this->assertPending($row);
            $this->assertNotSuperseded($row);
            $this->assertProposedAmount($proposedAmount);

            $oldAmount = (float) $row->proposed_amount;

            $row->proposed_amount = round($proposedAmount, 2);
            $row->reason = $reason ?? $row->reason;
            $row->save();

            $this->auditService->log(
                'stage_progress_adjusted',
                $row,
                $actor,
                ['proposed_amount' => $oldAmount],
                ['proposed_amount' => (float) $row->proposed_amount, 'reason' => $reason, 'stage_weight' => (float) $locked->weight]
            );

            return $row;
        });
    }

    /**
     * Final supervisor decision. The cumulative ceiling is validated against the
     * ACTIVE approved rows only.
     */
    public function approve(StageProgressApproval $approval, User $supervisor, float $approvedAmount, ?string $reason = null): StageProgressApproval
    {
        return DB::transaction(function () use ($approval, $supervisor, $approvedAmount, $reason) {
            $locked = $this->lockStage($approval->module_stage_id);

            $this->authorizeSupervisor($supervisor);

            $row = $this->lockApproval($approval->id);
            $this->assertPending($row);
            $this->assertNotSuperseded($row);

            $approvedAmount = round($approvedAmount, 2);
            $allocated = (float) $locked->weight;

            if ($approvedAmount < 0 || $approvedAmount > $allocated) {
                throw new InvalidApprovalException(sprintf(
                    'Approved amount %.2f must be between 0 and the allocated stage weight %.2f.',
                    $approvedAmount,
                    $allocated
                ));
            }

            if ($approvedAmount > (float) $row->proposed_amount) {
                throw new InvalidApprovalException(sprintf(
                    'Approved amount %.2f may not exceed the proposed amount %.2f.',
                    $approvedAmount,
                    (float) $row->proposed_amount
                ));
            }

            // ② aggregate WITHOUT FOR UPDATE — the parent row is already locked
            $alreadyApproved = $locked->approvedWeight();

            if (round($alreadyApproved + $approvedAmount, 2) - $allocated > 0.001) {
                throw StageProgressCapacityExceededException::forStage($locked->id, $allocated, $alreadyApproved, $approvedAmount);
            }

            $row->status = StageProgressApprovalStatus::Approved->value;
            $row->approved_amount = $approvedAmount;
            $row->final_approved_by = $supervisor->id;
            $row->decided_at = now();

            if ($reason !== null) {
                $row->reason = $reason;
            }

            $row->save();

            $this->auditService->log(
                'stage_progress_approved',
                $row,
                $supervisor,
                ['status' => StageProgressApprovalStatus::Pending->value, 'approved_amount' => null],
                [
                    'status' => StageProgressApprovalStatus::Approved->value,
                    'approved_amount' => $approvedAmount,
                    'cumulative_approved' => round($alreadyApproved + $approvedAmount, 2),
                    'allocated_weight' => $allocated,
                    'reason' => $reason,
                ]
            );

            return $row;
        });
    }

    public function reject(StageProgressApproval $approval, User $supervisor, string $reason): StageProgressApproval
    {
        return DB::transaction(function () use ($approval, $supervisor, $reason) {
            $this->lockStage($approval->module_stage_id);

            $this->authorizeSupervisor($supervisor);

            $row = $this->lockApproval($approval->id);
            $this->assertPending($row);
            $this->assertNotSuperseded($row);

            $row->status = StageProgressApprovalStatus::Rejected->value;
            $row->final_approved_by = $supervisor->id;
            $row->decided_at = now();
            $row->reason = $reason;
            $row->save();

            $this->auditService->log(
                'stage_progress_rejected',
                $row,
                $supervisor,
                ['status' => StageProgressApprovalStatus::Pending->value],
                ['status' => StageProgressApprovalStatus::Rejected->value, 'reason' => $reason]
            );

            return $row;
        });
    }

    /**
     * Corrects a previous decision by adding a NEW row instead of rewriting
     * history. The superseded row is never touched — it simply becomes
     * non-active, because another row now points at it.
     *
     * A correction always EXTENDS THE CHAIN: the row being superseded must not
     * already have a successor. Supersession therefore means REPLACE, never ADD
     * (T-1).
     */
    public function supersede(StageProgressApproval $approval, User $actor, float $proposedAmount, ?string $reason = null): StageProgressApproval
    {
        return DB::transaction(function () use ($approval, $actor, $proposedAmount, $reason) {
            $locked = $this->lockStage($approval->module_stage_id);

            $this->authorizeProposer($actor);
            $this->assertProposedAmount($proposedAmount);

            $source = StageProgressApproval::whereKey($approval->id)->firstOrFail();

            if ($source->module_stage_id !== $locked->id) {
                throw new InvalidApprovalException('The approval being superseded belongs to a different stage.');
            }

            // T-1: supersession must form a CHAIN, never a fork. Without this
            // guard a row whose first successor had already been decided could
            // take a second successor, which turns "correct A to B" into
            // "add B alongside A" and inflates the approved total. PostgreSQL
            // cannot enforce this because the owner accepted the absence of
            // UNIQUE(supersedes_approval_id), so the service is the only guard.
            $this->assertNotSuperseded($source);

            $pendingExists = $this->pendingQuery($locked->id)
                ->when($source->isPending(), fn ($query) => $query->whereKeyNot($source->id))
                ->exists();

            if ($pendingExists) {
                throw new PendingRequestExistsException('There is already a pending stage progress approval for this stage.');
            }

            $replacement = StageProgressApproval::create([
                'module_stage_id' => $locked->id,
                'module_id' => $locked->module_id,
                'proposed_amount' => round($proposedAmount, 2),
                'status' => StageProgressApprovalStatus::Pending->value,
                'proposed_by' => $actor->id,
                'reason' => $reason,
                'supersedes_approval_id' => $source->id,
            ]);

            $this->auditService->log(
                'stage_progress_superseded',
                $replacement,
                $actor,
                ['supersedes_approval_id' => $source->id, 'superseded_status' => $source->status],
                [
                    'supersedes_approval_id' => $source->id,
                    'proposed_amount' => (float) $replacement->proposed_amount,
                    'status' => StageProgressApprovalStatus::Pending->value,
                ]
            );

            return $replacement;
        });
    }

    public function approvedWeight(ModuleStage $stage): float
    {
        return $stage->approvedWeight();
    }

    public function remainingWeight(ModuleStage $stage): float
    {
        return $stage->remainingWeight();
    }

    /**
     * Parent lock — the only lock the ceiling design relies on (DEC-026).
     */
    private function lockStage(int $stageId): ModuleStage
    {
        return ModuleStage::whereKey($stageId)->lockForUpdate()->firstOrFail();
    }

    private function lockApproval(int $approvalId): StageProgressApproval
    {
        return StageProgressApproval::whereKey($approvalId)->lockForUpdate()->firstOrFail();
    }

    /**
     * ACTIVE pending proposals of a stage.
     *
     * Superseded rows are excluded even while they are still `pending`, because
     * supersession is derived (DEC-027) and a superseded proposal is no longer the
     * proposal under consideration. Without this filter, correcting a pending
     * proposal would permanently block every later proposal for that stage.
     */
    private function pendingQuery(int $stageId)
    {
        return StageProgressApproval::where('module_stage_id', $stageId)
            ->where('status', StageProgressApprovalStatus::Pending->value)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('stage_progress_approvals as superseding')
                    ->whereColumn('superseding.supersedes_approval_id', 'stage_progress_approvals.id');
            });
    }

    /**
     * A superseded row is history.
     *
     * Delegates to the single definition on the model (R-3 consolidation);
     * previously a verbatim copy using a standalone query.
     */
    private function isSuperseded(StageProgressApproval $approval): bool
    {
        return $approval->isSuperseded();
    }

    private function assertPending(StageProgressApproval $approval): void
    {
        if (! $approval->isPending()) {
            throw new InvalidApprovalException(sprintf(
                'Approval %d is already decided (status=%s) and is immutable.',
                $approval->id,
                $approval->status
            ));
        }
    }

    /**
     * A superseded row is history: it may never be decided, adjusted or
     * superseded again. This is the single guard that keeps the supersession
     * relation a linear chain (T-1 · DEC-027).
     */
    private function assertNotSuperseded(StageProgressApproval $approval): void
    {
        if ($this->isSuperseded($approval)) {
            throw new InvalidApprovalException(sprintf(
                'Approval %d has been superseded by a newer row; it is history and can no longer be changed or decided.',
                $approval->id
            ));
        }
    }

    private function assertProposedAmount(float $proposedAmount): void
    {
        if ($proposedAmount <= 0 || $proposedAmount > 100) {
            throw new InvalidApprovalException(sprintf('Proposed amount %.2f must be greater than 0 and at most 100.', $proposedAmount));
        }
    }

    private function authorizeProposer(User $actor): void
    {
        $mode = $this->mode();

        $allowed = match ($mode) {
            self::MODE_SUPERVISOR_ONLY => $actor->hasRole('supervisor'),
            self::MODE_EMPLOYER_THEN_SUPERVISOR => $actor->hasRole('employer'),
            self::MODE_EITHER_THEN_SUPERVISOR => $actor->hasRole('employer') || $actor->hasRole('supervisor'),
            default => throw StageApprovalAuthorizationException::unknownMode($mode),
        };

        if (! $allowed) {
            throw StageApprovalAuthorizationException::cannotPropose($mode, $this->roleOf($actor));
        }
    }

    private function authorizeSupervisor(User $actor): void
    {
        if (! $actor->hasRole('supervisor')) {
            throw StageApprovalAuthorizationException::onlySupervisorMayDecide($this->roleOf($actor));
        }
    }

    private function roleOf(User $actor): string
    {
        return $actor->getRoleNames()->first() ?? 'none';
    }

    private function mode(): string
    {
        $mode = $this->settingsService->get('progress_approval_mode', self::MODE_SUPERVISOR_ONLY);

        return is_string($mode) && $mode !== '' ? $mode : self::MODE_SUPERVISOR_ONLY;
    }
}
