<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * V1.9 — proposing / adjusting / superseding a stage progress amount.
 *
 * The action and target row are validated by the controller; this request only
 * carries the amount and optional reason. Amount semantics (0 < amount <= 100,
 * approval ceiling against the allocated stage weight) live exclusively in
 * StageProgressApprovalService.
 */
class StoreStageProgressApprovalRequest extends FormRequest
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
            'proposed_amount' => ['required', 'numeric', 'gt:0', 'max:100'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function proposedAmount(): float
    {
        return (float) $this->input('proposed_amount');
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? trim((string) $this->input('reason')) : null;
    }
}
