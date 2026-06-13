<?php

use App\Enum\Role;
use App\Models\Permission;
use App\Models\PermissionSet;
use App\Models\User;
use App\Models\UserPermissionSet;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->user = User::factory()->create(['role' => Role::User]);
    $this->permission = Permission::create([
        'name' => 'view_users',
        'display_name' => 'View Users',
        'description' => null,
    ]);
});

// --- index ---

it('redirects guests from the index', function () {
    get('/admin/permission-sets')->assertRedirect('/login');
});

it('forbids non-admins from the index', function () {
    actingAs($this->user)->get('/admin/permission-sets')->assertForbidden();
});

it('renders the permission sets index', function () {
    PermissionSet::factory()->create(['name' => 'Support Staff']);

    actingAs($this->admin)
        ->get('/admin/permission-sets')
        ->assertInertia(fn ($page) => $page
            ->component('admin/permission-sets/index')
            ->has('sets.data', 1)
        );
});

// --- create ---

it('renders the create page with all permissions', function () {
    actingAs($this->admin)
        ->get('/admin/permission-sets/create')
        ->assertInertia(fn ($page) => $page
            ->component('admin/permission-sets/create')
            ->has('allPermissions')
        );
});

// --- store ---

it('creates a permission set', function () {
    actingAs($this->admin)
        ->post('/admin/permission-sets', [
            'name' => 'Support Staff',
            'description' => 'Handles support tickets.',
            'permissions' => [$this->permission->id],
        ])
        ->assertRedirect();

    $set = PermissionSet::where('name', 'Support Staff')->firstOrFail();
    expect($set->permissions()->count())->toBe(1);
});

it('rejects a duplicate permission set name', function () {
    PermissionSet::factory()->create(['name' => 'Support Staff']);

    actingAs($this->admin)
        ->post('/admin/permission-sets', ['name' => 'Support Staff'])
        ->assertSessionHasErrors('name');
});

// --- edit ---

it('renders the edit page with the set and all permissions', function () {
    $set = PermissionSet::factory()->create();

    actingAs($this->admin)
        ->get("/admin/permission-sets/{$set->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->component('admin/permission-sets/edit')
            ->where('set.id', $set->id)
            ->has('allPermissions')
        );
});

// --- update ---

it('updates name and syncs permissions', function () {
    $set = PermissionSet::factory()->create(['name' => 'Old Name']);

    actingAs($this->admin)
        ->patch("/admin/permission-sets/{$set->id}", [
            'name' => 'New Name',
            'permissions' => [$this->permission->id],
        ])
        ->assertRedirect();

    expect($set->fresh()->name)->toBe('New Name');
    expect($set->permissions()->count())->toBe(1);
});

it('clears permissions when none are sent', function () {
    $set = PermissionSet::factory()->create();
    $set->permissions()->sync([$this->permission->id]);

    actingAs($this->admin)
        ->patch("/admin/permission-sets/{$set->id}", ['name' => $set->name])
        ->assertRedirect();

    expect($set->permissions()->count())->toBe(0);
});

it('rejects duplicate name on update for a different set', function () {
    PermissionSet::factory()->create(['name' => 'Taken']);
    $set = PermissionSet::factory()->create(['name' => 'Mine']);

    actingAs($this->admin)
        ->patch("/admin/permission-sets/{$set->id}", ['name' => 'Taken'])
        ->assertSessionHasErrors('name');
});

it('allows keeping the same name on update', function () {
    $set = PermissionSet::factory()->create(['name' => 'Mine']);

    actingAs($this->admin)
        ->patch("/admin/permission-sets/{$set->id}", ['name' => 'Mine'])
        ->assertRedirect();
});

// --- destroy ---

it('deletes an unassigned permission set', function () {
    $set = PermissionSet::factory()->create();

    actingAs($this->admin)
        ->delete("/admin/permission-sets/{$set->id}")
        ->assertRedirect('/admin/permission-sets');

    expect(PermissionSet::find($set->id))->toBeNull();
});

it('blocks deleting a set that is assigned to users', function () {
    $set = PermissionSet::factory()->create();
    UserPermissionSet::create([
        'user_id' => $this->user->id,
        'permission_set_id' => $set->id,
        'assigned_by' => $this->admin->id,
    ]);

    actingAs($this->admin)
        ->delete("/admin/permission-sets/{$set->id}")
        ->assertSessionHasErrors('delete');

    expect(PermissionSet::find($set->id))->not->toBeNull();
});
