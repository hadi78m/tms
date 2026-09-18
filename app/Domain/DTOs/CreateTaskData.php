<?php

namespace App\Domain\DTOs;

use App\Domain\Enums\TaskPriority;

readonly class CreateTaskData
{
    public function __construct(
        public int $project_id,
        public ?int $wbs_phase_id,
        public string $title,
        public ?string $description,
        public TaskPriority $priority,
        public float $weight,
        public ?string $planned_start_date,
        public ?string $planned_due_date,
        public ?int $parent_task_id
    ) {}
}
