<?php

use App\Enum\OrderStatus;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\StripeService;
use Mockery\MockInterface;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\Product as StripeProduct;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create(['stripe_customer_id' => 'cus_test123']);

    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->allows('createProduct')->andReturn(StripeProduct::constructFrom(['id' => 'prod_test123']));
        $mock->allows('createPrice')->andReturn(Price::constructFrom(['id' => 'price_test123']));
        $mock->allows('createCustomer')->andReturn(Customer::constructFrom(['id' => 'cus_test123']));
        $mock->allows('createCheckoutSession')->andReturn(
            CheckoutSession::constructFrom(['id' => 'cs_test123', 'url' => 'https://checkout.stripe.com/test'])
        );
    });
});

// --- store ---

it('creates a pending order and redirects to stripe on checkout', function () {
    $product = Product::factory()->physical()->create(['stripe_price_id' => 'price_test123']);
    $cart = Cart::factory()->create(['user_id' => $this->user->id]);
    app(CartService::class)->add($cart, $product);

    // X-Inertia header triggers 409 + X-Inertia-Location instead of a plain 302
    $response = actingAs($this->user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('checkout.store'));

    $response->assertStatus(409);
    $response->assertHeader('X-Inertia-Location', 'https://checkout.stripe.com/test');

    $order = Order::where('user_id', $this->user->id)->firstOrFail();
    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->order_number)->toMatch('/^ORD-\d{6}$/')
        ->and($order->stripe_checkout_session_id)->toBe('cs_test123')
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->product_name)->toBe($product->name);
});

it('snapshots product details onto order items', function () {
    $product = Product::factory()->physical()->create([
        'name' => 'Widget Pro',
        'sku' => 'WP-001',
        'price' => 4999,
        'stripe_price_id' => 'price_test123',
    ]);
    $cart = Cart::factory()->create(['user_id' => $this->user->id]);
    app(CartService::class)->add($cart, $product);

    actingAs($this->user)->post(route('checkout.store'));

    $item = Order::first()->items->first();
    expect($item->product_name)->toBe('Widget Pro')
        ->and($item->product_sku)->toBe('WP-001')
        ->and($item->price)->toBe(4999)
        ->and($item->product_type)->toBe('physical');
});

it('rejects checkout when cart is empty', function () {
    Cart::factory()->create(['user_id' => $this->user->id]);

    actingAs($this->user)
        ->post(route('checkout.store'))
        ->assertSessionHasErrors('checkout');
});

it('rejects checkout when a cart item product is inactive', function () {
    $active = Product::factory()->physical()->create(['stripe_price_id' => 'price_test123']);
    $inactive = Product::factory()->inactive()->create(['stripe_price_id' => 'price_test123']);
    $cart = Cart::factory()->create(['user_id' => $this->user->id]);
    $cartService = app(CartService::class);

    // Add active product, then deactivate it to simulate going stale
    $cartService->add($cart, $active);
    $active->update(['is_active' => false]);

    actingAs($this->user)
        ->post(route('checkout.store'))
        ->assertSessionHasErrors('checkout');

    expect(Order::count())->toBe(0);
});

it('creates a stripe customer lazily if missing at checkout', function () {
    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->allows('createProduct')->andReturn(StripeProduct::constructFrom(['id' => 'prod_test123']));
        $mock->allows('createPrice')->andReturn(Price::constructFrom(['id' => 'price_test123']));
        $mock->allows('createCustomer')->andReturn(Customer::constructFrom(['id' => 'cus_lazy456']));
        $mock->allows('createCheckoutSession')->andReturn(
            CheckoutSession::constructFrom(['id' => 'cs_test123', 'url' => 'https://checkout.stripe.com/test'])
        );
    });

    $user = User::factory()->create(['stripe_customer_id' => null]);
    $product = Product::factory()->physical()->create(['stripe_price_id' => 'price_test123']);
    $cart = Cart::factory()->create(['user_id' => $user->id]);
    app(CartService::class)->add($cart, $product);

    actingAs($user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('checkout.store'))
        ->assertStatus(409);

    expect($user->fresh()->stripe_customer_id)->toBe('cus_lazy456');
});

