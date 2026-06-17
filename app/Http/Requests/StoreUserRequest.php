<?php

namespace App\Http\Requests;

use App\Concerns\ProfileValidationRules;
use App\Concerns\ValidatesAssignableRoles;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use ProfileValidationRules;
    use ValidatesAssignableRoles;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowedRoles = array_column($this->user()->assignableTiers(), 'value');

        return [
            ...$this->profileRules(),
            'tier' => ['required', 'string', Rule::in($allowedRoles)],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ];
    }
}
