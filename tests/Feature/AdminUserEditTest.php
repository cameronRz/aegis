<?php

use App\Enum\Tier;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;

beforeEach(function () {
    $this->withoutVite();

    $this->editPermission = Permission::create([
        'name' => 'edit_user',
        'display_name' => 'Edit Users',
        'description' => null,
    ]);

    $this->siteAdmin = User::factory()->create(['tier' => Tier::SiteAdmin]);
    $this->admin = User::factory()->create(['tier' => Tier::Admin]);
    $this->target = User::factory()->create(['tier' => Tier::User]);
});

// --- Access: GET /admin/users/{user}/edit ---

it('redirects guests to login on the edit page', function () {
    get("/admin/users/{$this->target->id}/edit")->assertRedirect('/login');
});

it('forbids regular users without the permission', function () {
    $user = User::factory()->create(['tier' => Tier::User]);

    actingAs($user)->get("/admin/users/{$this->target->id}/edit")->assertForbidden();
});

it('allows site admins to access the edit page', function () {
    actingAs($this->siteAdmin)->get("/admin/users/{$this->target->id}/edit")->assertOk();
});

it('allows admins to access the edit page', function () {
    actingAs($this->admin)->get("/admin/users/{$this->target->id}/edit")->assertOk();
});

it('allows users with the edit_user role to access the edit page', function () {
    $role = Role::create(['name' => 'Staff']);
    $role->permissions()->sync([$this->editPermission->id]);

    $user = User::factory()->create(['tier' => Tier::User]);
    $user->roles()->attach($role->id, ['assigned_by' => null]);

    actingAs($user)->get("/admin/users/{$this->target->id}/edit")->assertOk();
});

it('blocks self-editing on the edit page', function () {
    actingAs($this->admin)->get("/admin/users/{$this->admin->id}/edit")->assertForbidden();
});

it('blocks an admin from editing another admin', function () {
    $anotherAdmin = User::factory()->create(['tier' => Tier::Admin]);

    actingAs($this->admin)->get("/admin/users/{$anotherAdmin->id}/edit")->assertForbidden();
});

it('allows a site admin to edit another admin', function () {
    actingAs($this->siteAdmin)->get("/admin/users/{$this->admin->id}/edit")->assertOk();
});

// --- Tier availability on the edit page ---

it('passes all three tiers to site admins', function () {
    actingAs($this->siteAdmin)
        ->get("/admin/users/{$this->target->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->component('users/edit')
            ->where('availableTiers', ['site_admin', 'admin', 'user'])
        );
});

it('passes only user tier to admins', function () {
    actingAs($this->admin)
        ->get("/admin/users/{$this->target->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->where('availableTiers', ['user'])
        );
});

it('pre-fills the form with the existing user data', function () {
    actingAs($this->admin)
        ->get("/admin/users/{$this->target->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->where('user.id', $this->target->id)
            ->where('user.first_name', $this->target->first_name)
            ->where('user.email', $this->target->email)
        );
});

// --- Updating users: PATCH /admin/users/{user} ---

it('updates a user and redirects to their show page', function () {
    $response = actingAs($this->admin)->patch("/admin/users/{$this->target->id}", [
        'first_name' => 'Updated',
        'last_name' => 'Name',
        'email' => 'updated@example.com',
        'tier' => 'user',
    ]);

    $response->assertRedirect("/admin/users/{$this->target->id}");

    $this->target->refresh();
    expect($this->target->first_name)->toBe('Updated')
        ->and($this->target->last_name)->toBe('Name')
        ->and($this->target->email)->toBe('updated@example.com')
        ->and($this->target->tier)->toBe(Tier::User);
});

it('blocks self-editing on update', function () {
    actingAs($this->admin)->patch("/admin/users/{$this->admin->id}", [
        'first_name' => 'Hacked',
        'last_name' => 'Name',
        'email' => 'hacked@example.com',
        'tier' => 'user',
    ])->assertForbidden();
});

it('blocks an admin from updating another admin', function () {
    $anotherAdmin = User::factory()->create(['tier' => Tier::Admin]);

    actingAs($this->admin)->patch("/admin/users/{$anotherAdmin->id}", [
        'first_name' => 'Hacked',
        'last_name' => 'Name',
        'email' => 'hacked@example.com',
        'tier' => 'user',
    ])->assertForbidden();
});

it('rejects an admin trying to assign a privileged role', function () {
    actingAs($this->admin)->patch("/admin/users/{$this->target->id}", [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'tier' => 'site_admin',
    ])->assertSessionHasErrors('tier');
});

it('allows site admins to update users to any role', function () {
    actingAs($this->siteAdmin)->patch("/admin/users/{$this->target->id}", [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'tier' => 'admin',
    ])->assertRedirect();

    expect($this->target->refresh()->tier)->toBe(Tier::Admin);
});

it('rejects an update with a duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    actingAs($this->admin)->patch("/admin/users/{$this->target->id}", [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'taken@example.com',
        'tier' => 'user',
    ])->assertSessionHasErrors('email');
});

