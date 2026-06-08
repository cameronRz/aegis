<?php

use App\Enum\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->withoutVite();

    $this->createUserPermission = Permission::create([
        'name' => 'create_user',
        'display_name' => 'Create Users',
        'description' => 'Create new user accounts.',
    ]);

    $this->siteAdmin = User::factory()->create(['role' => Role::SiteAdmin]);
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

// --- Access ---

it('redirects guests to login', function () {
    get('/admin/users/create')->assertRedirect('/login');
});

it('forbids regular users without the permission', function () {
    $user = User::factory()->create(['role' => Role::User]);

    actingAs($user)->get('/admin/users/create')->assertForbidden();
});

it('forbids managers without the permission', function () {
    $manager = User::factory()->create(['role' => Role::Manager]);

    actingAs($manager)->get('/admin/users/create')->assertForbidden();
});

it('allows site admins to access the create page', function () {
    actingAs($this->siteAdmin)->get('/admin/users/create')->assertOk();
});

it('allows admins to access the create page', function () {
    actingAs($this->admin)->get('/admin/users/create')->assertOk();
});

it('allows users with the create_user permission to access the create page', function () {
    $user = User::factory()->create(['role' => Role::User]);
    $user->permissions()->attach($this->createUserPermission->id, ['granted_by' => $this->admin->id]);

    actingAs($user)->get('/admin/users/create')->assertOk();
});

// --- Role availability on the create page ---

it('passes all four roles to site admins', function () {
    actingAs($this->siteAdmin)
        ->get('/admin/users/create')
        ->assertInertia(
            fn ($page) => $page
                ->component('users/create')
                ->where('availableRoles', ['site_admin', 'admin', 'manager', 'user'])
        );
});

it('passes only manager and user roles to admins', function () {
    actingAs($this->admin)
        ->get('/admin/users/create')
        ->assertInertia(
            fn ($page) => $page
                ->where('availableRoles', ['manager', 'user'])
                ->where('canAssignPermissions', true)
        );
});

it('passes only manager and user roles to create_user permission holders', function () {
    $user = User::factory()->create(['role' => Role::User]);
    $user->permissions()->attach($this->createUserPermission->id, ['granted_by' => $this->admin->id]);

    actingAs($user)
        ->get('/admin/users/create')
        ->assertInertia(
            fn ($page) => $page
                ->where('availableRoles', ['manager', 'user'])
                ->where('canAssignPermissions', false)
        );
});

// --- Storing users ---

it('creates a user and redirects to their show page', function () {
    Notification::fake();

    $response = actingAs($this->admin)->post('/admin/users', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'role' => 'user',
    ]);

    $newUser = User::where('email', 'jane@example.com')->firstOrFail();

    $response->assertRedirect("/admin/users/{$newUser->id}");

    expect($newUser->first_name)->toBe('Jane')
        ->and($newUser->last_name)->toBe('Doe')
        ->and($newUser->role)->toBe(Role::User)
        ->and($newUser->email_verified_at)->not->toBeNull();
});

it('sends a password reset email to the new user', function () {
    Notification::fake();

    actingAs($this->admin)->post('/admin/users', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'role' => 'user',
    ]);

    $newUser = User::where('email', 'jane@example.com')->firstOrFail();

    Notification::assertSentTo($newUser, ResetPassword::class);
});

it('allows admins to assign permissions on creation', function () {
    Notification::fake();

    $viewPermission = Permission::create([
        'name' => 'view_users',
        'display_name' => 'View Users',
        'description' => 'Access the users list.',
    ]);

    actingAs($this->admin)->post('/admin/users', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'role' => 'user',
        'permissions' => [$viewPermission->id],
    ]);

    $newUser = User::where('email', 'jane@example.com')->firstOrFail();

    expect($newUser->permissions)->toHaveCount(1)
        ->and($newUser->permissions->first()->name)->toBe('view_users');
});

it('prevents non-admins with create_user permission from assigning permissions', function () {
    Notification::fake();

    $user = User::factory()->create(['role' => Role::User]);
    $user->permissions()->attach($this->createUserPermission->id, ['granted_by' => $this->admin->id]);

    $viewPermission = Permission::create([
        'name' => 'view_users',
        'display_name' => 'View Users',
        'description' => 'Access the users list.',
    ]);

    actingAs($user)->post('/admin/users', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'role' => 'user',
        'permissions' => [$viewPermission->id],
    ]);

    $newUser = User::where('email', 'jane@example.com')->firstOrFail();

    expect($newUser->permissions)->toBeEmpty();
});

it('rejects an admin trying to assign a privileged role', function () {
    actingAs($this->admin)->post('/admin/users', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'role' => 'site_admin',
    ])->assertSessionHasErrors('role');
});

it('allows site admins to create users with any role', function () {
    Notification::fake();

    actingAs($this->siteAdmin)->post('/admin/users', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'role' => 'admin',
    ])->assertRedirect();

    expect(User::where('email', 'jane@example.com')->first()?->role)->toBe(Role::Admin);
});

it('rejects creation with a duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    actingAs($this->admin)->post('/admin/users', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'taken@example.com',
        'role' => 'user',
    ])->assertSessionHasErrors('email');
});
