<?php

namespace App\Domain\Exceptions;

/**
 * V1.11 — V11-01: the actor does not have project scope over the requested
 * project (mirror of ContractorScopeViolationException — C-16).
 */
class ProjectScopeViolationException extends DomainException
{
}
