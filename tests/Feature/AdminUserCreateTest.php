<?php

use App\Enum\Role;
use App\Models\Permission;
use App\Models\PermissionSet;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->withoutVite();

    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->allows('createCustomer')->andReturn(
            Customer::constructFrom(['id' => 'cus_test123'])
        );
    });

    $this->createPermission = Permission::create([
        'name' => 'create_user',
        'display_name' => 'Create Users',
        'description' => null,
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

it('allows site admins to access the create page', function () {
    actingAs($this->siteAdmin)->get('/admin/users/create')->assertOk();
});

it('allows admins to access the create page', function () {
    actingAs($this->admin)->get('/admin/users/create')->assertOk();
});

it('allows users with the create_user permission set to access the create page', function () {
    $set = PermissionSet::create(['name' => 'Staff']);
    $set->permissions()->sync([$this->createPermission->id]);

    $user = User::factory()->create(['role' => Role::User]);
    $user->userPermissionSet()->create(['permission_set_id' => $set->id, 'assigned_by' => null]);

    actingAs($user)->get('/admin/users/create')->assertOk();
});

// --- Role availability on the create page ---

it('passes all three roles to site admins', function () {
    actingAs($this->siteAdmin)
        ->get('/admin/users/create')
        ->assertInertia(fn ($page) => $page
            ->component('users/create')
            ->where('availableRoles', ['site_admin', 'admin', 'user'])
        );
});

it('passes only user role to admins', function () {
    actingAs($this->admin)
        ->get('/admin/users/create')
        ->assertInertia(fn ($page) => $page
            ->where('availableRoles', ['user'])
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
        ->and($newUser->email_verified_at)->not->toBeNull()
        ->and($newUser->stripe_customer_id)->toBe('cus_test123');
});

it('still creates the user if stripe customer creation fails', function () {
    Notification::fake();

    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->allows('createCustomer')->andThrow(
            $this->createMock(ApiErrorException::class)
        );
    });

    actingAs($this->admin)->post('/admin/users', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'role' => 'user',
    ])->assertRedirect();

    $newUser = User::where('email', 'jane@example.com')->firstOrFail();
    expect($newUser->stripe_customer_id)->toBeNull();
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
