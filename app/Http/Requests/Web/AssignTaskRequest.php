<?php

namespace App\Http\Requests\Web;

use App\Domain\DTOs\AssignTaskData;
use Illuminate\Foundation\Http\FormRequest;

class AssignTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'comments' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toDto(): AssignTaskData
    {
        return new AssignTaskData(
            taskId: $this->route('task')->id,
            userId: $this->input('user_id'),
            assignedById: auth()->id(),
            comments: $this->input('comments')
        );
    }
}
