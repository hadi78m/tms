<?php

namespace App\Domain\Services;

use App\Domain\Contracts\AuditServiceInterface;
use App\Domain\Enums\ModuleStatus;
use App\Domain\Exceptions\ModuleWeightException;
use App\Models\Module;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Owns the project-level module-weight invariant.
 *
 *   Active modules = 0  → VALID            (DEC-024)
 *   Active modules > 0  → SUM(weight) = 100
 *
 * Guaranteed ONLY by Service + Transaction + a parent lock on projects.id
 * (DEC-022 · DEC-023 · DEC-025). There is no aggregate CHECK and no aggregate
 * trigger. Every write path must obey the parent-lock doctrine, otherwise
 * concurrent transactions can produce write skew even though each one is
 * individually valid.
 */
class ModuleService
{
    public function __construct(
        protected AuditServiceInterface $auditService,
        protected ModuleStageService $stageService
    ) {}

    /**
     * Defines the complete module set of a project in ONE transaction (R-1).
     *
     * Legal only while the project has no active module, because a partial set
     * could not satisfy the 100% invariant at COMMIT. An empty definition list
     * is legal: zero active modules is a valid state.
     *
     * Each module is born together with its nine weighted stages, so
     * SUM(module_stages.weight) = 100 is true from the first instant as well.
     *
     * @param  array<int, array{name: string, weight: float|int|string, code?: ?string, description?: ?string, sort_order?: int}>  $definitions
     * @return Collection<int, Module>
     */
    public function createModules(Project $project, array $definitions, User $actor): Collection
    {
        return DB::transaction(function () use ($project, $definitions, $actor) {
            $this->lockProject($project->id);

            if ($this->activeCount($project->id) > 0) {
                throw ModuleWeightException::alreadyDefined($project->id);
            }

            $total = $this->sumOfDefinitions($definitions);

            if ($definitions !== [] && abs($total - 100.0) > 0.001) {
                throw ModuleWeightException::sumMismatch($project->id, $total);
            }

            $modules = new Collection;
            $sortOrder = 0;

            foreach ($definitions as $definition) {
                $sortOrder++;

                $module = Module::create([
                    'project_id' => $project->id,
                    'name' => $definition['name'],
                    'code' => $definition['code'] ?? null,
                    'description' => $definition['description'] ?? null,
                    'weight' => round((float) $definition['weight'], 2),
                    'sort_order' => (int) ($definition['sort_order'] ?? $sortOrder),
                    'status' => ModuleStatus::Active->value,
                ]);

                $this->stageService->provisionCatalogue($module);

                $this->auditService->log(
                    'module_created',
                    $module,
                    $actor,
                    [],
                    $module->toArray() + ['project_module_sum_after' => $total]
                );

                $this->auditService->log(
                    'stage_baseline_defined',
                    $module,
                    $actor,
                    [],
                    ['module_id' => $module->id, 'stage_codes' => $module->stages()->pluck('stage_code')->all()]
                );

                $modules->push($module);
            }

            return $modules;
        });
    }

    /**
     * Applies a complete new weight distribution to the active modules of a
     * project in ONE transaction (R-2). The map must cover every active module.
     *
     * @param  array<int, float|int|string>  $weightsByModuleId
     */
    public function rebalance(Project $project, array $weightsByModuleId, User $actor): void
    {
        DB::transaction(function () use ($project, $weightsByModuleId, $actor) {
            $this->lockProject($project->id);

            $modules = Module::where('project_id', $project->id)
                ->where('status', ModuleStatus::Active->value)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($modules->isEmpty()) {
                throw new ModuleWeightException(sprintf('Project %d has no active module to rebalance.', $project->id));
            }

            if (count($weightsByModuleId) !== $modules->count()) {
                throw ModuleWeightException::sumMismatch($project->id, array_sum(array_map('floatval', $weightsByModuleId)));
            }

            $sum = 0.0;

            foreach ($weightsByModuleId as $moduleId => $weight) {
                if (! $modules->has($moduleId)) {
                    throw new ModuleWeightException(sprintf('Module %s does not belong to project %d.', $moduleId, $project->id));
                }

                $weight = round((float) $weight, 2);

                if ($weight <= 0 || $weight > 100) {
                    throw ModuleWeightException::outOfRange($weight);
                }

                $sum += $weight;
            }

            $sum = round($sum, 2);

            if (abs($sum - 100.0) > 0.001) {
                throw ModuleWeightException::sumMismatch($project->id, $sum);
            }

            $this->applyWeights($project->id, $weightsByModuleId, $actor);
        });
    }

    /**
     * Soft deletes a module and, in the same transaction, rebalances whatever
     * the caller supplies for the modules that stay active (R-4).
     *
     * Reducing the active module count almost always requires rebalancing: with
     * 40 / 35 / 25, removing the 25% module would leave 75% and must therefore
     * either be accompanied by `[$a => 60, $b => 40]` or be refused. Archiving
     * the LAST active module needs no map — zero active modules is valid
     * (DEC-024).
     *
     * @param  array<int, float|int|string>  $remainingWeights  module_id => new weight
     */
    public function archive(Module $module, User $actor, array $remainingWeights = []): void
    {
        DB::transaction(function () use ($module, $actor, $remainingWeights) {
            $this->lockProject($module->project_id);

            $module->delete();

            $this->applyWeights($module->project_id, $remainingWeights, $actor);

            $remainingCount = $this->activeCount($module->project_id);
            $remainingSum = $this->activeWeightSum($module->project_id);

            $this->assertInvariant($module->project_id, $remainingCount, $remainingSum);

            $this->auditService->log(
                'module_archived',
                $module,
                $actor,
                ['deleted_at' => null],
                ['deleted_at' => $module->deleted_at?->toIso8601String(), 'active_module_sum_after' => $remainingSum]
            );
        });
    }

