<?php

use App\Enum\SubscriptionStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StripeService;
use Mockery\MockInterface;
use Stripe\BillingPortal\Session as BillingPortalSession;
use Stripe\Price;
use Stripe\Product as StripeProduct;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create(['stripe_customer_id' => 'cus_test']);

    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->allows('createProduct')->andReturn(StripeProduct::constructFrom(['id' => 'prod_test']));
        $mock->allows('createPrice')->andReturn(Price::constructFrom(['id' => 'price_test']));
        $mock->allows('createCustomer');
    });
});

// --- orders: guest redirect ---

it('redirects guests from orders index', function () {
    get(route('orders'))->assertRedirect(route('login'));
});

it('redirects guests from order show', function () {
    $order = Order::factory()->create();
    get(route('orders.show', $order))->assertRedirect(route('login'));
});

// --- orders: index ---

it('renders the orders index for authenticated users', function () {
    Order::factory()->count(3)->create(['user_id' => $this->user->id]);

    actingAs($this->user)
        ->get(route('orders'))
        ->assertInertia(fn ($page) => $page
            ->component('orders/index')
            ->has('orders.data', 3)
        );
});

it('only shows the authenticated users own orders', function () {
    $other = User::factory()->create();
    Order::factory()->count(2)->create(['user_id' => $other->id]);
    Order::factory()->create(['user_id' => $this->user->id]);

    actingAs($this->user)
        ->get(route('orders'))
        ->assertInertia(fn ($page) => $page->has('orders.data', 1));
});

// --- orders: show ---

it('renders the order show page for the owner', function () {
    $order = Order::factory()->paid()->create(['user_id' => $this->user->id]);
    OrderItem::factory()->create(['order_id' => $order->id]);

    actingAs($this->user)
        ->get(route('orders.show', $order))
        ->assertInertia(fn ($page) => $page
            ->component('orders/show')
            ->has('order.items', 1)
        );
});

it('returns 403 when a user tries to view another users order', function () {
    $other = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $other->id]);

    actingAs($this->user)
        ->get(route('orders.show', $order))
        ->assertForbidden();
});

// --- subscriptions: guest redirect ---

it('redirects guests from subscriptions index', function () {
    get(route('subscriptions'))->assertRedirect(route('login'));
});

// --- subscriptions: index ---

it('renders the subscriptions index for authenticated users', function () {
    Subscription::factory()->count(2)->create(['user_id' => $this->user->id]);

    actingAs($this->user)
        ->get(route('subscriptions'))
        ->assertInertia(fn ($page) => $page
            ->component('subscriptions/index')
            ->has('subscriptions', 2)
        );
});

it('only shows the authenticated users own subscriptions', function () {
    $other = User::factory()->create();
    Subscription::factory()->create(['user_id' => $other->id]);
    Subscription::factory()->create(['user_id' => $this->user->id]);

    actingAs($this->user)
        ->get(route('subscriptions'))
        ->assertInertia(fn ($page) => $page->has('subscriptions', 1));
});

// --- subscriptions: cancel ---

it('sets cancel_at_period_end and calls stripe on cancel', function () {
    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->allows('createProduct')->andReturn(StripeProduct::constructFrom(['id' => 'prod_test']));
        $mock->allows('createPrice')->andReturn(Price::constructFrom(['id' => 'price_test']));
        $mock->expects('cancelSubscription')
            ->once()
            ->withArgs(fn ($id, $atPeriodEnd) => $id === 'sub_test' && $atPeriodEnd === true);
    });

    $sub = Subscription::factory()->create([
        'user_id' => $this->user->id,
        'stripe_subscription_id' => 'sub_test',
        'status' => SubscriptionStatus::Active,
        'cancel_at_period_end' => false,
    ]);

    actingAs($this->user)
        ->post(route('subscriptions.cancel', $sub))
        ->assertRedirect();

    expect($sub->fresh()->cancel_at_period_end)->toBeTrue();
});

it('returns 403 when a user tries to cancel another users subscription', function () {
    $other = User::factory()->create();
    $sub = Subscription::factory()->create(['user_id' => $other->id]);

    actingAs($this->user)
        ->post(route('subscriptions.cancel', $sub))
        ->assertForbidden();
});

// --- billing portal ---

it('redirects to stripe billing portal', function () {
    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->allows('createProduct')->andReturn(StripeProduct::constructFrom(['id' => 'prod_test']));
        $mock->allows('createPrice')->andReturn(Price::constructFrom(['id' => 'price_test']));
        $mock->expects('createBillingPortalSession')
            ->once()
            ->andReturn(BillingPortalSession::constructFrom(['url' => 'https://billing.stripe.com/test']));
    });

    actingAs($this->user)
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('billing.portal'))
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://billing.stripe.com/test');
});

it('returns 422 for billing portal when user has no stripe customer id', function () {
    $user = User::factory()->create(['stripe_customer_id' => null]);

    actingAs($user)
        ->post(route('billing.portal'))
        ->assertStatus(422);
});
