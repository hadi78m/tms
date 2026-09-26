<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * V1.9 — rebalancing the weights of a project's active modules
 * (ModuleService::rebalance). The map must cover every active module; the
 * service enforces the SUM = 100 invariant inside its own transaction.
 */
class RebalanceModulesRequest extends FormRequest
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
            'weights' => ['required', 'array'],
            'weights.*' => ['required', 'numeric', 'gt:0', 'max:100'],
        ];
    }

    /**
     * @return array<int, float>
     */
    public function weightsByModuleId(): array
    {
        $map = [];

        foreach ((array) $this->input('weights', []) as $moduleId => $weight) {
            $map[(int) $moduleId] = (float) $weight;
        }

        return $map;
    }
}
