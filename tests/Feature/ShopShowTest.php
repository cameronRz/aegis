<?php

use App\Models\Product;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
});

it('redirects guests to login', function () {
    $product = Product::factory()->create();

    get(route('shop.show', $product))->assertRedirect('/login');
});

it('renders the product detail page', function () {
    $product = Product::factory()->create(['name' => 'Test Widget']);

    actingAs($this->user)
        ->get(route('shop.show', $product))
        ->assertInertia(fn ($page) => $page
            ->component('shop/show')
            ->where('product.name', 'Test Widget')
        );
});

it('returns 404 for inactive products', function () {
    $product = Product::factory()->inactive()->create();

    actingAs($this->user)
        ->get(route('shop.show', $product))
        ->assertNotFound();
});

it('returns 404 for soft-deleted products', function () {
    $product = Product::factory()->create();
    $product->delete();

    actingAs($this->user)
        ->get(route('shop.show', $product))
        ->assertNotFound();
});

it('passes imageUrl to the page', function () {
    $product = Product::factory()->create(['image' => 'products/test.jpg']);

    actingAs($this->user)
        ->get(route('shop.show', $product))
        ->assertInertia(fn ($page) => $page
            ->where('imageUrl', '/storage/products/test.jpg')
        );
});

it('passes null imageUrl when product has no image', function () {
    $product = Product::factory()->create(['image' => null]);

    actingAs($this->user)
        ->get(route('shop.show', $product))
        ->assertInertia(fn ($page) => $page->where('imageUrl', null));
});

it('loads the product category', function () {
    $product = Product::factory()->create();

    actingAs($this->user)
        ->get(route('shop.show', $product))
        ->assertInertia(fn ($page) => $page->has('product.category'));
});
