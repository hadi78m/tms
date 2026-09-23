<?php

namespace App\Domain\Enums;

/**
 * The authoritative Business Truth for what kind of Task this is.
 *
 * A Task must never be typed solely from its `module_stage_id`: a Support Task
 * may exist with both `module_stage_id` and `wbs_phase_id` NULL and still be a
 * perfectly valid Support Task (BD-02 · OQ-04 = 2B).
 *
 * @see \App\Models\Task
 */
enum TaskType: string
{
    case Development = 'development';

    case Support = 'support';

    /**
     * A Support Task is independent of the Module/Stage/WBS-Phase hierarchy.
     */
    public function requiresModuleStage(): bool
    {
        return $this === self::Development;
    }
}
