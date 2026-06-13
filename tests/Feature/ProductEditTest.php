<?php

use App\Enum\Role;
use App\Models\Category;
use App\Models\Permission;
use App\Models\PermissionSet;
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
        $mock->allows('updateProduct');
        $mock->allows('archivePrice');
    });

    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->editPermission = Permission::create([
        'name' => 'edit_product',
        'display_name' => 'Edit Product',
        'description' => 'Edit products.',
    ]);
    $this->product = Product::factory()->create();
});

// --- edit page ---

it('redirects guests from the edit page', function () {
    get("/admin/products/{$this->product->id}/edit")->assertRedirect('/login');
});

it('forbids users without edit_product from the edit page', function () {
    $user = User::factory()->create(['role' => Role::User]);

    actingAs($user)->get("/admin/products/{$this->product->id}/edit")->assertForbidden();
});

it('renders the edit page for an admin', function () {
    actingAs($this->admin)
        ->get("/admin/products/{$this->product->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->component('products/edit')
            ->has('product')
            ->has('categories')
            ->has('imageUrl'),
        );
});

it('renders the edit page for a user with edit_product permission', function () {
    $user = User::factory()->create(['role' => Role::User]);
    $set = PermissionSet::create(['name' => 'Staff']);
    $set->permissions()->sync([$this->editPermission->id]);
    $user->userPermissionSet()->create(['permission_set_id' => $set->id, 'assigned_by' => $this->admin->id]);

    actingAs($user)
        ->get("/admin/products/{$this->product->id}/edit")
        ->assertInertia(fn ($page) => $page->component('products/edit'));
});

// --- update ---

it('forbids users without edit_product from updating', function () {
    $user = User::factory()->create(['role' => Role::User]);

    actingAs($user)
        ->patch("/admin/products/{$this->product->id}", ['name' => 'New Name'])
        ->assertForbidden();
});

it('updates a product and redirects', function () {
    actingAs($this->admin)
        ->patch("/admin/products/{$this->product->id}", [
            'name' => 'Updated Widget',
            'sku' => $this->product->sku,
            'description' => 'Updated description.',
            'category_id' => null,
            'type' => 'physical',
            'price' => 4999,
            'price_type' => 'one_time',
            'billing_interval' => null,
            'billing_interval_count' => null,
            'trial_period_days' => null,
            'track_inventory' => false,
            'stock_quantity' => null,
            'is_active' => true,
            'remove_image' => false,
        ])
        ->assertRedirect("/admin/products/{$this->product->id}");

    expect($this->product->fresh()->name)->toBe('Updated Widget');
    expect($this->product->fresh()->price)->toBe(4999);
});

it('enforces unique SKU ignoring the current product', function () {
    actingAs($this->admin)
        ->patch("/admin/products/{$this->product->id}", [
            'name' => $this->product->name,
            'sku' => $this->product->sku,
            'description' => $this->product->description,
            'category_id' => null,
            'type' => 'physical',
            'price' => $this->product->price,
            'price_type' => 'one_time',
            'is_active' => true,
            'remove_image' => false,
        ])
        ->assertRedirect("/admin/products/{$this->product->id}");
});

it('rejects a duplicate SKU from another product', function () {
    Product::factory()->create(['sku' => 'OTHER-001']);

    actingAs($this->admin)
        ->patch("/admin/products/{$this->product->id}", [
            'name' => $this->product->name,
            'sku' => 'OTHER-001',
            'description' => $this->product->description,
            'category_id' => null,
            'type' => 'physical',
            'price' => $this->product->price,
            'price_type' => 'one_time',
            'is_active' => true,
            'remove_image' => false,
        ])
        ->assertSessionHasErrors('sku');
});

it('resets sort_order when category changes', function () {
    $catA = Category::factory()->create();
    $catB = Category::factory()->create();

    Product::factory()->create(['category_id' => $catB->id, 'sort_order' => 1]);
    Product::factory()->create(['category_id' => $catB->id, 'sort_order' => 2]);

    $product = Product::factory()->create(['category_id' => $catA->id, 'sort_order' => 1]);

    actingAs($this->admin)
        ->patch("/admin/products/{$product->id}", [
            'name' => $product->name,
            'sku' => $product->sku,
            'description' => $product->description,
            'category_id' => $catB->id,
            'type' => 'physical',
            'price' => $product->price,
            'price_type' => 'one_time',
            'is_active' => true,
            'remove_image' => false,
        ]);

    expect($product->fresh()->category_id)->toBe($catB->id);
    expect($product->fresh()->sort_order)->toBe(3);
});

it('preserves sort_order when category is unchanged', function () {
    $cat = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $cat->id, 'sort_order' => 2]);

    actingAs($this->admin)
        ->patch("/admin/products/{$product->id}", [
            'name' => 'New Name',
            'sku' => $product->sku,
            'description' => $product->description,
            'category_id' => $cat->id,
            'type' => 'physical',
            'price' => $product->price,
            'price_type' => 'one_time',
            'is_active' => true,
            'remove_image' => false,
        ]);

    expect($product->fresh()->sort_order)->toBe(2);
});

it('replaces image on upload', function () {
    $oldPath = UploadedFile::fake()->image('old.jpg')->store('products', 'public');
    $this->product->update(['image' => $oldPath]);

    actingAs($this->admin)
        ->patch("/admin/products/{$this->product->id}", [
            'name' => $this->product->name,
            'sku' => $this->product->sku,
            'description' => $this->product->description,
            'category_id' => null,
            'type' => 'physical',
            'price' => $this->product->price,
            'price_type' => 'one_time',
            'is_active' => true,
            'image' => UploadedFile::fake()->image('new.jpg'),
            'remove_image' => false,
        ]);

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($this->product->fresh()->image);
});

it('removes image when remove_image is true', function () {
    $path = UploadedFile::fake()->image('product.jpg')->store('products', 'public');
    $this->product->update(['image' => $path]);

    actingAs($this->admin)
        ->patch("/admin/products/{$this->product->id}", [
            'name' => $this->product->name,
            'sku' => $this->product->sku,
            'description' => $this->product->description,
            'category_id' => null,
            'type' => 'physical',
            'price' => $this->product->price,
            'price_type' => 'one_time',
            'is_active' => true,
            'remove_image' => true,
        ]);

    Storage::disk('public')->assertMissing($path);
    expect($this->product->fresh()->image)->toBeNull();
});