    /**
     * Restores a soft deleted module. The restored module re-enters the
     * aggregate, so the invariant is re-checked and the caller may rebalance the
     * active set in the same transaction (DEC-024).
     *
     * Note this cannot be expressed through rebalance(): rebalance() only sees
     * ACTIVE modules, so it can never bring a module back in.
     *
     * @param  array<int, float|int|string>  $activeWeights  module_id => new weight
     */
    public function restore(Module $module, User $actor, array $activeWeights = []): void
    {
        DB::transaction(function () use ($module, $actor, $activeWeights) {
            $this->lockProject($module->project_id);

            $previousDeletedAt = $module->deleted_at?->toIso8601String();

            $module->restore();

            $this->applyWeights($module->project_id, $activeWeights, $actor);

            $restoredSum = $this->activeWeightSum($module->project_id);

            $this->assertInvariant($module->project_id, $this->activeCount($module->project_id), $restoredSum);

            $this->auditService->log(
                'module_restored',
                $module,
                $actor,
                ['deleted_at' => $previousDeletedAt],
                ['deleted_at' => null, 'active_module_sum_after' => $restoredSum]
            );
        });
    }

    /**
     * Updates non-weight attributes. `weight` is deliberately rejected here:
     * it may only move through rebalance(), which preserves the 100% invariant.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Module $module, array $attributes, User $actor): Module
    {
        if (array_key_exists('weight', $attributes)) {
            throw new ModuleWeightException('Module weight can only be changed through rebalance(), which preserves SUM = 100.');
        }

        return DB::transaction(function () use ($module, $attributes, $actor) {
            $this->lockProject($module->project_id);

            $before = [];

            foreach (array_keys($attributes) as $key) {
                $before[$key] = $module->getOriginal($key);
            }

            $module->fill($attributes)->save();

            $this->auditService->log(
                'module_updated_sensitive_field',
                $module,
                $actor,
                $before,
                $module->only(array_keys($attributes))
            );

            return $module;
        });
    }

    /**
     * Applies a weight map to the ACTIVE modules of a project and audits each
     * change. The caller is responsible for the transaction and the parent lock;
     * the final invariant check happens after this returns.
     *
     * @param  array<int, float|int|string>  $weightsByModuleId
     */
    private function applyWeights(int $projectId, array $weightsByModuleId, User $actor): void
    {
        if ($weightsByModuleId === []) {
            return;
        }

        $modules = Module::where('project_id', $projectId)
            ->where('status', ModuleStatus::Active->value)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($weightsByModuleId as $moduleId => $weight) {
            if (! $modules->has($moduleId)) {
                throw new ModuleWeightException(sprintf(
                    'Module %s is not an active module of project %d.',
                    $moduleId,
                    $projectId
                ));
            }

            $weight = round((float) $weight, 2);

            if ($weight <= 0 || $weight > 100) {
                throw ModuleWeightException::outOfRange($weight);
            }

            /** @var Module $module */
            $module = $modules->get($moduleId);
            $oldWeight = (float) $module->weight;

            if ($oldWeight === $weight) {
                continue;
            }

            $module->weight = $weight;
            $module->save();

            $this->auditService->log(
                'module_weight_changed',
                $module,
                $actor,
                ['weight' => $oldWeight],
                ['weight' => $weight, 'project_module_sum_after' => 100.00]
            );
        }
    }

    public function activeCount(int $projectId): int
    {
        return Module::where('project_id', $projectId)
            ->where('status', ModuleStatus::Active->value)
            ->count();
    }

    public function activeWeightSum(int $projectId): float
    {
        return round((float) Module::where('project_id', $projectId)
            ->where('status', ModuleStatus::Active->value)
            ->sum('weight'), 2);
    }

    /**
     * @param  array<int, array{name: string, weight: float|int|string, ...}>  $definitions
     */
    private function sumOfDefinitions(array $definitions): float
    {
        $total = 0.0;

        foreach ($definitions as $definition) {
            $weight = round((float) ($definition['weight'] ?? 0), 2);

            if ($weight <= 0 || $weight > 100) {
                throw ModuleWeightException::outOfRange($weight);
            }

            $total += $weight;
        }

        return round($total, 2);
    }

    private function assertInvariant(int $projectId, int $count, float $sum): void
    {
        if ($count === 0) {
            return; // zero active modules is a valid state (DEC-024)
        }

        if (abs($sum - 100.0) > 0.001) {
            throw ModuleWeightException::sumMismatch($projectId, $sum);
        }
    }

    /**
     * Parent lock (DEC-023). Always taken before any module of the project is
     * read or written.
     */
    private function lockProject(int $projectId): void
    {
        Project::withTrashed()->whereKey($projectId)->lockForUpdate()->firstOrFail();
    }
}
