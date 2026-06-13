<?php

use App\Enum\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

it('soft-deletes a user without removing grants they made', function () {
    $this->target->permissions()->attach($this->deleteUserPermission->id, ['granted_by' => $this->admin->id]);

    actingAs($this->siteAdmin)
        ->delete("/admin/users/{$this->admin->id}")
        ->assertRedirect('/admin/users');

    expect($this->admin->fresh()->deleted_at)->not->toBeNull();

    $this->assertDatabaseHas('user_permissions', [
        'user_id' => $this->target->id,
        'permission_id' => $this->deleteUserPermission->id,
        'granted_by' => $this->admin->id,
    ]);
});

it('deletes passkeys and sessions when a user is soft-deleted', function () {
    $this->target->passkeys()->create([
        'name' => 'Test passkey',
        'credential_id' => 'cred-id',
        'credential' => '{}',
    ]);

    DB::table('sessions')->insert([
        'id' => 'session-id',
        'user_id' => $this->target->id,
        'payload' => 'payload',
        'last_activity' => time(),
    ]);

    actingAs($this->admin)
        ->delete("/admin/users/{$this->target->id}")
        ->assertRedirect('/admin/users');

    expect($this->target->fresh()->passkeys()->count())->toBe(0);
    expect(DB::table('sessions')->where('user_id', $this->target->id)->count())->toBe(0);
});

it('does not show soft-deleted users in the admin users index', function () {
    $this->target->delete();

    actingAs($this->admin)
        ->get('/admin/users')
        ->assertInertia(fn ($page) => $page->where('users.data', fn ($users) => collect($users)->doesntContain(
            fn ($user) => $user['id'] === $this->target->id
        )));
});

it('keeps permissions intact when a user is restored', function () {
    $this->target->permissions()->attach($this->deleteUserPermission->id, ['granted_by' => $this->admin->id]);
    $this->target->delete();

    actingAs($this->siteAdmin)
        ->post("/admin/users/{$this->target->id}/restore")
        ->assertRedirect('/admin/users/trash');

    $restored = User::find($this->target->id);

    expect($restored->deleted_at)->toBeNull();
    expect($restored->permissions()->count())->toBe(1);
});

it('nulls out granted_by instead of deleting the grant when the granter is permanently deleted', function () {
    $this->target->permissions()->attach($this->deleteUserPermission->id, ['granted_by' => $this->admin->id]);
    $this->admin->delete();

    actingAs($this->siteAdmin)
        ->delete("/admin/users/{$this->admin->id}/force")
        ->assertRedirect('/admin/users/trash');

    $this->assertDatabaseHas('user_permissions', [
        'user_id' => $this->target->id,
        'permission_id' => $this->deleteUserPermission->id,
        'granted_by' => null,
    ]);
});
