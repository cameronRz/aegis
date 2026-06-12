<?php

namespace App\Http\Controllers;

use App\Enum\Role;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Permission;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Exception\ApiErrorException;

class UserController extends Controller
{
    public function __construct(private readonly StripeService $stripe) {}

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

        return Inertia::render('users/create', [
            'availableRoles' => array_column($viewer->assignableRoles(), 'value'),
            'allPermissions' => $viewer->isAdmin() ? Permission::all() : [],
            'canAssignPermissions' => $viewer->isAdmin(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $viewer = $request->user();

        $user = User::create([
            'first_name' => $request->validated('first_name'),
            'last_name' => $request->validated('last_name'),
            'email' => $request->validated('email'),
            'password' => Str::random(64),
        ]);

        $user->role = Role::from($request->validated('role'));
        $user->email_verified_at = now();

        try {
            $customer = $this->stripe->createCustomer($user);
            $user->stripe_customer_id = $customer->id;
        } catch (ApiErrorException $e) {
            Log::channel('stripe')->error('Failed to create Stripe customer for admin-created user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        $user->save();

        if ($viewer->isAdmin() && $request->filled('permissions')) {
            $pivot = collect($request->validated('permissions'))
                ->mapWithKeys(fn (int $id) => [$id => ['granted_by' => $viewer->id]]);

            $user->permissions()->attach($pivot);
        }

        Password::broker()->sendResetLink(['email' => $user->email]);

        return redirect()->route('admin.users.show', $user);
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        $user->load('permissions');

        $viewer = $request->user();

        return Inertia::render('users/edit', [
            'user' => $user,
            'availableRoles' => array_column($viewer->assignableRoles(), 'value'),
            'allPermissions' => $viewer->isAdmin() ? Permission::all() : [],
            'canAssignPermissions' => $viewer->isAdmin(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $viewer = $request->user();

        $user->first_name = $request->validated('first_name');
        $user->last_name = $request->validated('last_name');
        $user->email = $request->validated('email');
        $user->role = Role::from($request->validated('role'));
        $user->save();

        if ($viewer->isAdmin()) {
            $user->load('permissions');

            $existingGrantedBy = $user->permissions->keyBy('id')->map(fn ($p) => $p->pivot->granted_by);

            $pivot = collect($request->validated('permissions', []))
                ->mapWithKeys(fn (int $id) => [
                    $id => ['granted_by' => $existingGrantedBy->get($id, $viewer->id)],
                ]);

            $user->permissions()->sync($pivot);
        }

        return redirect()->route('admin.users.show', $user);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('admin.users');
    }

    public function show(Request $request, User $user): Response
    {
        $user->load('permissions');

        $viewer = $request->user();

        return Inertia::render('users/show', [
            'user' => $user,
            'allPermissions' => Permission::all(),
            'canEdit' => Gate::allows('edit_user') && $viewer->can('update', $user),
            'canDelete' => Gate::allows('delete_user') && $viewer->can('delete', $user),
            'canManagePermissions' => $viewer->can('managePermissions', $user),
        ]);
    }
}
