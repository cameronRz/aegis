<?php

namespace App\Http\Controllers;

use App\Enum\Tier;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
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
            ->with('roles')
            ->visibleTo(auth()->user())
            ->search($request->input('search'))
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => $request->only('search'),
            'roles' => Role::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function trash(Request $request): Response
    {
        $users = User::onlyTrashed()
            ->visibleTo(auth()->user())
            ->search($request->input('search'))
            ->latest('deleted_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('users/trash', [
            'users' => $users,
            'filters' => $request->only('search'),
        ]);
    }

    public function restore(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->restore();

        return redirect()->route('admin.users.trash');
    }

    public function forceDestroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->forceDelete();

        return redirect()->route('admin.users.trash');
    }

    public function create(Request $request): Response
    {
        $viewer = $request->user();

        return Inertia::render('users/create', [
            'availableTiers' => array_column($viewer->assignableTiers(), 'value'),
            'roles' => Role::query()
                ->orderBy('name')
                ->with('permissions')
                ->get(['id', 'name'])
                ->filter(fn (Role $role): bool => $viewer->canAssignRole($role))
                ->values(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            'first_name' => $request->validated('first_name'),
            'last_name' => $request->validated('last_name'),
            'email' => $request->validated('email'),
            'password' => Str::random(64),
        ]);

        $user->tier = Tier::from($request->validated('tier'));
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

        $user->roles()->syncWithPivotValues(
            $request->validated('role_ids', []),
            ['assigned_by' => $request->user()->id]
        );

        Password::broker()->sendResetLink(['email' => $user->email]);

        return redirect()->route('admin.users.show', $user);
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        $viewer = $request->user();
        $user->loadMissing('roles');

        return Inertia::render('users/edit', [
            'user' => $user,
            'availableTiers' => array_column($viewer->assignableTiers(), 'value'),
            'roles' => Role::query()
                ->orderBy('name')
                ->with('permissions')
                ->get(['id', 'name'])
                ->filter(fn (Role $role): bool => $viewer->canAssignRole($role))
                ->values(),
            'selectedRoleIds' => $user->roles->pluck('id')->toArray(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $user->first_name = $request->validated('first_name');
        $user->last_name = $request->validated('last_name');
        $user->email = $request->validated('email');
        $user->tier = Tier::from($request->validated('tier'));
        $user->save();

        $preservedRolePayload = $user->roles()
            ->with('permissions')
            ->get()
            ->reject(fn (Role $role): bool => $request->user()->canAssignRole($role))
            ->mapWithKeys(fn (Role $role): array => [
                $role->id => ['assigned_by' => $role->pivot->assigned_by],
            ]);

        $submittedRolePayload = collect($request->validated('role_ids', []))
            ->unique()
            ->mapWithKeys(fn ($roleId): array => [
                (int) $roleId => ['assigned_by' => $request->user()->id],
            ]);

        $user->roles()->sync($preservedRolePayload->union($submittedRolePayload)->all());

        return redirect()->route('admin.users.show', $user);
    }

    public function bulkAssignRoles(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $viewer = $request->user();
        $roles = Role::query()
            ->with('permissions')
            ->whereIn('id', $validated['role_ids'])
            ->get();

        if ($roles->contains(fn (Role $role): bool => ! $viewer->canAssignRole($role))) {
            return back()->withErrors([
                'role_ids' => 'You may only assign roles that do not grant permissions beyond your own.',
            ]);
        }

        foreach ($validated['user_ids'] as $userId) {
            $user = User::find($userId);

            if (! $user || ! $viewer->can('update', $user)) {
                continue;
            }

            $user->roles()->syncWithoutDetaching(
                collect($validated['role_ids'])->mapWithKeys(fn ($id) => [$id => ['assigned_by' => $viewer->id]])
            );
        }

        return redirect()->route('admin.users');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('admin.users');
    }

    public function show(Request $request, User $user): Response
    {
        $user->load('roles.permissions');

        $viewer = $request->user();

        return Inertia::render('users/show', [
            'user' => $user,
            'canEdit' => Gate::allows('edit_user') && $viewer->can('update', $user),
            'canDelete' => Gate::allows('delete_user') && $viewer->can('delete', $user),
        ]);
    }
}
