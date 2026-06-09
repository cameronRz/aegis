<?php

use App\Enum\Role;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->withoutVite();

    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->viewPermission = Permission::create([
        'name' => 'view_products',
        'display_name' => 'View Products',
        'description' => 'Access the products list.',
    ]);
});

it('redirects guests to login', function () {
    get('/admin/products')->assertRedirect('/login');
});

it('forbids users without the view_products permission', function () {
    $user = User::factory()->create(['role' => Role::User]);

    actingAs($user)->get('/admin/products')->assertForbidden();
});

it('allows an admin to view products', function () {
    Product::factory(3)->create();

    actingAs($this->admin)
        ->get('/admin/products')
        ->assertInertia(
            fn ($page) => $page
                ->component('products/index')
                ->has('products.data', 3),
        );
});

it('allows a user with view_products permission to view products', function () {
    $user = User::factory()->create(['role' => Role::User]);
    $user->permissions()->attach($this->viewPermission->id, ['granted_by' => $this->admin->id]);

    Product::factory(2)->create();

    actingAs($user)
        ->get('/admin/products')
        ->assertInertia(
            fn ($page) => $page
                ->component('products/index')
                ->has('products.data', 2),
        );
});

it('searches products by name', function () {
    Product::factory()->create(['name' => 'Wireless Mouse']);
    Product::factory()->create(['name' => 'Mechanical Keyboard']);

    actingAs($this->admin)
        ->get('/admin/products?search=wireless')
        ->assertInertia(
            fn ($page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Wireless Mouse'),
        );
});

it('searches products by SKU', function () {
    Product::factory()->create(['sku' => 'AB-1234']);
    Product::factory()->create(['sku' => 'XY-9999']);

    actingAs($this->admin)
        ->get('/admin/products?search=AB-1234')
        ->assertInertia(
            fn ($page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.sku', 'AB-1234'),
        );
});

it('returns products with their category', function () {
    $category = Category::factory()->create(['name' => 'Electronics']);
    Product::factory()->withCategory($category)->create(['name' => 'Webcam']);

    actingAs($this->admin)
        ->get('/admin/products')
        ->assertInertia(
            fn ($page) => $page
                ->where('products.data.0.category.name', 'Electronics'),
        );
});

it('returns products without a category', function () {
    Product::factory()->create(['category_id' => null]);

    actingAs($this->admin)
        ->get('/admin/products')
        ->assertInertia(
            fn ($page) => $page
                ->where('products.data.0.category', null),
        );
});
