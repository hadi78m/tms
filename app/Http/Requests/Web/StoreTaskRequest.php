<?php

namespace App\Http\Requests\Web;

use App\Domain\DTOs\CreateTaskData;
use App\Domain\Enums\TaskPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'wbs_phase_id' => ['nullable', 'integer', 'exists:wbs_phases,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'string', Rule::in(array_column(TaskPriority::cases(), 'value'))],
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'planned_start_date' => ['nullable', 'date'],
            'planned_due_date' => ['nullable', 'date'],
            'parent_task_id' => ['nullable', 'integer', 'exists:tasks,id'],
        ];
    }

    public function toDto(): CreateTaskData
    {
        return new CreateTaskData(
            project_id: $this->input('project_id'),
            wbs_phase_id: $this->input('wbs_phase_id'),
            title: $this->input('title'),
            description: $this->input('description'),
            priority: TaskPriority::from($this->input('priority')),
            weight: (float) $this->input('weight'),
            planned_start_date: $this->input('planned_start_date'),
            planned_due_date: $this->input('planned_due_date'),
            parent_task_id: $this->input('parent_task_id')
        );
    }
}
