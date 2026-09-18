<?php

namespace App\Domain\Services;

use App\Domain\Exceptions\DuplicatePeriodException;
use App\Domain\Exceptions\InvalidWeightException;
use App\Models\PerformanceRecord;
use Illuminate\Database\QueryException;

class PerformanceRecordService
{
    /**
     * Records performance for a contractor during a specific period.
     *
     * @throws InvalidWeightException
     * @throws DuplicatePeriodException
     */
    public function recordPerformance(
        int $contractId,
        int $contractorId,
        string $periodStart,
        string $periodEnd,
        int $tasksCompleted,
        float $totalWeightCompleted,
        int $reworkCount,
        int $slaBreachCount
    ): PerformanceRecord {
        if ($totalWeightCompleted < 0 || $totalWeightCompleted > 100) {
            throw new InvalidWeightException('Total weight completed must be between 0 and 100.');
        }

        try {
            return PerformanceRecord::create([
                'contract_id' => $contractId,
                'contractor_id' => $contractorId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'tasks_completed' => $tasksCompleted,
                'total_weight_completed' => $totalWeightCompleted,
                'rework_count' => $reworkCount,
                'sla_breach_count' => $slaBreachCount,
            ]);
        } catch (QueryException $e) {
            // PostgreSQL unique constraint violation error code is 23505
            if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'idx_perf_records_unique')) {
                throw new DuplicatePeriodException('A performance record already exists for this contractor in the specified period.');
            }
            throw $e;
        }
    }
}
