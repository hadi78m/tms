<?php

namespace App\Domain\Exceptions;

/**
 * V1.11 — V11-01: a project membership assignment violated a business rule
 * (unknown user/project, non-active assignee, future-dated assignment,
 * invalid membership type, invalid period).
 */
class InvalidProjectMembershipException extends DomainException
{
}
