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
});

it('redirects guests to login', function () {
    get('/admin/users')->assertRedirect('/login');
});

it('forbids regular users from accessing admin users', function () {
    $user = User::factory()->create(['role' => Role::User]);

    actingAs($user)->get('/admin/users')->assertForbidden();
});

it('returns users for an admin', function () {
    $customers = User::factory(3)->create(['role' => Role::User]);

    actingAs($this->admin)
        ->get('/admin/users')
        ->assertInertia(
            fn ($page) => $page
                ->component('users/index')
                ->has('users.data', 3 + 1) // 3 customers + the admin itself
        );
});

it('hides site_admin users from regular admins', function () {
    User::factory(2)->create(['role' => Role::User]);

    actingAs($this->admin)
        ->get('/admin/users')
        ->assertInertia(
            fn ($page) => $page->has('users.data', 3) // admin + 2 users, site_admin excluded
        );
});

it('returns all users including site_admins for site admin', function () {
    User::factory(2)->create(['role' => Role::User]);

    actingAs($this->siteAdmin)
        ->get('/admin/users')
        ->assertInertia(
            fn ($page) => $page->has('users.data', 2 + 2) // 2 users + site admin + regular admin from beforeEach
        );
});

it('filters users by search term', function () {
    User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith', 'role' => Role::User]);
    User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones', 'role' => Role::User]);

    actingAs($this->admin)
        ->get('/admin/users?search=alice')
        ->assertInertia(
            fn ($page) => $page->has('users.data', 1)
        );
});

it('searches by email', function () {
    User::factory()->create(['email' => 'unique@example.com', 'role' => Role::User]);
    User::factory(3)->create(['role' => Role::User]);

    actingAs($this->admin)
        ->get('/admin/users?search=unique@example.com')
        ->assertInertia(
            fn ($page) => $page->has('users.data', 1)
        );
});

it('hides site_admin users from users with view_users permission set', function () {
    $permission = Permission::create(['name' => 'view_users', 'display_name' => 'View Users', 'description' => null]);
    $set = PermissionSet::create(['name' => 'Staff']);
    $set->permissions()->sync([$permission->id]);

    $staff = User::factory()->create(['role' => Role::User]);
    $staff->userPermissionSet()->create(['permission_set_id' => $set->id, 'assigned_by' => null]);

    User::factory(2)->create(['role' => Role::User]);

    actingAs($staff)
        ->get('/admin/users')
        ->assertInertia(
            fn ($page) => $page->has('users.data', 4) // staff + 2 users + admin; site_admin excluded
        );
});

it('passes filters back to the page', function () {
    actingAs($this->admin)
        ->get('/admin/users?search=test')
        ->assertInertia(
            fn ($page) => $page->where('filters.search', 'test')
        );
});
