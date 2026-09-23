<?php

namespace App\Domain\Exceptions;

/**
 * The Module stage-weight invariant was violated.
 *
 * Invariant: SUM(module_stages.weight) = 100 per Module.
 */
class StageWeightException extends DomainException
{
    public static function sumMismatch(int $moduleId, float $sum): self
    {
        return new self(sprintf(
            'SUM(module_stages.weight) for module %d is %.2f (expected 100.00).',
            $moduleId,
            $sum
        ));
    }

    public static function outOfRange(float $weight): self
    {
        return new self(sprintf('Stage weight %.2f must be greater than 0 and at most 100.', $weight));
    }
}
