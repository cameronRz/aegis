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
use function Pest\Laravel\delete;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('public');

    $this->mock(StripeService::class, function (MockInterface $mock) {
        $mock->allows('createProduct')->andReturn(StripeProduct::constructFrom(['id' => 'prod_test123']));
        $mock->allows('createPrice')->andReturn(Price::constructFrom(['id' => 'price_test123']));
        $mock->allows('archiveProduct');
    });

    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->viewPermission = Permission::create([
        'name' => 'view_products',
        'display_name' => 'View Products',
        'description' => 'Access the products list.',
    ]);
    $this->product = Product::factory()->create();
});

// --- show ---

it('redirects guests from the show page', function () {
    get("/admin/products/{$this->product->id}")->assertRedirect('/login');
});

it('forbids users without view_products from the show page', function () {
    $user = User::factory()->create(['role' => Role::User]);

    actingAs($user)->get("/admin/products/{$this->product->id}")->assertForbidden();
});

it('renders the show page for an admin', function () {
    actingAs($this->admin)
        ->get("/admin/products/{$this->product->id}")
        ->assertInertia(fn ($page) => $page
            ->component('products/show')
            ->has('product')
            ->has('imageUrl')
            ->where('canEdit', true)
            ->where('canDelete', true),
        );
});

it('renders the show page for a user with view_products permission', function () {
    $user = User::factory()->create(['role' => Role::User]);
    $set = PermissionSet::create(['name' => 'Staff']);
    $set->permissions()->sync([$this->viewPermission->id]);
    $user->userPermissionSet()->create(['permission_set_id' => $set->id, 'assigned_by' => $this->admin->id]);

    actingAs($user)
        ->get("/admin/products/{$this->product->id}")
        ->assertInertia(fn ($page) => $page
            ->component('products/show')
            ->where('canEdit', false)
            ->where('canDelete', false),
        );
});

it('includes the category relationship', function () {
    $category = Category::factory()->create(['name' => 'Electronics']);
    $product = Product::factory()->withCategory($category)->create();

    actingAs($this->admin)
        ->get("/admin/products/{$product->id}")
        ->assertInertia(fn ($page) => $page
            ->where('product.category.name', 'Electronics'),
        );
});

it('passes a null imageUrl when product has no image', function () {
    actingAs($this->admin)
        ->get("/admin/products/{$this->product->id}")
        ->assertInertia(fn ($page) => $page->where('imageUrl', null));
});

it('passes a non-null imageUrl when product has an image', function () {
    $path = UploadedFile::fake()->image('product.jpg')->store('products', 'public');
    $this->product->update(['image' => $path]);

    actingAs($this->admin)
        ->get("/admin/products/{$this->product->id}")
        ->assertInertia(fn ($page) => $page->whereNot('imageUrl', null));
});

// --- destroy ---

it('redirects guests from destroy', function () {
    delete("/admin/products/{$this->product->id}")->assertRedirect('/login');
});

it('forbids users without delete_product from destroying', function () {
    $user = User::factory()->create(['role' => Role::User]);

    actingAs($user)->delete("/admin/products/{$this->product->id}")->assertForbidden();
});

it('soft-deletes the product and redirects', function () {
    actingAs($this->admin)
        ->delete("/admin/products/{$this->product->id}")
        ->assertRedirect('/admin/products');

    expect(Product::find($this->product->id))->toBeNull();
    expect(Product::withTrashed()->find($this->product->id))->not->toBeNull();
});
