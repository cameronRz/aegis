<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
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
    });

    $this->user = User::factory()->create();
});

it('redirects guests to login', function () {
    get(route('shop'))->assertRedirect('/login');
});

it('renders the shop page for authenticated users', function () {
    actingAs($this->user)
        ->get(route('shop'))
        ->assertInertia(fn ($page) => $page->component('shop/index'));
});

it('only shows active products', function () {
    Product::factory()->create(['name' => 'Active Product']);
    Product::factory()->inactive()->create(['name' => 'Inactive Product']);

    actingAs($this->user)
        ->get(route('shop'))
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Active Product')
        );
});

it('excludes soft-deleted products', function () {
    $product = Product::factory()->create();
    $product->delete();

    actingAs($this->user)
        ->get(route('shop'))
        ->assertInertia(fn ($page) => $page->has('products.data', 0));
});

it('searches by product name', function () {
    Product::factory()->create(['name' => 'Wireless Mouse']);
    Product::factory()->create(['name' => 'Mechanical Keyboard']);

    actingAs($this->user)
        ->get(route('shop', ['search' => 'wireless']))
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Wireless Mouse')
        );
});

it('searches by SKU', function () {
    Product::factory()->create(['sku' => 'MOU-001']);
    Product::factory()->create(['sku' => 'KEY-002']);

    actingAs($this->user)
        ->get(route('shop', ['search' => 'MOU']))
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.sku', 'MOU-001')
        );
});

it('filters by category slug', function () {
    $tools = Category::factory()->create(['name' => 'Tools', 'slug' => 'tools']);
    $courses = Category::factory()->create(['name' => 'Courses', 'slug' => 'courses']);

    Product::factory()->withCategory($tools)->create(['name' => 'Hammer']);
    Product::factory()->withCategory($courses)->create(['name' => 'PHP 101']);

    actingAs($this->user)
        ->get(route('shop', ['category' => 'tools']))
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Hammer')
        );
});

it('orders products by category then sort_order', function () {
    $a = Category::factory()->create();
    $b = Category::factory()->create();

    // Create in reverse order to confirm sorting overrides insertion order
    Product::factory()->withCategory($b)->create(['name' => 'B-Second', 'sort_order' => 2]);
    Product::factory()->withCategory($b)->create(['name' => 'B-First', 'sort_order' => 1]);
    Product::factory()->withCategory($a)->create(['name' => 'A-Only', 'sort_order' => 1]);

    actingAs($this->user)
        ->get(route('shop'))
        ->assertInertia(fn ($page) => $page
            ->where('products.data.0.name', 'A-Only')
            ->where('products.data.1.name', 'B-First')
            ->where('products.data.2.name', 'B-Second')
        );
});

it('passes active categories to the page', function () {
    Category::factory()->create(['name' => 'Tools', 'is_active' => true]);
    Category::factory()->create(['name' => 'Hidden', 'is_active' => false]);

    actingAs($this->user)
        ->get(route('shop'))
        ->assertInertia(fn ($page) => $page
            ->has('categories', 1)
            ->where('categories.0.name', 'Tools')
        );
});

it('passes filters back to the page', function () {
    actingAs($this->user)
        ->get(route('shop', ['search' => 'widget', 'category' => 'tools']))
        ->assertInertia(fn ($page) => $page
            ->where('filters.search', 'widget')
            ->where('filters.category', 'tools')
        );
});

it('passes an empty cartItems map when nothing is in the cart', function () {
    Product::factory()->create();

    actingAs($this->user)
        ->get(route('shop'))
        ->assertInertia(fn ($page) => $page->where('cartItems', []));
});

it('passes cartItems map with productId keys for products in the cart', function () {
    $product = Product::factory()->create();
    $cart = Cart::factory()->create(['user_id' => $this->user->id]);
    $item = CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id]);

    actingAs($this->user)
        ->get(route('shop'))
        ->assertInertia(fn ($page) => $page
            ->where("cartItems.{$product->id}", $item->id)
        );
});
