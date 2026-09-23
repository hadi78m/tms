<?php

namespace App\Http\Requests\Web;

use App\Domain\DTOs\CreateTaskData;
use App\Domain\Enums\TaskPriority;
use App\Domain\Enums\TaskType;
use App\Support\JalaliDate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

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
     * Prepare inputs before validation to normalize Jalali dates.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->filled('planned_start_date')) {
            $merge['planned_start_date'] = JalaliDate::toGregorianDate($this->input('planned_start_date'));
        }

        if ($this->filled('planned_due_date')) {
            $merge['planned_due_date'] = JalaliDate::toGregorianDate($this->input('planned_due_date'));
        }

        if (! empty($merge)) {
            $this->merge($merge);
        }
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
            // T-2-A (DEC-037): task_type is a required, explicit input of Create
            // Task. The DB default 'development' is a migration backfill concern
            // (DEC-016), never a substitute for business input.
            'task_type' => ['required', new Enum(TaskType::class)],
            // R1-F1 (DEC-036): weight stays REQUIRED on the official create path.
            // `tasks.weight` is nullable in the schema (M-07) only for
            // legacy/import scenarios; the HTTP path must never produce NULL.
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
            task_type: TaskType::from($this->input('task_type')),
            weight: (float) $this->input('weight'),
            planned_start_date: $this->input('planned_start_date'),
            planned_due_date: $this->input('planned_due_date'),
            parent_task_id: $this->input('parent_task_id')
        );
    }
}
