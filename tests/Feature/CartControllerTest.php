<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Services\StripeService;
use Mockery\MockInterface;
use Stripe\Price;
use Stripe\Product as StripeProduct;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->withoutVite();

    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->allows('createProduct')->andReturn(StripeProduct::constructFrom(['id' => 'prod_test123']));
        $mock->allows('createPrice')->andReturn(Price::constructFrom(['id' => 'price_test123']));
        $mock->allows('archiveProduct');
    });

    $this->user = User::factory()->create();
});

it('redirects guests to login', function () {
    get(route('cart'))->assertRedirect('/login');
});

it('renders the cart page', function () {
    actingAs($this->user)
        ->get(route('cart'))
        ->assertInertia(fn ($page) => $page->component('cart/index'));
});

it('adds a product to the cart', function () {
    $product = Product::factory()->create();

    actingAs($this->user)
        ->post(route('cart.items.store'), ['product_id' => $product->id])
        ->assertRedirect();

    expect(Cart::where('user_id', $this->user->id)->first()->items()->count())->toBe(1);
});

it('returns an error for inactive products', function () {
    $product = Product::factory()->inactive()->create();

    actingAs($this->user)
        ->post(route('cart.items.store'), ['product_id' => $product->id])
        ->assertSessionHasErrors('cart');
});

it('updates a cart item quantity', function () {
    $cart = Cart::factory()->create(['user_id' => $this->user->id]);
    $item = CartItem::factory()->create(['cart_id' => $cart->id, 'quantity' => 1]);

    actingAs($this->user)
        ->patch(route('cart.items.update', $item), ['quantity' => 3])
        ->assertRedirect();

    expect($item->fresh()->quantity)->toBe(3);
});

it('prevents updating another user\'s cart item', function () {
    $other = User::factory()->create();
    $cart = Cart::factory()->create(['user_id' => $other->id]);
    $item = CartItem::factory()->create(['cart_id' => $cart->id]);

    actingAs($this->user)
        ->patch(route('cart.items.update', $item), ['quantity' => 2])
        ->assertForbidden();
});

it('removes a cart item', function () {
    $cart = Cart::factory()->create(['user_id' => $this->user->id]);
    $item = CartItem::factory()->create(['cart_id' => $cart->id]);

    actingAs($this->user)
        ->delete(route('cart.items.destroy', $item))
        ->assertRedirect();

    expect(CartItem::find($item->id))->toBeNull();
});

it('prevents removing another user\'s cart item', function () {
    $other = User::factory()->create();
    $cart = Cart::factory()->create(['user_id' => $other->id]);
    $item = CartItem::factory()->create(['cart_id' => $cart->id]);

    actingAs($this->user)
        ->delete(route('cart.items.destroy', $item))
        ->assertForbidden();
});

it('clears all cart items', function () {
    $cart = Cart::factory()->create(['user_id' => $this->user->id]);
    CartItem::factory()->count(3)->create(['cart_id' => $cart->id]);

    actingAs($this->user)
        ->delete(route('cart.clear'))
        ->assertRedirect();

    expect($cart->items()->count())->toBe(0);
});

it('nulls out the product on a cart item when the product is force-deleted', function () {
    $cart = Cart::factory()->create(['user_id' => $this->user->id]);
    $product = Product::factory()->create();
    $item = CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id]);

    $product->forceDelete();

    expect($item->fresh())
        ->product_id->toBeNull()
        ->exists->toBeTrue();
});

it('returns unavailable items separately from the cart page', function () {
    $cart = Cart::factory()->create(['user_id' => $this->user->id]);
    $available = Product::factory()->create();
    CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $available->id]);
    $unavailable = CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => null]);

    actingAs($this->user)
        ->get(route('cart'))
        ->assertInertia(fn ($page) => $page
            ->component('cart/index')
            ->has('cart.items', 1)
            ->has('unavailableItems', 1)
            ->where('unavailableItems.0.id', $unavailable->id)
        );
});
