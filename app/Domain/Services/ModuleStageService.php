<?php

namespace App\Domain\Services;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\Enums\ModuleStageCode;
use App\Domain\Exceptions\StageWeightException;
use App\Domain\Exceptions\StageWeightLockedException;
use App\Models\Module;
use App\Models\ModuleStage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Owns everything about `module_stages`.
 *
 * Invariant: SUM(module_stages.weight) = 100 per Module, guaranteed only by
 * Service + Transaction + a parent lock on modules.id (DEC-022 · DEC-023).
 * There is no aggregate CHECK and no aggregate trigger — by owner decision
 * (OQ-30 = D).
 */
class ModuleStageService
{
    public function __construct(
        protected AuditServiceInterface $auditService
    ) {}

    /**
     * Creates the nine baseline stages of a Module.
     *
     * MUST be called inside the transaction that creates the Module, so the
     * invariant holds from the instant the Module exists (R-1).
     *
     * @return Collection<int, ModuleStage>
     */
    public function provisionCatalogue(Module $module): Collection
    {
        $now = now();
        $rows = [];

        foreach (ModuleStageCode::catalogue() as $code => $definition) {
            $rows[] = [
                'module_id' => $module->id,
                'stage_code' => $code,
                'name' => $definition['name'],
                'weight' => $definition['weight'],
                'sort_order' => $definition['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        ModuleStage::insert($rows);

        return $module->stages()->get();
    }

    /**
     * Changes the allocated weight of a stage before it is locked.
     *
     * A stage weight is locked as soon as ANY approval row exists for it,
     * pending included (OQ-05 = 3A) — enforced here and by
     * `trg_module_stages_weight_locked`.
     *
     * Because the stage sum must stay 100, a single-stage change is only legal
     * when the module has a single stage or the caller rebalances the rest in
     * the same transaction. That is why the full rebalance entry point below is
     * the supported way to move weight between stages.
     */
    public function changeWeight(ModuleStage $stage, float $newWeight, User $actor): ModuleStage
    {
        return DB::transaction(function () use ($stage, $newWeight, $actor) {
            $this->lockModule($stage->module_id);

            /** @var ModuleStage $locked */
            $locked = ModuleStage::whereKey($stage->id)->lockForUpdate()->firstOrFail();

            if ($locked->isWeightLocked()) {
                throw StageWeightLockedException::forStage($locked->id);
            }

            $newWeight = round($newWeight, 2);
            $this->assertInRange($newWeight);

            $oldWeight = (float) $locked->weight;
            $newSum = round($this->allocatedSum($locked->module_id) - $oldWeight + $newWeight, 2);

            if (abs($newSum - 100.0) > 0.001) {
                throw StageWeightException::sumMismatch($locked->module_id, $newSum);
            }

            $locked->weight = $newWeight;
            $locked->save();

            $this->auditService->log(
                'stage_weight_changed',
                $locked,
                $actor,
                ['weight' => $oldWeight],
                ['weight' => $newWeight, 'module_stage_sum_after' => $newSum]
            );

            return $locked;
        });
    }

    /**
     * Rebalances stages of one module in a single transaction.
     *
     * The map is a PARTIAL set: it names only the stages whose weight moves (for
     * example "take 5 points from coding and give them to analysis"). The rule is
     * therefore checked on the RESULTING total of all stages, not on the sum of
     * the map — otherwise moving weight between two of nine stages would be
     * impossible, exactly like the archive/restore defect found in ModuleService.
     *
     * @param  array<int, float>  $weightsByStageId
     */
    public function rebalance(Module $module, array $weightsByStageId, User $actor): void
    {
        DB::transaction(function () use ($module, $weightsByStageId, $actor) {
            $this->lockModule($module->id);

            $stages = ModuleStage::where('module_id', $module->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $resultingSum = (float) $stages->sum('weight');
            $changes = [];

            foreach ($weightsByStageId as $stageId => $weight) {
                if (! $stages->has($stageId)) {
                    throw new StageWeightException(sprintf('Stage %s does not belong to module %d.', $stageId, $module->id));
                }

                $newWeight = round((float) $weight, 2);
                $this->assertInRange($newWeight);

                /** @var ModuleStage $stage */
                $stage = $stages->get($stageId);
                $oldWeight = (float) $stage->weight;

                if ($oldWeight === $newWeight) {
                    continue;
                }

                $resultingSum += $newWeight - $oldWeight;
                $changes[] = [$stage, $oldWeight, $newWeight];
            }

            $resultingSum = round($resultingSum, 2);

            if (abs($resultingSum - 100.0) > 0.001) {
                throw StageWeightException::sumMismatch($module->id, $resultingSum);
            }

            foreach ($changes as [$stage, $oldWeight, $newWeight]) {
                if ($stage->isWeightLocked()) {
                    throw StageWeightLockedException::forStage($stage->id);
                }

                $stage->weight = $newWeight;
                $stage->save();

                $this->auditService->log(
                    'stage_weight_changed',
                    $stage,
                    $actor,
                    ['weight' => $oldWeight],
                    ['weight' => $newWeight, 'module_stage_sum_after' => $resultingSum]
                );
            }
        });
    }

    public function allocatedSum(int $moduleId): float
    {
        return round((float) ModuleStage::where('module_id', $moduleId)->sum('weight'), 2);
    }

    private function lockModule(int $moduleId): void
    {
        Module::withTrashed()->whereKey($moduleId)->lockForUpdate()->firstOrFail();
    }

    private function assertInRange(float $weight): void
    {
        if ($weight <= 0 || $weight > 100) {
            throw StageWeightException::outOfRange($weight);
        }
    }
}
