<?php

namespace App\Domain\DTOs;

/**
 * V1.11 — V11-01 (DEC-048 / OD-1): payload for a Supervisor membership
 * assignment. `assigned_by` is resolved by the caller (controller/command)
 * from a real, authenticated actor — it is never fabricated.
 */
class AssignProjectSupervisorData
{
    public function __construct(
        public int $project_id,
        public int $user_id,
        public int $assigned_by,
        public ?string $reason = null
    ) {}
}
