<?php

namespace App\Http\Controllers\Admin;

use App\Enum\Role;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->when(
                auth()->user()->role !== Role::SiteAdmin,
                fn ($query) => $query->where('role', '!=', Role::SiteAdmin->value)
            )
            ->when(
                auth()->user()->role === Role::Manager,
                fn ($query) => $query->where('role', '!=', Role::Admin->value)
            )
            ->when(
                $request->input('search'),
                function ($query, string $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                }
            )
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => $request->only('search'),
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        $user->load('permissions');

        $allPermissions = Permission::all();

        $viewer = $request->user();
        $canManagePermissions = $viewer->id !== $user->id
            && ($viewer->role === Role::SiteAdmin || ($viewer->role === Role::Admin && ! in_array($user->role, [Role::SiteAdmin, Role::Admin], true)));

        return Inertia::render('admin/users/show', [
            'user' => $user,
            'allPermissions' => $allPermissions,
            'canManagePermissions' => $canManagePermissions,
        ]);
    }
}
