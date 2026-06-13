<?php

use App\Enum\Role;
use App\Models\Permission;
use App\Models\PermissionSet;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->withoutVite();

    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->siteAdmin = User::factory()->create(['role' => Role::SiteAdmin]);
    $this->viewPermission = Permission::create([
        'name' => 'view_users',
        'display_name' => 'View Users',
        'description' => null,
    ]);
});

it('redirects guests from user show', function () {
    $user = User::factory()->create();

    get("/admin/users/{$user->id}")->assertRedirect('/login');
});

it('forbids users without the view_users permission', function () {
    $user = User::factory()->create(['role' => Role::User]);
    $target = User::factory()->create();

    actingAs($user)->get("/admin/users/{$target->id}")->assertForbidden();
});

it('allows a user with view_users permission set to view a user', function () {
    $set = PermissionSet::create(['name' => 'Support']);
    $set->permissions()->sync([$this->viewPermission->id]);

    $staff = User::factory()->create(['role' => Role::User]);
    $staff->userPermissionSet()->create(['permission_set_id' => $set->id, 'assigned_by' => null]);

    $target = User::factory()->create();

    actingAs($staff)->get("/admin/users/{$target->id}")->assertOk();
});

it('renders the user show page with canEdit and canDelete props', function () {
    $target = User::factory()->create(['role' => Role::User]);

    actingAs($this->admin)
        ->get("/admin/users/{$target->id}")
        ->assertInertia(fn ($page) => $page
            ->component('users/show')
            ->has('user')
            ->has('canEdit')
            ->has('canDelete')
        );
});
