<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::withCount('users')
            ->with('permissions:id,name,display_name')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/roles/create', [
            'allPermissions' => Permission::all(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create($request->only('name', 'description'));
        $role->permissions()->sync($request->validated('permissions', []));

        return redirect()->route('admin.roles.edit', $role);
    }

    public function edit(Role $role): Response
    {
        $role->load('permissions');

        return Inertia::render('admin/roles/edit', [
            'role' => $role,
            'allPermissions' => Permission::all(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role->update($request->only('name', 'description'));
        $role->permissions()->sync($request->validated('permissions', []));

        return redirect()->route('admin.roles.edit', $role);
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->isAssigned()) {
            $count = $role->users()->count();

            return back()->withErrors([
                'delete' => "This role is assigned to {$count} user(s). Remove it from those users before deleting.",
            ]);
        }

        $role->delete();

        return redirect()->route('admin.roles');
    }
}
