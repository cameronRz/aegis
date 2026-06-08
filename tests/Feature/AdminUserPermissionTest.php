<?php

use App\Enum\Role;
use App\Models\Permission;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->withoutVite();

    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->siteAdmin = User::factory()->create(['role' => Role::SiteAdmin]);
    $this->permission = Permission::create([
        'name' => 'view_users',
        'display_name' => 'View Users',
        'description' => 'Access the users list.',
    ]);
});

// --- show ---

it('redirects guests from user show', function () {
    $user = User::factory()->create();

    get("/admin/users/{$user->id}")->assertRedirect('/login');
});

it('forbids users without the view_users permission', function () {
    $user = User::factory()->create(['role' => Role::User]);
    $target = User::factory()->create();

    actingAs($user)->get("/admin/users/{$target->id}")->assertForbidden();
});

it('allows a manager with view_users permission to view a user', function () {
    $manager = User::factory()->create(['role' => Role::Manager]);
    $manager->permissions()->attach($this->permission->id, ['granted_by' => $this->admin->id]);
    $target = User::factory()->create();

    actingAs($manager)->get("/admin/users/{$target->id}")->assertOk();
});

it('returns user and permissions data on show', function () {
    $target = User::factory()->create(['role' => Role::User]);
    $target->permissions()->attach($this->permission->id, ['granted_by' => $this->admin->id]);

    actingAs($this->admin)
        ->get("/admin/users/{$target->id}")
        ->assertInertia(
            fn ($page) => $page
                ->component('users/show')
                ->has('user')
                ->has('allPermissions', 1)
                ->where('canManagePermissions', true)
        );
});

it('sets canManagePermissions to false when viewing yourself', function () {
    actingAs($this->admin)
        ->get("/admin/users/{$this->admin->id}")
        ->assertInertia(
            fn ($page) => $page->where('canManagePermissions', false)
        );
});

it('sets canManagePermissions to false when admin views another admin', function () {
    $otherAdmin = User::factory()->create(['role' => Role::Admin]);

    actingAs($this->admin)
        ->get("/admin/users/{$otherAdmin->id}")
        ->assertInertia(
            fn ($page) => $page->where('canManagePermissions', false)
        );
});

// --- toggle ---

it('redirects guests from permission toggle', function () {
    $user = User::factory()->create();

    post("/admin/users/{$user->id}/permissions/{$this->permission->id}/toggle")->assertRedirect('/login');
});

it('forbids non-admins from toggling permissions', function () {
    $manager = User::factory()->create(['role' => Role::Manager]);
    $target = User::factory()->create(['role' => Role::User]);

    actingAs($manager)
        ->post("/admin/users/{$target->id}/permissions/{$this->permission->id}/toggle")
        ->assertForbidden();
});

it('grants a permission when it is not yet assigned', function () {
    $target = User::factory()->create(['role' => Role::User]);

    actingAs($this->admin)
        ->post("/admin/users/{$target->id}/permissions/{$this->permission->id}/toggle");

    expect($target->permissions()->where('permission_id', $this->permission->id)->exists())->toBeTrue();
});

it('revokes a permission when it is already assigned', function () {
    $target = User::factory()->create(['role' => Role::User]);
    $target->permissions()->attach($this->permission->id, ['granted_by' => $this->admin->id]);

    actingAs($this->admin)
        ->post("/admin/users/{$target->id}/permissions/{$this->permission->id}/toggle");

    expect($target->permissions()->where('permission_id', $this->permission->id)->exists())->toBeFalse();
});

it('records who granted the permission', function () {
    $target = User::factory()->create(['role' => Role::User]);

    actingAs($this->admin)
        ->post("/admin/users/{$target->id}/permissions/{$this->permission->id}/toggle");

    $pivot = $target->permissions()->first()?->pivot;
    expect($pivot?->granted_by)->toBe($this->admin->id);
});
