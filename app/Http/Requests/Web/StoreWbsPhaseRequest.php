<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * V1.9 — creating a WBS Phase. The service (WbsPhaseService::createPhase)
 * applies the DEC-017 fixed `expected_output` default and the pending
 * completion status; this request validates the required legacy columns.
 */
class StoreWbsPhaseRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'weight' => ['required', 'numeric', 'gte:0', 'max:100'],
            'planned_duration' => ['required', 'integer', 'gt:0'],
            'duration_unit' => ['required', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function phaseAttributes(): array
    {
        return [
            'name' => trim((string) $this->input('name')),
            'weight' => (float) $this->input('weight'),
            'planned_duration' => (int) $this->input('planned_duration'),
            'duration_unit' => trim((string) $this->input('duration_unit')),
            'start_date' => $this->filled('start_date') ? $this->input('start_date') : null,
            'end_date' => $this->filled('end_date') ? $this->input('end_date') : null,
            'status' => 'active',
        ];
    }
}
