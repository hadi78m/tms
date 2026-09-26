<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * V1.9 — updating module non-weight attributes (ModuleService::update).
 *
 * `weight` is deliberately rejected: it may only move through
 * ModuleService::rebalance(), which preserves SUM(active modules.weight) = 100.
 */
class UpdateModuleRequest extends FormRequest
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
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            // Module weight has exactly one legal move path: rebalance(). The
            // service remains the authority for any other caller.
            'weight' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function moduleAttributes(): array
    {
        $code = trim((string) $this->input('code', ''));

        return [
            'name' => trim((string) $this->input('name')),
            'code' => $code !== '' ? $code : null,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
        ];
    }
}
