<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * V1.9 — rebalancing stage weights of one module (ModuleStageService::rebalance).
 *
 * The map is a PARTIAL set: only the stages whose weight moves. The service
 * checks the RESULTING total of all stages against 100 and enforces the weight
 * lock (OQ-05 = 3A) inside its own transaction.
 */
class RebalanceStagesRequest extends FormRequest
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
    public function weightsByStageId(): array
    {
        $map = [];

        foreach ((array) $this->input('weights', []) as $stageId => $weight) {
            $map[(int) $stageId] = (float) $weight;
        }

        return $map;
    }
}
