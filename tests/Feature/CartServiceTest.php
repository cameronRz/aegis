<?php

use App\Exceptions\CartException;
use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;

beforeEach(function () {
    $this->service = app(CartService::class);
    $this->user = User::factory()->create();
    $this->cart = Cart::factory()->create(['user_id' => $this->user->id]);
});

it('creates a cart if one does not exist', function () {
    $user = User::factory()->create();

    $cart = $this->service->getOrCreate($user);

    expect($cart->user_id)->toBe($user->id)
        ->and(Cart::where('user_id', $user->id)->count())->toBe(1);
});

it('returns the existing cart on subsequent calls', function () {
    $this->service->getOrCreate($this->user);
    $this->service->getOrCreate($this->user);

    expect(Cart::where('user_id', $this->user->id)->count())->toBe(1);
});

it('adds a product to the cart', function () {
    $product = Product::factory()->create();

    $this->service->add($this->cart, $product);

    expect($this->cart->items()->count())->toBe(1)
        ->and($this->cart->items()->first()->product_id)->toBe($product->id);
});

it('increments quantity when adding an existing item', function () {
    $product = Product::factory()->create();
    $this->service->add($this->cart, $product);
    $this->service->add($this->cart, $product);

    expect($this->cart->items()->first()->quantity)->toBe(2);
});

it('throws when adding an inactive product', function () {
    $product = Product::factory()->inactive()->create();

    expect(fn () => $this->service->add($this->cart, $product))
        ->toThrow(CartException::class);
});

it('throws when adding a subscription beyond qty 1', function () {
    $product = Product::factory()->subscription()->create();
    $this->service->add($this->cart, $product);

    expect(fn () => $this->service->add($this->cart, $product))
        ->toThrow(CartException::class);
});

it('throws when adding more than available stock', function () {
    $product = Product::factory()->physical()->create([
        'track_inventory' => true,
        'stock_quantity' => 1,
    ]);

    expect(fn () => $this->service->add($this->cart, $product, 2))
        ->toThrow(CartException::class);
});

it('updates item quantity', function () {
    $product = Product::factory()->create();
    $item = $this->service->add($this->cart, $product);

    $this->service->updateQuantity($item, 3);

    expect($item->fresh()->quantity)->toBe(3);
});

it('removes an item', function () {
    $product = Product::factory()->create();
    $item = $this->service->add($this->cart, $product);

    $this->service->remove($item);

    expect($this->cart->items()->count())->toBe(0);
});

it('clears all items', function () {
    Product::factory()->count(3)->create()
        ->each(fn ($p) => $this->service->add($this->cart, $p));

    $this->service->clear($this->cart);

    expect($this->cart->items()->count())->toBe(0);
});

it('calculates the cart total', function () {
    $a = Product::factory()->create(['price' => 1000]);
    $b = Product::factory()->create(['price' => 500]);
    $this->service->add($this->cart, $a);
    $this->service->add($this->cart, $b);
    $this->cart->load('items.product');

    expect($this->service->total($this->cart))->toBe(1500);
});

it('detects subscription products in the cart', function () {
    $sub = Product::factory()->subscription()->create();
    $this->service->add($this->cart, $sub);
    $this->cart->load('items.product');

    expect($this->service->hasSubscription($this->cart))->toBeTrue();
});

it('syncs cart count to session after mutation', function () {
    $product = Product::factory()->create();
    $this->service->add($this->cart, $product);

    expect(session('cart_count'))->toBe(1);
});
