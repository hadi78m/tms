<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * V1.9 — supervisor decision (approve / reject) on a pending stage progress
 * approval. The decision verb is validated by the controller; amount validity
 * against the proposed amount and the cumulative ceiling is owned by
 * StageProgressApprovalService.
 */
class DecideStageProgressApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:approve,reject'],
            // Required only when decision = approve; the service enforces
            // 0 <= approved <= proposed and the cumulative ceiling.
            'approved_amount' => ['required_if:decision,approve', 'nullable', 'numeric', 'gte:0', 'max:100'],
            'reason' => [
                'required_if:decision,reject',
                'nullable',
                'string',
                'max:1000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->input('decision') === 'reject' && trim((string) $value) === '') {
                        $fail('دلیل رد الزامی است.');
                    }
                },
            ],
        ];
    }

    public function isApproval(): bool
    {
        return $this->input('decision') === 'approve';
    }

    public function approvedAmount(): float
    {
        return (float) $this->input('approved_amount');
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? trim((string) $this->input('reason')) : null;
    }
}
