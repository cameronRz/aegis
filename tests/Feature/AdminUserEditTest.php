<?php

use App\Enum\Role;
use App\Models\Permission;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;

beforeEach(function () {
    $this->withoutVite();

    $this->editUserPermission = Permission::create([
        'name' => 'edit_user',
        'display_name' => 'Edit Users',
        'description' => 'Edit existing user accounts.',
    ]);

    $this->siteAdmin = User::factory()->create(['role' => Role::SiteAdmin]);
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->target = User::factory()->create(['role' => Role::User]);
});

// --- Access: GET /admin/users/{user}/edit ---

it('redirects guests to login on the edit page', function () {
    get("/admin/users/{$this->target->id}/edit")->assertRedirect('/login');
});

it('forbids regular users without the permission', function () {
    $user = User::factory()->create(['role' => Role::User]);

    actingAs($user)->get("/admin/users/{$this->target->id}/edit")->assertForbidden();
});

it('allows site admins to access the edit page', function () {
    actingAs($this->siteAdmin)->get("/admin/users/{$this->target->id}/edit")->assertOk();
});

it('allows admins to access the edit page', function () {
    actingAs($this->admin)->get("/admin/users/{$this->target->id}/edit")->assertOk();
});

it('allows users with the edit_user permission to access the edit page', function () {
    $user = User::factory()->create(['role' => Role::User]);
    $user->permissions()->attach($this->editUserPermission->id, ['granted_by' => $this->admin->id]);

    actingAs($user)->get("/admin/users/{$this->target->id}/edit")->assertOk();
});

it('blocks self-editing on the edit page', function () {
    actingAs($this->admin)->get("/admin/users/{$this->admin->id}/edit")->assertForbidden();
});

it('blocks an admin from editing another admin', function () {
    $anotherAdmin = User::factory()->create(['role' => Role::Admin]);

    actingAs($this->admin)->get("/admin/users/{$anotherAdmin->id}/edit")->assertForbidden();
});

it('allows a site admin to edit another admin', function () {
    actingAs($this->siteAdmin)->get("/admin/users/{$this->admin->id}/edit")->assertOk();
});

// --- Role availability on the edit page ---

it('passes all four roles to site admins', function () {
    actingAs($this->siteAdmin)
        ->get("/admin/users/{$this->target->id}/edit")
        ->assertInertia(
            fn ($page) => $page
                ->component('users/edit')
                ->where('availableRoles', ['site_admin', 'admin', 'manager', 'user'])
        );
});

it('passes only manager and user roles to admins', function () {
    actingAs($this->admin)
        ->get("/admin/users/{$this->target->id}/edit")
        ->assertInertia(
            fn ($page) => $page
                ->where('availableRoles', ['manager', 'user'])
                ->where('canAssignPermissions', true)
        );
});

it('passes only manager and user roles to edit_user permission holders', function () {
    $user = User::factory()->create(['role' => Role::User]);
    $user->permissions()->attach($this->editUserPermission->id, ['granted_by' => $this->admin->id]);

    actingAs($user)
        ->get("/admin/users/{$this->target->id}/edit")
        ->assertInertia(
            fn ($page) => $page
                ->where('availableRoles', ['manager', 'user'])
                ->where('canAssignPermissions', false)
        );
});

it('pre-fills the form with the existing user data', function () {
    actingAs($this->admin)
        ->get("/admin/users/{$this->target->id}/edit")
        ->assertInertia(
            fn ($page) => $page
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
        'role' => 'manager',
    ]);

    $response->assertRedirect("/admin/users/{$this->target->id}");

    $this->target->refresh();
    expect($this->target->first_name)->toBe('Updated')
        ->and($this->target->last_name)->toBe('Name')
        ->and($this->target->email)->toBe('updated@example.com')
        ->and($this->target->role)->toBe(Role::Manager);
});

it('blocks self-editing on update', function () {
    actingAs($this->admin)->patch("/admin/users/{$this->admin->id}", [
        'first_name' => 'Hacked',
        'last_name' => 'Name',
        'email' => 'hacked@example.com',
        'role' => 'user',
    ])->assertForbidden();
});

