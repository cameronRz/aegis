<?php

namespace App\Http\Requests;

use App\Concerns\ProfileValidationRules;
use App\Enum\Role;
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
        $viewer = $this->user();
        $userId = $this->route('user')?->id;

        $allowedRoles = $viewer->role === Role::SiteAdmin
            ? array_column(Role::cases(), 'value')
            : [Role::Manager->value, Role::User->value];

        return [
            ...$this->profileRules($userId),
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'permissions' => ['sometimes', 'nullable', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ];
    }
}
