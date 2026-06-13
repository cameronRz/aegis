<?php

use App\Enum\Role;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->withoutVite();

    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->user = User::factory()->create(['role' => Role::User]);
    $this->trashed = User::factory()->create(['role' => Role::User]);
    $this->trashed->delete();
});

// --- trash page ---

it('redirects guests from the trash page', function () {
    get('/admin/users/trash')->assertRedirect('/login');
});

it('forbids non-admins from the trash page', function () {
    actingAs($this->user)->get('/admin/users/trash')->assertForbidden();
});

it('renders the trash page for an admin', function () {
    actingAs($this->admin)
        ->get('/admin/users/trash')
        ->assertInertia(fn ($page) => $page
            ->component('users/trash')
            ->has('users.data', 1),
        );
});

it('does not show active users on the trash page', function () {
    actingAs($this->admin)
        ->get('/admin/users/trash')
        ->assertInertia(fn ($page) => $page->has('users.data', 1)); // only the trashed one
});

it('searches trashed users by name', function () {
    $other = User::factory()->create(['first_name' => 'Different', 'role' => Role::User]);
    $other->delete();

    actingAs($this->admin)
        ->get('/admin/users/trash?search='.urlencode($this->trashed->first_name))
        ->assertInertia(fn ($page) => $page->has('users.data', 1));
});

// --- restore ---

it('redirects guests from restore', function () {
    post("/admin/users/{$this->trashed->id}/restore")->assertRedirect('/login');
});

it('forbids users without delete_user from restoring', function () {
    actingAs($this->user)
        ->post("/admin/users/{$this->trashed->id}/restore")
        ->assertForbidden();
});

it('restores a soft-deleted user', function () {
    actingAs($this->admin)
        ->post("/admin/users/{$this->trashed->id}/restore")
        ->assertRedirect('/admin/users/trash');

    expect(User::find($this->trashed->id))->not->toBeNull();
});

// --- force delete ---

it('redirects guests from force delete', function () {
    delete("/admin/users/{$this->trashed->id}/force")->assertRedirect('/login');
});

it('forbids non-admins from force deleting', function () {
    actingAs($this->user)
        ->delete("/admin/users/{$this->trashed->id}/force")
        ->assertForbidden();
});

it('permanently deletes a soft-deleted user', function () {
    actingAs($this->admin)
        ->delete("/admin/users/{$this->trashed->id}/force")
        ->assertRedirect('/admin/users/trash');

    expect(User::withTrashed()->find($this->trashed->id))->toBeNull();
});
