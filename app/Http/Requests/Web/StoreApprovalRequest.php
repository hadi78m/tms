<?php

namespace App\Http\Requests\Web;

use App\Domain\DTOs\StoreApprovalData;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class StoreApprovalRequest extends FormRequest
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
            'status' => ['required', 'in:approved,rejected'],
            'comments' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toDto(): StoreApprovalData
    {
        return new StoreApprovalData(
            approvableType: Task::class,
            approvableId: $this->route('task')->id,
            status: $this->input('status'),
            approvedById: auth()->id(),
            comments: $this->input('comments')
        );
    }
}
