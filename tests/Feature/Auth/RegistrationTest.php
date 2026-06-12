<?php

use App\Models\User;
use App\Services\StripeService;
use Laravel\Fortify\Features;
use Mockery\MockInterface;
use Stripe\Customer;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->expects('createCustomer')->once()->andReturn(
            Customer::constructFrom(['id' => 'cus_test123'])
        );
    });

    $response = $this->post(route('register.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
    expect(User::where('email', 'test@example.com')->first()->stripe_customer_id)->toBe('cus_test123');
});
