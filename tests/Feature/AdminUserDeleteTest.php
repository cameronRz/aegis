<?php

use App\Enum\Role;
use App\Models\Permission;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;

beforeEach(function () {
    $this->withoutVite();

    $this->deleteUserPermission = Permission::create([
        'name' => 'delete_user',
        'display_name' => 'Delete Users',
        'description' => 'Delete user accounts.',
    ]);

    $this->siteAdmin = User::factory()->create(['role' => Role::SiteAdmin]);
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->target = User::factory()->create(['role' => Role::User]);
});

it('redirects guests to login', function () {
    delete("/admin/users/{$this->target->id}")->assertRedirect('/login');
});

it('forbids regular users without the permission', function () {
    $user = User::factory()->create(['role' => Role::User]);

    actingAs($user)->delete("/admin/users/{$this->target->id}")->assertForbidden();
});

it('allows site admins to delete a user', function () {
    actingAs($this->siteAdmin)
        ->delete("/admin/users/{$this->target->id}")
        ->assertRedirect('/admin/users');

    expect(User::find($this->target->id))->toBeNull();
});

it('allows admins to delete a user', function () {
    actingAs($this->admin)
        ->delete("/admin/users/{$this->target->id}")
        ->assertRedirect('/admin/users');

    expect(User::find($this->target->id))->toBeNull();
});

it('allows users with the delete_user permission to delete a user', function () {
    $user = User::factory()->create(['role' => Role::User]);
    $user->permissions()->attach($this->deleteUserPermission->id, ['granted_by' => $this->admin->id]);

    actingAs($user)
        ->delete("/admin/users/{$this->target->id}")
        ->assertRedirect('/admin/users');

    expect(User::find($this->target->id))->toBeNull();
});

it('blocks self-deletion', function () {
    actingAs($this->admin)
        ->delete("/admin/users/{$this->admin->id}")
        ->assertForbidden();

    expect(User::find($this->admin->id))->not->toBeNull();
});

it('blocks an admin from deleting another admin', function () {
    $anotherAdmin = User::factory()->create(['role' => Role::Admin]);

    actingAs($this->admin)
        ->delete("/admin/users/{$anotherAdmin->id}")
        ->assertForbidden();

    expect(User::find($anotherAdmin->id))->not->toBeNull();
});

it('allows a site admin to delete another admin', function () {
    actingAs($this->siteAdmin)
        ->delete("/admin/users/{$this->admin->id}")
        ->assertRedirect('/admin/users');

    expect(User::find($this->admin->id))->toBeNull();
});

it('redirects to the users index after deletion', function () {
    actingAs($this->admin)
        ->delete("/admin/users/{$this->target->id}")
        ->assertRedirect('/admin/users');
});
