<?php

namespace App\Domain\Exceptions;

/**
 * The actor is not allowed to perform this step under the configured
 * `progress_approval_mode` setting.
 *
 * Business rule (BD-05): the Project Supervisor is always the final approver.
 * Who may PROPOSE depends on the mode:
 *   supervisor_only         → supervisor
 *   employer_then_supervisor→ employer
 *   either_then_supervisor  → employer or supervisor
 */
class StageApprovalAuthorizationException extends DomainException
{
    public static function cannotPropose(string $mode, string $role): self
    {
        return new self(sprintf(
            'Role "%s" may not propose a stage progress amount while progress_approval_mode = "%s".',
            $role,
            $mode
        ));
    }

    public static function onlySupervisorMayDecide(string $role): self
    {
        return new self(sprintf(
            'Only a Project Supervisor may approve or reject a stage progress amount (actor role: "%s").',
            $role
        ));
    }

    public static function unknownMode(string $mode): self
    {
        return new self(sprintf('Unknown progress_approval_mode "%s".', $mode));
    }
}
