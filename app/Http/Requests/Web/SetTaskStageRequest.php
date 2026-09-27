<?php

namespace App\Http\Requests\Web;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * V1.10 — DEC-042/043: stage assignment / reassignment input.
 *
 * The supervisor role is enforced by DEC-044 at the HTTP layer (route
 * middleware) AND as a business invariant in TaskService::assignStage.
 * module_id is derived from the stage in the service, never accepted as input.
 */
class SetTaskStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Task|null $task */
        $task = $this->route('task');
        $taskId = $task instanceof Task ? $task->id : (int) $task;
        $projectId = $task instanceof Task ? (int) $task->project_id : 0;

        return [
            // null clears the stage link (a stage-less task remains valid per
            // DEC-013/OQ-04=2B); otherwise the stage must belong to the task's
            // own project.
            'module_stage_id' => [
                'nullable',
                'integer',
                Rule::exists('module_stages', 'id')->where(function ($query) use ($projectId) {
                    $query->whereIn('module_id', function ($sub) use ($projectId) {
                        $sub->select('id')->from('modules')->where('project_id', $projectId);
                    });
                }),
            ],
        ];
    }

    public function toStageId(): ?int
    {
        return $this->filled('module_stage_id') ? (int) $this->input('module_stage_id') : null;
    }
}
