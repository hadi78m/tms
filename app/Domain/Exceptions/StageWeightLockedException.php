<?php

namespace App\Domain\Exceptions;

/**
 * `module_stages.weight` is locked after the first approval row exists for that
 * stage — regardless of whether that row is still `pending` (OQ-05 = 3A).
 *
 * Backed by the database too: `trg_module_stages_weight_locked`.
 */
class StageWeightLockedException extends DomainException
{
    public static function forStage(int $stageId): self
    {
        return new self(sprintf(
            'module_stages.weight is locked for stage %d: an approval row already exists.',
            $stageId
        ));
    }
}
