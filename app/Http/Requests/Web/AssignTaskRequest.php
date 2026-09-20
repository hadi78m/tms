<?php

namespace App\Http\Requests\Web;

use App\Domain\DTOs\AssignTaskData;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class AssignTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = auth()->user();
        $task = $this->route('task');

        if ($user && $user->contractor_id !== null && $task) {
            $taskModel = $task instanceof Task ? $task : Task::find($task);
            if ($taskModel && $taskModel->contractor_id !== $user->contractor_id) {
                return false;
            }
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'comments' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toDto(): AssignTaskData
    {
        $task = $this->route('task');
        $taskId = $task instanceof Task ? $task->id : (int) $task;

        return new AssignTaskData(
            task_id: $taskId,
            user_id: (int) $this->input('user_id'),
            assigned_by: (int) auth()->id(),
            reason: $this->input('reason') ?? $this->input('comments')
        );
    }
}
