<?php

namespace App\Http\Controllers;

use App\Enum\Role;
use App\Http\Requests\StoreUserRequest;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
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

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(Request $request): Response
    {
        $viewer = $request->user();
        $isAdmin = in_array($viewer->role, [Role::SiteAdmin, Role::Admin], true);

        $availableRoles = $viewer->role === Role::SiteAdmin
            ? array_column(Role::cases(), 'value')
            : [Role::Manager->value, Role::User->value];

        return Inertia::render('users/create', [
            'availableRoles' => $availableRoles,
            'allPermissions' => $isAdmin ? Permission::all() : [],
            'canAssignPermissions' => $isAdmin,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $viewer = $request->user();
        $isAdmin = in_array($viewer->role, [Role::SiteAdmin, Role::Admin], true);

        $user = User::create([
            'first_name' => $request->validated('first_name'),
            'last_name' => $request->validated('last_name'),
            'email' => $request->validated('email'),
            'password' => Str::random(64),
        ]);

        $user->role = Role::from($request->validated('role'));
        $user->email_verified_at = now();
        $user->save();

        if ($isAdmin && $request->filled('permissions')) {
            $pivot = collect($request->validated('permissions'))
                ->mapWithKeys(fn (int $id) => [$id => ['granted_by' => $viewer->id]]);

            $user->permissions()->attach($pivot);
        }

        Password::broker()->sendResetLink(['email' => $user->email]);

        return redirect()->route('admin.users.show', $user);
    }

    public function show(Request $request, User $user): Response
    {
        $user->load('permissions');

        $allPermissions = Permission::all();

        $viewer = $request->user();
        $canManagePermissions = $viewer->id !== $user->id
            && ($viewer->role === Role::SiteAdmin || ($viewer->role === Role::Admin && ! in_array($user->role, [Role::SiteAdmin, Role::Admin], true)));

        return Inertia::render('users/show', [
            'user' => $user,
            'allPermissions' => $allPermissions,
            'canManagePermissions' => $canManagePermissions,
        ]);
    }
}
