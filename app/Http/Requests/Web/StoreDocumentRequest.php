<?php

namespace App\Http\Requests\Web;

use App\Support\JalaliDate;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare inputs before validation to normalize Jalali dates.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('claimed_at')) {
            $this->merge([
                'claimed_at' => JalaliDate::toGregorianDateTime($this->input('claimed_at')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480'], // max 20MB
            'claimed_at' => ['nullable', 'date'],
        ];
    }
}
