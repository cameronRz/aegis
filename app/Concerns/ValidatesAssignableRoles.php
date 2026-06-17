<?php

namespace App\Concerns;

use App\Models\Role;
use Illuminate\Validation\Validator;

trait ValidatesAssignableRoles
{
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

                $hasUnassignableRole = Role::query()
                    ->with('permissions')
                    ->whereIn('id', $roleIds)
                    ->get()
                    ->contains(fn (Role $role): bool => ! $this->user()->canAssignRole($role));

                if ($hasUnassignableRole) {
                    $validator->errors()->add('role_ids', 'You may only assign roles that do not grant permissions beyond your own.');
                }
            },
        ];
    }
}
