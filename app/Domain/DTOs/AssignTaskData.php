<?php

namespace App\Domain\DTOs;

class AssignTaskData
{
    public function __construct(
        public int $task_id,
        public int $user_id,
        public int $assigned_by,
        public ?string $reason = null
    ) {}
}
