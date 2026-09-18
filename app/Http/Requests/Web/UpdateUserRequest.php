<?php

namespace App\Http\Requests\Web;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole('admin');
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : $user;

        return [
            'name' => ['required', 'string', 'max:255'],
            'national_code' => ['required', 'string', 'size:10', 'regex:/^[0-9]{10}$/', 'unique:users,national_code,'.$userId],
            'mobile' => ['required', 'string', 'size:11', 'regex:/^[0-9]{11}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:6'],
            'contractor_id' => ['nullable', 'exists:synced_contractors,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
