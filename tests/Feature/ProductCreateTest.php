<?php

use App\Enum\BillingInterval;
use App\Enum\PriceType;
use App\Enum\ProductType;
use App\Enum\Role;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Stripe\Price;
use Stripe\Product as StripeProduct;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('public');

    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->allows('createProduct')->andReturn(StripeProduct::constructFrom(['id' => 'prod_test123']));
        $mock->allows('createPrice')->andReturn(Price::constructFrom(['id' => 'price_test123']));
    });

    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->createPermission = Permission::create([
        'name' => 'create_product',
        'display_name' => 'Create Product',
        'description' => 'Create a new product.',
    ]);
});

function validProductPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Widget',
        'sku' => 'TW-0001',
        'description' => 'A test product.',
        'category_id' => null,
        'type' => 'physical',
        'price' => 2999,
        'price_type' => 'one_time',
        'billing_interval' => null,
        'billing_interval_count' => null,
        'trial_period_days' => null,
        'stock_quantity' => null,
        'track_inventory' => false,
        'is_active' => true,
        'image' => UploadedFile::fake()->image('product.jpg'),
    ], $overrides);
}

// --- create page ---

it('redirects guests from create page', function () {
    get('/admin/products/create')->assertRedirect('/login');
});

it('forbids users without create_product from the create page', function () {
    $user = User::factory()->create(['role' => Role::User]);

    actingAs($user)->get('/admin/products/create')->assertForbidden();
});

it('renders the create page for an admin', function () {
    actingAs($this->admin)
        ->get('/admin/products/create')
        ->assertInertia(fn ($page) => $page->component('products/create')->has('categories'));
});

it('renders the create page for a user with create_product permission', function () {
    $user = User::factory()->create(['role' => Role::User]);
    $user->permissions()->attach($this->createPermission->id, ['granted_by' => $this->admin->id]);

    actingAs($user)
        ->get('/admin/products/create')
        ->assertInertia(fn ($page) => $page->component('products/create'));
});

// --- store ---

it('forbids users without create_product from storing', function () {
    $user = User::factory()->create(['role' => Role::User]);

    actingAs($user)->post('/admin/products', validProductPayload())->assertForbidden();
});

it('creates a physical product and redirects', function () {
    actingAs($this->admin)
        ->post('/admin/products', validProductPayload())
        ->assertRedirect('/admin/products');

    $product = Product::first();
    expect($product->name)->toBe('Test Widget');
    expect($product->sku)->toBe('TW-0001');
    expect($product->price)->toBe(2999);
    expect($product->type)->toBe(ProductType::Physical);
    expect($product->price_type)->toBe(PriceType::OneTime);
    Storage::disk('public')->assertExists($product->image);
});

it('auto-assigns sort_order via the Sortable trait', function () {
    actingAs($this->admin)->post('/admin/products', validProductPayload(['sku' => 'A-001']));
    actingAs($this->admin)->post('/admin/products', validProductPayload(['sku' => 'A-002']));

    $products = Product::ordered()->get();
    expect($products[0]->sort_order)->toBe(1);
    expect($products[1]->sort_order)->toBe(2);
});

it('scopes sort_order per category', function () {
    $catA = Category::factory()->create();
    $catB = Category::factory()->create();

    actingAs($this->admin)->post('/admin/products', validProductPayload(['sku' => 'C1', 'category_id' => $catA->id]));
    actingAs($this->admin)->post('/admin/products', validProductPayload(['sku' => 'C2', 'category_id' => $catA->id]));
    actingAs($this->admin)->post('/admin/products', validProductPayload(['sku' => 'C3', 'category_id' => $catB->id]));

    $catAProducts = Product::where('category_id', $catA->id)->ordered()->get();
    $catBProducts = Product::where('category_id', $catB->id)->ordered()->get();

    expect($catAProducts[0]->sort_order)->toBe(1);
    expect($catAProducts[1]->sort_order)->toBe(2);
    expect($catBProducts[0]->sort_order)->toBe(1);
});

it('creates a subscription product with billing fields', function () {
    actingAs($this->admin)->post('/admin/products', validProductPayload([
        'type' => 'subscription',
        'price_type' => 'recurring',
        'billing_interval' => 'monthly',
        'billing_interval_count' => 1,
        'trial_period_days' => 14,
    ]));

    $product = Product::first();
    expect($product->type)->toBe(ProductType::Subscription);
    expect($product->price_type)->toBe(PriceType::Recurring);
    expect($product->billing_interval)->toBe(BillingInterval::Monthly);
    expect($product->billing_interval_count)->toBe(1);
    expect($product->trial_period_days)->toBe(14);
});

it('creates a physical product with inventory tracking', function () {
    actingAs($this->admin)->post('/admin/products', validProductPayload([
        'track_inventory' => true,
        'stock_quantity' => 50,
    ]));

    $product = Product::first();
    expect($product->track_inventory)->toBeTrue();
    expect($product->stock_quantity)->toBe(50);
});

it('requires billing_interval for subscription type', function () {
    actingAs($this->admin)
        ->post('/admin/products', validProductPayload([
            'type' => 'subscription',
            'price_type' => 'recurring',
            'billing_interval' => null,
            'billing_interval_count' => null,
        ]))
        ->assertSessionHasErrors(['billing_interval', 'billing_interval_count']);
});

it('requires stock_quantity when track_inventory is true', function () {
    actingAs($this->admin)
        ->post('/admin/products', validProductPayload([
            'track_inventory' => true,
            'stock_quantity' => null,
        ]))
        ->assertSessionHasErrors('stock_quantity');
});

it('requires a unique SKU', function () {
    Product::factory()->create(['sku' => 'DUPE-001']);

    actingAs($this->admin)
        ->post('/admin/products', validProductPayload(['sku' => 'DUPE-001']))
        ->assertSessionHasErrors('sku');
});

it('rejects non-image file uploads', function () {
    actingAs($this->admin)
        ->post('/admin/products', validProductPayload([
            'image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ]))
        ->assertSessionHasErrors('image');
});
