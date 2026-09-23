<?php

namespace App\Domain\Exceptions;

/**
 * The Project module-weight invariant was violated.
 *
 * Invariant (BD-01 · DEC-024):
 *   active modules = 0  → VALID
 *   active modules > 0  → SUM(active.weight) MUST = 100
 */
class ModuleWeightException extends DomainException
{
    public static function sumMismatch(int $projectId, float $sum): self
    {
        return new self(sprintf(
            'SUM(active modules.weight) for project %d is %.2f (expected 100.00).',
            $projectId,
            $sum
        ));
    }

    public static function alreadyDefined(int $projectId): self
    {
        return new self(sprintf(
            'Project %d already has active modules; use rebalance() instead of createModules().',
            $projectId
        ));
    }

    public static function outOfRange(float $weight): self
    {
        return new self(sprintf('Module weight %.2f must be greater than 0 and at most 100.', $weight));
    }
}
