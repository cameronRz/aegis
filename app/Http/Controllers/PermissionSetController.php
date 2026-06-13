<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePermissionSetRequest;
use App\Http\Requests\UpdatePermissionSetRequest;
use App\Models\Permission;
use App\Models\PermissionSet;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PermissionSetController extends Controller
{
    public function index(): Response
    {
        $sets = PermissionSet::withCount('userPermissionSets')
            ->with('permissions:id,name,display_name')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/permission-sets/index', [
            'sets' => $sets,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/permission-sets/create', [
            'allPermissions' => Permission::all(),
        ]);
    }

    public function store(StorePermissionSetRequest $request): RedirectResponse
    {
        $set = PermissionSet::create($request->only('name', 'description'));
        $set->permissions()->sync($request->validated('permissions', []));

        return redirect()->route('admin.permission-sets.edit', $set);
    }

    public function edit(PermissionSet $permissionSet): Response
    {
        $permissionSet->load('permissions');

        return Inertia::render('admin/permission-sets/edit', [
            'set' => $permissionSet,
            'allPermissions' => Permission::all(),
        ]);
    }

    public function update(UpdatePermissionSetRequest $request, PermissionSet $permissionSet): RedirectResponse
    {
        $permissionSet->update($request->only('name', 'description'));
        $permissionSet->permissions()->sync($request->validated('permissions', []));

        return redirect()->route('admin.permission-sets.edit', $permissionSet);
    }

    public function destroy(PermissionSet $permissionSet): RedirectResponse
    {
        if ($permissionSet->isAssigned()) {
            $count = $permissionSet->userPermissionSets()->count();

            return back()->withErrors([
                'delete' => "This set is assigned to {$count} user(s). Reassign them before deleting.",
            ]);
        }

        $permissionSet->delete();

        return redirect()->route('admin.permission-sets');
    }
}
