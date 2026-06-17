<?php

namespace App\Http\Requests;

use App\Concerns\ProfileValidationRules;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $allowedRoles = array_column($this->user()->assignableTiers(), 'value');

        return [
            ...$this->profileRules($userId),
            'tier' => ['required', 'string', Rule::in($allowedRoles)],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('role_ids') || $validator->errors()->has('role_ids.*')) {
                    return;
                }

                $roleIds = collect($this->input('role_ids', []))
                    ->filter()
                    ->unique()
                    ->values();

                if ($roleIds->isEmpty()) {
                    return;
                }

                $existingRoleIds = $this->route('user')
                    ? $this->route('user')->roles()->pluck('roles.id')
                    : collect();

                $hasNewUnassignableRole = Role::query()
                    ->with('permissions')
                    ->whereIn('id', $roleIds)
                    ->get()
                    ->contains(fn (Role $role): bool => ! $existingRoleIds->contains($role->id) && ! $this->user()->canAssignRole($role));

                if ($hasNewUnassignableRole) {
                    $validator->errors()->add('role_ids', 'You may only assign roles that do not grant permissions beyond your own.');
                }
            },
        ];
    }
}
