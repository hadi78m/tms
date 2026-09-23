<?php

namespace App\Domain\Exceptions;

/**
 * The cumulative approval ceiling of a stage was exceeded.
 *
 * Rule: SUM(active approved_amount) <= module_stages.weight, where
 * "active" excludes any approved row that a newer row supersedes (DEC-026 · DEC-027).
 */
class StageProgressCapacityExceededException extends DomainException
{
    public static function forStage(int $stageId, float $allocated, float $approved, float $attempted): self
    {
        return new self(sprintf(
            'Stage %d capacity exceeded: allocated %.2f, already approved %.2f, '
            .'attempted %.2f (remaining %.2f).',
            $stageId,
            $allocated,
            $approved,
            $attempted,
            round($allocated - $approved, 2)
        ));
    }
}
