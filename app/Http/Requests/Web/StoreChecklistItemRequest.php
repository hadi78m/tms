<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * V1.9 — adding a checklist item to a WBS Phase
 * (WbsPhaseService::addChecklistItem). Checklist items carry NO computational
 * weight — they only express complete / incomplete.
 */
class StoreChecklistItemRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function title(): string
    {
        return trim((string) $this->input('title'));
    }

    public function description(): ?string
    {
        return $this->filled('description') ? trim((string) $this->input('description')) : null;
    }
}
