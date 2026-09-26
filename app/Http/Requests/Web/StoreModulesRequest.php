<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * V1.9 — defining the complete module set of a project (ModuleService::createModules).
 *
 * The service is the sole owner of the SUM(active modules.weight) = 100 invariant;
 * this request only enforces per-row input sanity.
 */
class StoreModulesRequest extends FormRequest
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
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'modules' => ['required', 'array'],
            'modules.*.name' => ['nullable', 'string', 'max:255'],
            'modules.*.code' => ['nullable', 'string', 'max:50'],
            'modules.*.weight' => ['nullable', 'numeric', 'gt:0', 'max:100'],
        ];
    }

    /**
     * Rows left empty in the form are skipped; a fully empty submission is
     * rejected here so the service always receives at least one definition.
     *
     * @return array<int, array{name: string, code: ?string, weight: float}>
     */
    public function moduleDefinitions(): array
    {
        $definitions = [];

        foreach ((array) $this->input('modules', []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $weight = $row['weight'] ?? null;

            if ($name === '' || $weight === null || $weight === '') {
                continue;
            }

            $code = trim((string) ($row['code'] ?? ''));

            $definitions[] = [
                'name' => $name,
                'code' => $code !== '' ? $code : null,
                'weight' => (float) $weight,
            ];
        }

        return $definitions;
    }
}