it('blocks an admin from updating another admin', function () {
    $anotherAdmin = User::factory()->create(['role' => Role::Admin]);

    actingAs($this->admin)->patch("/admin/users/{$anotherAdmin->id}", [
        'first_name' => 'Hacked',
        'last_name' => 'Name',
        'email' => 'hacked@example.com',
        'role' => 'user',
    ])->assertForbidden();
});

it('rejects an admin trying to assign a privileged role', function () {
    actingAs($this->admin)->patch("/admin/users/{$this->target->id}", [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'role' => 'site_admin',
    ])->assertSessionHasErrors('role');
});

it('allows site admins to update users to any role', function () {
    actingAs($this->siteAdmin)->patch("/admin/users/{$this->target->id}", [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'role' => 'admin',
    ])->assertRedirect();

    expect($this->target->refresh()->role)->toBe(Role::Admin);
});

it('rejects an update with a duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    actingAs($this->admin)->patch("/admin/users/{$this->target->id}", [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'taken@example.com',
        'role' => 'user',
    ])->assertSessionHasErrors('email');
});

it('allows the same email to be submitted unchanged', function () {
    actingAs($this->admin)->patch("/admin/users/{$this->target->id}", [
        'first_name' => $this->target->first_name,
        'last_name' => $this->target->last_name,
        'email' => $this->target->email,
        'role' => 'user',
    ])->assertRedirect();
});

it('allows admins to sync permissions on update', function () {
    $viewPermission = Permission::create([
        'name' => 'view_users',
        'display_name' => 'View Users',
        'description' => 'Access the users list.',
    ]);

    actingAs($this->admin)->patch("/admin/users/{$this->target->id}", [
        'first_name' => $this->target->first_name,
        'last_name' => $this->target->last_name,
        'email' => $this->target->email,
        'role' => 'user',
        'permissions' => [$viewPermission->id],
    ]);

    expect($this->target->fresh()->permissions)->toHaveCount(1)
        ->and($this->target->fresh()->permissions->first()->name)->toBe('view_users');
});

it('syncs away permissions that are removed on update', function () {
    $viewPermission = Permission::create([
        'name' => 'view_users',
        'display_name' => 'View Users',
        'description' => 'Access the users list.',
    ]);

    $this->target->permissions()->attach($viewPermission->id, ['granted_by' => $this->admin->id]);

    actingAs($this->admin)->patch("/admin/users/{$this->target->id}", [
        'first_name' => $this->target->first_name,
        'last_name' => $this->target->last_name,
        'email' => $this->target->email,
        'role' => 'user',
        'permissions' => [],
    ]);

    expect($this->target->fresh()->permissions)->toBeEmpty();
});

it('preserves granted_by for existing permissions on update', function () {
    $viewPermission = Permission::create([
        'name' => 'view_users',
        'display_name' => 'View Users',
        'description' => 'Access the users list.',
    ]);

    $originalGrantor = User::factory()->create(['role' => Role::Admin]);
    $this->target->permissions()->attach($viewPermission->id, ['granted_by' => $originalGrantor->id]);

    actingAs($this->admin)->patch("/admin/users/{$this->target->id}", [
        'first_name' => $this->target->first_name,
        'last_name' => $this->target->last_name,
        'email' => $this->target->email,
        'role' => 'user',
        'permissions' => [$viewPermission->id],
    ]);

    $pivot = $this->target->fresh()->permissions()->withPivot('granted_by')->first()->pivot;
    expect($pivot->granted_by)->toBe($originalGrantor->id);
});

it('prevents non-admins with edit_user permission from syncing permissions', function () {
    $user = User::factory()->create(['role' => Role::User]);
    $user->permissions()->attach($this->editUserPermission->id, ['granted_by' => $this->admin->id]);

    $viewPermission = Permission::create([
        'name' => 'view_users',
        'display_name' => 'View Users',
        'description' => 'Access the users list.',
    ]);

    actingAs($user)->patch("/admin/users/{$this->target->id}", [
        'first_name' => $this->target->first_name,
        'last_name' => $this->target->last_name,
        'email' => $this->target->email,
        'role' => 'user',
        'permissions' => [$viewPermission->id],
    ]);

    expect($this->target->fresh()->permissions)->toBeEmpty();
});
