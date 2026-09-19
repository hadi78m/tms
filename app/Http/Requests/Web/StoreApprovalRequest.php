<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class StoreApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approval_type' => ['required', 'in:technical,final'],
            'status'        => ['required', 'in:approved,needs_rework'],
            'comment'       => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'approval_type.required' => 'نوع تایید الزامی است.',
            'approval_type.in'       => 'نوع تایید نامعتبر است.',
            'status.required'        => 'وضعیت تایید الزامی است.',
            'status.in'              => 'وضعیت تایید باید «تایید» یا «نیاز به اصلاح» باشد.',
        ];
    }
}

