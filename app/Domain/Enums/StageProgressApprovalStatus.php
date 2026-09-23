<?php

namespace App\Domain\Enums;

/**
 * Stored status domain of a stage progress approval (DEC-027).
 *
 * `superseded` is deliberately NOT a case here: it is a DERIVED state, defined
 * as "an approved row that another row points at through
 * supersedes_approval_id". Keeping it out of the stored domain is what lets the
 * immutability trigger stay absolute — revoking an approval never requires an
 * UPDATE.
 */
enum StageProgressApprovalStatus: string
{
    case Pending = 'pending';

    case Approved = 'approved';

    case Rejected = 'rejected';

    public function isDecided(): bool
    {
        return $this !== self::Pending;
    }
}
