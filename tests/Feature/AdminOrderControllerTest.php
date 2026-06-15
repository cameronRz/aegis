<?php

use App\Enum\OrderStatus;
use App\Enum\Tier;
use App\Models\Order;
use App\Models\OrderItem;
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
        $mock->allows('createProduct')->andReturn(StripeProduct::constructFrom(['id' => 'prod_test']));
        $mock->allows('createPrice')->andReturn(Price::constructFrom(['id' => 'price_test']));
        $mock->allows('createCustomer');
    });

    $this->admin = User::factory()->create(['tier' => Tier::Admin]);
    $this->user = User::factory()->create();
});

// --- access control ---

it('redirects guests from admin orders index', function () {
    get(route('admin.orders'))->assertRedirect(route('login'));
});

it('returns 403 for non-admins on admin orders index', function () {
    actingAs($this->user)
        ->get(route('admin.orders'))
        ->assertForbidden();
});

it('returns 403 for non-admins on admin orders show', function () {
    $order = Order::factory()->create();

    actingAs($this->user)
        ->get(route('admin.orders.show', $order))
        ->assertForbidden();
});

// --- index ---

it('renders the admin orders index for admins', function () {
    Order::factory()->count(3)->create();

    actingAs($this->admin)
        ->get(route('admin.orders'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/orders/index')
            ->has('orders.data', 3)
        );
});

it('searches orders by order number', function () {
    $target = Order::factory()->create();
    Order::factory()->count(2)->create();

    actingAs($this->admin)
        ->get(route('admin.orders', ['search' => $target->order_number]))
        ->assertInertia(fn ($page) => $page->has('orders.data', 1));
});

it('searches orders by client email', function () {
    $client = User::factory()->create(['email' => 'findme@example.com']);
    Order::factory()->create(['user_id' => $client->id]);
    Order::factory()->count(2)->create();

    actingAs($this->admin)
        ->get(route('admin.orders', ['search' => 'findme']))
        ->assertInertia(fn ($page) => $page->has('orders.data', 1));
});

it('searches orders by client name', function () {
    $client = User::factory()->create(['first_name' => 'Unique', 'last_name' => 'Testson']);
    Order::factory()->create(['user_id' => $client->id]);
    Order::factory()->count(2)->create();

    actingAs($this->admin)
        ->get(route('admin.orders', ['search' => 'Testson']))
        ->assertInertia(fn ($page) => $page->has('orders.data', 1));
});

// --- show ---

it('renders the admin order show page', function () {
    $order = Order::factory()->paid()->create(['user_id' => $this->user->id]);
    OrderItem::factory()->count(2)->create(['order_id' => $order->id]);

    actingAs($this->admin)
        ->get(route('admin.orders.show', $order))
        ->assertInertia(fn ($page) => $page
            ->component('admin/orders/show')
            ->has('order.items', 2)
            ->has('order.user')
        );
});
