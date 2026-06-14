<?php

use App\Enum\Tier;
use App\Models\Permission;
use App\Models\Role;
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

    $this->siteAdmin = User::factory()->create(['tier' => Tier::SiteAdmin]);
    $this->admin = User::factory()->create(['tier' => Tier::Admin]);
});

// --- Access ---

it('redirects guests to login', function () {
    get('/admin/users/create')->assertRedirect('/login');
});

it('forbids regular users without the permission', function () {
    $user = User::factory()->create(['tier' => Tier::User]);

    actingAs($user)->get('/admin/users/create')->assertForbidden();
});

it('allows site admins to access the create page', function () {
    actingAs($this->siteAdmin)->get('/admin/users/create')->assertOk();
});

it('allows admins to access the create page', function () {
    actingAs($this->admin)->get('/admin/users/create')->assertOk();
});

it('allows users with the create_user role to access the create page', function () {
    $role = Role::create(['name' => 'Staff']);
    $role->permissions()->sync([$this->createPermission->id]);

    $user = User::factory()->create(['tier' => Tier::User]);
    $user->roles()->attach($role->id, ['assigned_by' => null]);

    actingAs($user)->get('/admin/users/create')->assertOk();
});

// --- Role availability on the create page ---

it('passes all three roles to site admins', function () {
    actingAs($this->siteAdmin)
        ->get('/admin/users/create')
        ->assertInertia(fn ($page) => $page
            ->component('users/create')
            ->where('availableTiers', ['site_admin', 'admin', 'user'])
        );
});

it('passes only user role to admins', function () {
    actingAs($this->admin)
        ->get('/admin/users/create')
        ->assertInertia(fn ($page) => $page
            ->where('availableTiers', ['user'])
        );
});

// --- Storing users ---

it('creates a user and redirects to their show page', function () {
    Notification::fake();

    $response = actingAs($this->admin)->post('/admin/users', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'tier' => 'user',
    ]);

    $newUser = User::where('email', 'jane@example.com')->firstOrFail();

    $response->assertRedirect("/admin/users/{$newUser->id}");

    expect($newUser->first_name)->toBe('Jane')
        ->and($newUser->last_name)->toBe('Doe')
        ->and($newUser->tier)->toBe(Tier::User)
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
        'tier' => 'user',
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
        'tier' => 'user',
    ]);

    $newUser = User::where('email', 'jane@example.com')->firstOrFail();

    Notification::assertSentTo($newUser, ResetPassword::class);
});

it('rejects an admin trying to assign a privileged role', function () {
    actingAs($this->admin)->post('/admin/users', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'tier' => 'site_admin',
    ])->assertSessionHasErrors('tier');
});

it('allows site admins to create users with any role', function () {
    Notification::fake();

    actingAs($this->siteAdmin)->post('/admin/users', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'tier' => 'admin',
    ])->assertRedirect();

    expect(User::where('email', 'jane@example.com')->first()?->tier)->toBe(Tier::Admin);
});

it('passes roles to the create page', function () {
    Role::factory()->create(['name' => 'Support Staff']);

    actingAs($this->admin)
        ->get('/admin/users/create')
        ->assertInertia(fn ($page) => $page->has('roles', 1));
});

it('assigns roles when creating a user', function () {
    Notification::fake();

    $role = Role::factory()->create();

    actingAs($this->admin)->post('/admin/users', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'tier' => 'user',
        'role_ids' => [$role->id],
    ])->assertRedirect();

    $newUser = User::where('email', 'jane@example.com')->firstOrFail();
    expect($newUser->roles()->where('role_id', $role->id)->exists())->toBeTrue();
});

it('creates a user without roles when none are provided', function () {
    Notification::fake();

    actingAs($this->admin)->post('/admin/users', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'tier' => 'user',
    ])->assertRedirect();

    $newUser = User::where('email', 'jane@example.com')->firstOrFail();
    expect($newUser->roles()->count())->toBe(0);
});

it('rejects creation with a duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    actingAs($this->admin)->post('/admin/users', [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'taken@example.com',
        'tier' => 'user',
    ])->assertSessionHasErrors('email');
});
