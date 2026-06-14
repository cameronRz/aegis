<?php

use App\Enum\Tier;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->create(['tier' => Tier::Admin]);
    $this->user = User::factory()->create(['tier' => Tier::User]);
    $this->permission = Permission::create([
        'name' => 'view_users',
        'display_name' => 'View Users',
        'description' => null,
    ]);
});

// --- index ---

it('redirects guests from the index', function () {
    get('/admin/roles')->assertRedirect('/login');
});

it('forbids non-admins from the index', function () {
    actingAs($this->user)->get('/admin/roles')->assertForbidden();
});

it('renders the roles index', function () {
    Role::factory()->create(['name' => 'Support Staff']);

    actingAs($this->admin)
        ->get('/admin/roles')
        ->assertInertia(fn ($page) => $page
            ->component('admin/roles/index')
            ->has('roles.data', 1)
        );
});

// --- create ---

it('renders the create page with all permissions', function () {
    actingAs($this->admin)
        ->get('/admin/roles/create')
        ->assertInertia(fn ($page) => $page
            ->component('admin/roles/create')
            ->has('allPermissions')
        );
});

// --- store ---

it('creates a role', function () {
    actingAs($this->admin)
        ->post('/admin/roles', [
            'name' => 'Support Staff',
            'description' => 'Handles support tickets.',
            'permissions' => [$this->permission->id],
        ])
        ->assertRedirect();

    $role = Role::where('name', 'Support Staff')->firstOrFail();
    expect($role->permissions()->count())->toBe(1);
});

it('rejects a duplicate role name', function () {
    Role::factory()->create(['name' => 'Support Staff']);

    actingAs($this->admin)
        ->post('/admin/roles', ['name' => 'Support Staff'])
        ->assertSessionHasErrors('name');
});

// --- edit ---

it('renders the edit page with the role and all permissions', function () {
    $role = Role::factory()->create();

    actingAs($this->admin)
        ->get("/admin/roles/{$role->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->component('admin/roles/edit')
            ->where('role.id', $role->id)
            ->has('allPermissions')
        );
});

// --- update ---

it('updates name and syncs permissions', function () {
    $role = Role::factory()->create(['name' => 'Old Name']);

    actingAs($this->admin)
        ->patch("/admin/roles/{$role->id}", [
            'name' => 'New Name',
            'permissions' => [$this->permission->id],
        ])
        ->assertRedirect();

    expect($role->fresh()->name)->toBe('New Name');
    expect($role->permissions()->count())->toBe(1);
});

it('clears permissions when none are sent', function () {
    $role = Role::factory()->create();
    $role->permissions()->sync([$this->permission->id]);

    actingAs($this->admin)
        ->patch("/admin/roles/{$role->id}", ['name' => $role->name])
        ->assertRedirect();

    expect($role->permissions()->count())->toBe(0);
});

it('rejects duplicate name on update for a different role', function () {
    Role::factory()->create(['name' => 'Taken']);
    $role = Role::factory()->create(['name' => 'Mine']);

    actingAs($this->admin)
        ->patch("/admin/roles/{$role->id}", ['name' => 'Taken'])
        ->assertSessionHasErrors('name');
});

it('allows keeping the same name on update', function () {
    $role = Role::factory()->create(['name' => 'Mine']);

    actingAs($this->admin)
        ->patch("/admin/roles/{$role->id}", ['name' => 'Mine'])
        ->assertRedirect();
});

// --- destroy ---

it('deletes an unassigned role', function () {
    $role = Role::factory()->create();

    actingAs($this->admin)
        ->delete("/admin/roles/{$role->id}")
        ->assertRedirect('/admin/roles');

    expect(Role::find($role->id))->toBeNull();
});

it('blocks deleting a role that is assigned to users', function () {
    $role = Role::factory()->create();
    $this->user->roles()->attach($role->id, ['assigned_by' => $this->admin->id]);

    actingAs($this->admin)
        ->delete("/admin/roles/{$role->id}")
        ->assertSessionHasErrors('delete');

    expect(Role::find($role->id))->not->toBeNull();
});