it('uses subscription mode when cart contains a subscription product', function () {
    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->allows('createProduct')->andReturn(StripeProduct::constructFrom(['id' => 'prod_test123']));
        $mock->allows('createPrice')->andReturn(Price::constructFrom(['id' => 'price_test123']));
        $mock->expects('createCheckoutSession')
            ->once()
            ->withArgs(fn (array $params) => $params['mode'] === 'subscription')
            ->andReturn(CheckoutSession::constructFrom(['id' => 'cs_test123', 'url' => 'https://checkout.stripe.com/test']));
    });

    $product = Product::factory()->subscription()->create(['stripe_price_id' => 'price_test123']);
    $cart = Cart::factory()->create(['user_id' => $this->user->id]);
    app(CartService::class)->add($cart, $product);

    actingAs($this->user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('checkout.store'))
        ->assertStatus(409);
});

it('passes trial_period_days for subscription products', function () {
    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->allows('createProduct')->andReturn(StripeProduct::constructFrom(['id' => 'prod_test123']));
        $mock->allows('createPrice')->andReturn(Price::constructFrom(['id' => 'price_test123']));
        $mock->expects('createCheckoutSession')
            ->once()
            ->withArgs(fn (array $params) => ($params['subscription_data']['trial_period_days'] ?? null) === 14)
            ->andReturn(CheckoutSession::constructFrom(['id' => 'cs_test123', 'url' => 'https://checkout.stripe.com/test']));
    });

    $product = Product::factory()->subscription()->create([
        'stripe_price_id' => 'price_test123',
        'trial_period_days' => 14,
    ]);
    $cart = Cart::factory()->create(['user_id' => $this->user->id]);
    app(CartService::class)->add($cart, $product);

    actingAs($this->user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('checkout.store'))
        ->assertStatus(409);
});

it('returns errors when stripe session creation fails', function () {
    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->allows('createProduct')->andReturn(StripeProduct::constructFrom(['id' => 'prod_test123']));
        $mock->allows('createPrice')->andReturn(Price::constructFrom(['id' => 'price_test123']));
        $mock->allows('createCheckoutSession')->andThrow(
            $this->createMock(ApiErrorException::class)
        );
    });

    $product = Product::factory()->physical()->create(['stripe_price_id' => 'price_test123']);
    $cart = Cart::factory()->create(['user_id' => $this->user->id]);
    app(CartService::class)->add($cart, $product);

    actingAs($this->user)
        ->post(route('checkout.store'))
        ->assertSessionHasErrors('checkout');
});

// --- success ---

it('renders the success page for the order owner', function () {
    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'stripe_checkout_session_id' => 'cs_test123',
        'status' => OrderStatus::Paid,
    ]);

    actingAs($this->user)
        ->get(route('checkout.success', ['session_id' => 'cs_test123']))
        ->assertInertia(fn ($page) => $page->component('checkout/success'));
});

it('returns 403 if success page accessed by wrong user', function () {
    $other = User::factory()->create();
    Order::factory()->create([
        'user_id' => $other->id,
        'stripe_checkout_session_id' => 'cs_test123',
    ]);

    actingAs($this->user)
        ->get(route('checkout.success', ['session_id' => 'cs_test123']))
        ->assertForbidden();
});

it('returns 400 when success page has no session_id', function () {
    actingAs($this->user)
        ->get(route('checkout.success'))
        ->assertStatus(400);
});

// --- cancel ---

it('renders the cancel page', function () {
    actingAs($this->user)
        ->get(route('checkout.cancel'))
        ->assertInertia(fn ($page) => $page->component('checkout/cancel'));
});
