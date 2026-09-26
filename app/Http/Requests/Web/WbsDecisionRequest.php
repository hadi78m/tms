<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * V1.9 — supervisor decision on a WBS Phase (complete / not completed / reopen).
 * A written reason is mandatory for `not_completed` (chk_wbs_rejection_reason).
 */
class WbsDecisionRequest extends FormRequest
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
            'decision' => ['required', 'string', 'in:complete,not_completed,reopen'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function isComplete(): bool
    {
        return $this->input('decision') === 'complete';
    }

    public function isReopen(): bool
    {
        return $this->input('decision') === 'reopen';
    }

    public function comment(): ?string
    {
        return $this->filled('comment') ? trim((string) $this->input('comment')) : null;
    }
}
