<?php

namespace App\Domain\Exceptions;

/**
 * A WBS Phase completion transition was requested from an illegal state.
 */
class WbsPhaseTransitionException extends DomainException
{
    public static function alreadyPending(int $phaseId): self
    {
        return new self(sprintf('WBS phase %d is already pending a supervisor decision.', $phaseId));
    }

    public static function notPending(int $phaseId, string $status): self
    {
        return new self(sprintf('WBS phase %d is not pending (status=%s); reopen it first.', $phaseId, $status));
    }

    public static function reasonRequired(): self
    {
        return new self('A supervisor comment is mandatory when declaring a WBS phase not completed.');
    }
}
