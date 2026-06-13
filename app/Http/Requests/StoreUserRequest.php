<?php

namespace App\Http\Requests;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowedRoles = array_column($this->user()->assignableRoles(), 'value');

        return [
            ...$this->profileRules(),
            'role' => ['required', 'string', Rule::in($allowedRoles)],
        ];
    }
}
