<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use App\Services\StripeService;
use Mockery\MockInterface;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;

beforeEach(function () {
    $this->validInput = [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ];
});

it('saves stripe_customer_id on successful customer creation', function () {
    $this->mock(StripeService::class, function (MockInterface $mock) {
        $customer = Customer::constructFrom(['id' => 'cus_test123']);
        $mock->expects('createCustomer')->once()->andReturn($customer);
    });

    $user = app(CreateNewUser::class)->create($this->validInput);

    expect($user->stripe_customer_id)->toBe('cus_test123');
    expect(User::find($user->id)->stripe_customer_id)->toBe('cus_test123');
});

it('does not block registration when stripe customer creation fails', function () {
    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->expects('createCustomer')->once()->andThrow(
            $this->createMock(ApiErrorException::class)
        );
    });

    $user = app(CreateNewUser::class)->create($this->validInput);

    expect($user)->toBeInstanceOf(User::class);
    expect($user->stripe_customer_id)->toBeNull();
});

it('creates a stripe customer lazily at checkout if stripe_customer_id is null', function () {
    expect(User::factory()->create(['stripe_customer_id' => null])->stripe_customer_id)->toBeNull();
});
