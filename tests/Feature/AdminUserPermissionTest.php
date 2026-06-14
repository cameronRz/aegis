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
    $this->siteAdmin = User::factory()->create(['tier' => Tier::SiteAdmin]);
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
    $user = User::factory()->create(['tier' => Tier::User]);
    $target = User::factory()->create();

    actingAs($user)->get("/admin/users/{$target->id}")->assertForbidden();
});

it('allows a user with view_users permission set to view a user', function () {
    $role = Role::create(['name' => 'Support']);
    $role->permissions()->sync([$this->viewPermission->id]);

    $staff = User::factory()->create(['tier' => Tier::User]);
    $staff->roles()->attach($role->id, ['assigned_by' => null]);

    $target = User::factory()->create();

    actingAs($staff)->get("/admin/users/{$target->id}")->assertOk();
});

it('renders the user show page with canEdit and canDelete props', function () {
    $target = User::factory()->create(['tier' => Tier::User]);

    actingAs($this->admin)
        ->get("/admin/users/{$target->id}")
        ->assertInertia(fn ($page) => $page
            ->component('users/show')
            ->has('user')
            ->has('canEdit')
            ->has('canDelete')
        );
});
