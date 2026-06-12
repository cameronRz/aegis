<?php

use App\Models\User;
use App\Services\StripeService;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();

    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->allows('createProduct');
        $mock->allows('createPrice');
    });
});

// --- guests redirected ---

it('redirects guests from checkout store', function () {
    post(route('checkout.store'))->assertRedirect(route('login'));
});

it('redirects guests from checkout success', function () {
    get(route('checkout.success'))->assertRedirect(route('login'));
});

it('redirects guests from checkout cancel', function () {
    get(route('checkout.cancel'))->assertRedirect(route('login'));
});

// --- authenticated access ---

it('checkout store rejects an empty cart', function () {
    // Empty cart → 302 back with validation error; confirms route is auth-protected and reachable
    actingAs($this->user)
        ->post(route('checkout.store'))
        ->assertSessionHasErrors('checkout');
});

it('checkout success returns 400 without a session_id', function () {
    actingAs($this->user)
        ->get(route('checkout.success'))
        ->assertStatus(400);
});

it('checkout cancel renders for authenticated users', function () {
    actingAs($this->user)
        ->get(route('checkout.cancel'))
        ->assertInertia(fn ($page) => $page->component('checkout/cancel'));
});
