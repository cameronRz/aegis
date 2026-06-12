<?php

use App\Models\Product;
use App\Services\StripeService;
use Mockery\MockInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\Product as StripeProduct;

beforeEach(function () {
    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->allows('createProduct')->andReturn(
            StripeProduct::constructFrom(['id' => 'prod_test123'])
        );
        $mock->allows('createPrice')->andReturn(
            Price::constructFrom(['id' => 'price_test123'])
        );
        $mock->allows('updateProduct');
        $mock->allows('archivePrice');
        $mock->allows('archiveProduct');
    });
});

// --- created ---

it('syncs a new product to stripe on creation', function () {
    $product = Product::factory()->create();

    expect($product->fresh()->stripe_product_id)->toBe('prod_test123')
        ->and($product->fresh()->stripe_price_id)->toBe('price_test123');
});

it('logs and does not throw if stripe product creation fails', function () {
    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->allows('createProduct')->andThrow(
            $this->createMock(ApiErrorException::class)
        );
    });

    expect(fn () => Product::factory()->create())->not->toThrow(Exception::class);
});

// --- updated ---

it('updates the stripe product when name or description changes', function () {
    $product = Product::factory()->create([
        'stripe_product_id' => 'prod_test123',
        'stripe_price_id' => 'price_test123',
    ]);

    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->expects('updateProduct')->once()->with('prod_test123', Mockery::any());
        $mock->allows('createPrice');
        $mock->allows('archivePrice');
    });

    $product->update(['name' => 'New Name']);
});

it('archives the old price and creates a new one when price changes', function () {
    $product = Product::withoutEvents(
        fn () => Product::factory()->subscription()->create([
            'stripe_product_id' => 'prod_test123',
            'stripe_price_id' => 'price_old',
        ])
    );

    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->expects('archivePrice')->once()->with('price_old');
        $mock->expects('createPrice')->once()->andReturn(
            Price::constructFrom(['id' => 'price_new'])
        );
        $mock->allows('updateProduct');
    });

    $product->update(['price' => 9999]);

    expect($product->fresh()->stripe_price_id)->toBe('price_new');
});

it('does nothing on update when stripe_product_id is missing', function () {
    $product = Product::withoutEvents(
        fn () => Product::factory()->create(['stripe_product_id' => null, 'stripe_price_id' => null])
    );

    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->expects('updateProduct')->never();
        $mock->expects('archivePrice')->never();
        $mock->expects('createPrice')->never();
    });

    $product->update(['name' => 'No Stripe Yet']);
});

// --- forceDeleted ---

it('archives the stripe product on force delete', function () {
    $product = Product::factory()->create([
        'stripe_product_id' => 'prod_test123',
        'stripe_price_id' => 'price_test123',
    ]);

    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->expects('archiveProduct')->once()->with('prod_test123');
    });

    $product->forceDelete();
});

it('skips stripe archiving on force delete when stripe_product_id is missing', function () {
    $product = Product::withoutEvents(
        fn () => Product::factory()->create(['stripe_product_id' => null])
    );

    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->expects('archiveProduct')->never();
    });

    $product->forceDelete();
});
