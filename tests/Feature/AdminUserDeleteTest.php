<?php

use App\Enum\Tier;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;

beforeEach(function () {
    $this->withoutVite();

    $this->siteAdmin = User::factory()->create(['tier' => Tier::SiteAdmin]);
    $this->admin = User::factory()->create(['tier' => Tier::Admin]);
    $this->target = User::factory()->create(['tier' => Tier::User]);
});

it('redirects guests to login', function () {
    delete("/admin/users/{$this->target->id}")->assertRedirect('/login');
});

it('forbids regular users without the permission', function () {
    $user = User::factory()->create(['tier' => Tier::User]);

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

it('blocks self-deletion', function () {
    actingAs($this->admin)
        ->delete("/admin/users/{$this->admin->id}")
        ->assertForbidden();

    expect(User::find($this->admin->id))->not->toBeNull();
});

it('blocks an admin from deleting another admin', function () {
    $anotherAdmin = User::factory()->create(['tier' => Tier::Admin]);

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