// --- Role assignment ---

it('passes roles and selected role ids to the edit page', function () {
    $role = Role::factory()->create();
    $this->target->roles()->attach($role->id, ['assigned_by' => null]);

    actingAs($this->admin)
        ->get("/admin/users/{$this->target->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->has('roles', 1)
            ->where('selectedRoleIds', [$role->id])
        );
});

it('passes empty selectedRoleIds when user has no roles', function () {
    actingAs($this->admin)
        ->get("/admin/users/{$this->target->id}/edit")
        ->assertInertia(fn ($page) => $page->where('selectedRoleIds', []));
});

it('assigns a role on update', function () {
    $role = Role::factory()->create();

    actingAs($this->admin)->patch("/admin/users/{$this->target->id}", [
        'first_name' => $this->target->first_name,
        'last_name' => $this->target->last_name,
        'email' => $this->target->email,
        'tier' => 'user',
        'role_ids' => [$role->id],
    ])->assertRedirect();

    expect($this->target->fresh()->roles()->where('role_id', $role->id)->exists())->toBeTrue();
});

it('rejects role updates that grant permissions beyond the actor', function () {
    $staffRole = Role::factory()->create(['name' => 'Staff']);
    $staffRole->permissions()->sync([$this->editPermission->id]);

    $viewProducts = Permission::create([
        'name' => 'view_products',
        'display_name' => 'View Products',
        'description' => null,
    ]);
    $privilegedRole = Role::factory()->create(['name' => 'Product Viewer']);
    $privilegedRole->permissions()->sync([$viewProducts->id]);

    $staff = User::factory()->create(['tier' => Tier::User]);
    $staff->roles()->attach($staffRole->id, ['assigned_by' => null]);

    actingAs($staff)->patch("/admin/users/{$this->target->id}", [
        'first_name' => $this->target->first_name,
        'last_name' => $this->target->last_name,
        'email' => $this->target->email,
        'tier' => 'user',
        'role_ids' => [$privilegedRole->id],
    ])->assertSessionHasErrors('role_ids');

    expect($this->target->fresh()->roles()->where('role_id', $privilegedRole->id)->exists())->toBeFalse();
});

it('preserves existing roles the actor is not allowed to assign', function () {
    $staffRole = Role::factory()->create(['name' => 'Staff']);
    $staffRole->permissions()->sync([$this->editPermission->id]);

    $viewProducts = Permission::create([
        'name' => 'view_products',
        'display_name' => 'View Products',
        'description' => null,
    ]);
    $privilegedRole = Role::factory()->create(['name' => 'Product Viewer']);
    $privilegedRole->permissions()->sync([$viewProducts->id]);
    $this->target->roles()->attach($privilegedRole->id, ['assigned_by' => $this->siteAdmin->id]);

    $staff = User::factory()->create(['tier' => Tier::User]);
    $staff->roles()->attach($staffRole->id, ['assigned_by' => null]);

    actingAs($staff)->patch("/admin/users/{$this->target->id}", [
        'first_name' => $this->target->first_name,
        'last_name' => $this->target->last_name,
        'email' => $this->target->email,
        'tier' => 'user',
        'role_ids' => [],
    ])->assertRedirect();

    $preservedRole = $this->target->fresh()->roles()->where('role_id', $privilegedRole->id)->first();

    expect($preservedRole)->not->toBeNull()
        ->and($preservedRole->pivot->assigned_by)->toBe($this->siteAdmin->id);
});

it('clears roles when empty array is sent on update', function () {
    $role = Role::factory()->create();
    $this->target->roles()->attach($role->id, ['assigned_by' => null]);

    actingAs($this->admin)->patch("/admin/users/{$this->target->id}", [
        'first_name' => $this->target->first_name,
        'last_name' => $this->target->last_name,
        'email' => $this->target->email,
        'tier' => 'user',
        'role_ids' => [],
    ])->assertRedirect();

    expect($this->target->fresh()->roles()->count())->toBe(0);
});

it('changes roles on update', function () {
    $roleA = Role::factory()->create();
    $roleB = Role::factory()->create();
    $this->target->roles()->attach($roleA->id, ['assigned_by' => null]);

    actingAs($this->admin)->patch("/admin/users/{$this->target->id}", [
        'first_name' => $this->target->first_name,
        'last_name' => $this->target->last_name,
        'email' => $this->target->email,
        'tier' => 'user',
        'role_ids' => [$roleB->id],
    ])->assertRedirect();

    $freshRoles = $this->target->fresh()->roles()->pluck('role_id')->toArray();
    expect($freshRoles)->toBe([$roleB->id]);
});

it('allows the same email to be submitted unchanged', function () {
    actingAs($this->admin)->patch("/admin/users/{$this->target->id}", [
        'first_name' => $this->target->first_name,
        'last_name' => $this->target->last_name,
        'email' => $this->target->email,
        'tier' => 'user',
    ])->assertRedirect();
});
