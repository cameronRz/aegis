<?php

namespace App\Http\Requests;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $allowedRoles = array_column($this->user()->assignableRoles(), 'value');

        return [
            ...$this->profileRules($userId),
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'permission_set_id' => ['nullable', 'integer', 'exists:permission_sets,id'],
        ];
    }
}
