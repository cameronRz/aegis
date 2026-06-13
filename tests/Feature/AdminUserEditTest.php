<?php

use App\Enum\Role;
use App\Models\Permission;
use App\Models\PermissionSet;
use App\Models\User;
use App\Models\UserPermissionSet;

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

it('allows users with the edit_user permission set to access the edit page', function () {
    $set = PermissionSet::create(['name' => 'Staff']);
    $set->permissions()->sync([$this->editPermission->id]);

    $user = User::factory()->create(['role' => Role::User]);
    $user->userPermissionSet()->create(['permission_set_id' => $set->id, 'assigned_by' => null]);

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

it('passes all three roles to site admins', function () {
    actingAs($this->siteAdmin)
        ->get("/admin/users/{$this->target->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->component('users/edit')
            ->where('availableRoles', ['site_admin', 'admin', 'user'])
        );
});

it('passes only user role to admins', function () {
    actingAs($this->admin)
        ->get("/admin/users/{$this->target->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->where('availableRoles', ['user'])
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
        'role' => 'user',
    ]);

    $response->assertRedirect("/admin/users/{$this->target->id}");

    $this->target->refresh();
    expect($this->target->first_name)->toBe('Updated')
        ->and($this->target->last_name)->toBe('Name')
        ->and($this->target->email)->toBe('updated@example.com')
        ->and($this->target->role)->toBe(Role::User);
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

// --- Permission set assignment ---

it('passes permission sets and current set id to the edit page', function () {
    $set = PermissionSet::factory()->create();
    $this->target->userPermissionSet()->create(['permission_set_id' => $set->id, 'assigned_by' => null]);

    actingAs($this->admin)
        ->get("/admin/users/{$this->target->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->has('permissionSets', 1)
            ->where('currentPermissionSetId', $set->id)
        );
});

it('passes null currentPermissionSetId when user has no set', function () {
    actingAs($this->admin)
        ->get("/admin/users/{$this->target->id}/edit")
        ->assertInertia(fn ($page) => $page->where('currentPermissionSetId', null));
});

it('assigns a permission set on update', function () {
    $set = PermissionSet::factory()->create();

    actingAs($this->admin)->patch("/admin/users/{$this->target->id}", [
        'first_name' => $this->target->first_name,
        'last_name' => $this->target->last_name,
        'email' => $this->target->email,
        'role' => 'user',
        'permission_set_id' => $set->id,
    ])->assertRedirect();

    expect($this->target->fresh()->userPermissionSet?->permission_set_id)->toBe($set->id);
});

it('clears the permission set when null is sent on update', function () {
    $set = PermissionSet::factory()->create();
    $this->target->userPermissionSet()->create(['permission_set_id' => $set->id, 'assigned_by' => null]);

    actingAs($this->admin)->patch("/admin/users/{$this->target->id}", [
        'first_name' => $this->target->first_name,
        'last_name' => $this->target->last_name,
        'email' => $this->target->email,
        'role' => 'user',
        'permission_set_id' => null,
    ])->assertRedirect();

    expect(UserPermissionSet::where('user_id', $this->target->id)->exists())->toBeFalse();
});

it('changes the permission set on update', function () {
    $setA = PermissionSet::factory()->create();
    $setB = PermissionSet::factory()->create();
    $this->target->userPermissionSet()->create(['permission_set_id' => $setA->id, 'assigned_by' => null]);

    actingAs($this->admin)->patch("/admin/users/{$this->target->id}", [
        'first_name' => $this->target->first_name,
        'last_name' => $this->target->last_name,
        'email' => $this->target->email,
        'role' => 'user',
        'permission_set_id' => $setB->id,
    ])->assertRedirect();

    expect($this->target->fresh()->userPermissionSet?->permission_set_id)->toBe($setB->id);
});

it('allows the same email to be submitted unchanged', function () {
    actingAs($this->admin)->patch("/admin/users/{$this->target->id}", [
        'first_name' => $this->target->first_name,
        'last_name' => $this->target->last_name,
        'email' => $this->target->email,
        'role' => 'user',
    ])->assertRedirect();
});
