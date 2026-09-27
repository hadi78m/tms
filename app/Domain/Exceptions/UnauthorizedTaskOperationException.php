<?php

namespace App\Domain\Exceptions;

class UnauthorizedTaskOperationException extends DomainException
{
    /**
     * DEC-042 (DR-1=A): only a supervisor may assign module_stage_id.
     */
    public static function stageAssignmentDenied(string $role): self
    {
        return new self(sprintf(
            'فقط ناظر (Supervisor) مجاز به تعیین مرحلهٔ تسک است. نقش فعلی: %s.',
            $role
        ));
    }

    /**
     * DEC-043 (DR-2=A): the stage is locked after the task's Final Approval.
     */
    public static function stageLockedAfterFinalApproval(string $status): self
    {
        return new self(sprintf(
            'پس از وضعیت نهایی (%s) تغییر مرحلهٔ تسک مجاز نیست.',
            $status
        ));
    }
}
