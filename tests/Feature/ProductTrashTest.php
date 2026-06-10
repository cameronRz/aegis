<?php

use App\Enum\Role;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('public');

    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->user = User::factory()->create(['role' => Role::User]);
    $this->product = Product::factory()->create();
    $this->product->delete();
});

// --- trash page ---

it('redirects guests from the trash page', function () {
    get('/admin/products/trash')->assertRedirect('/login');
});

it('forbids non-admins from the trash page', function () {
    actingAs($this->user)->get('/admin/products/trash')->assertForbidden();
});

it('renders the trash page for an admin', function () {
    actingAs($this->admin)
        ->get('/admin/products/trash')
        ->assertInertia(fn ($page) => $page
            ->component('products/trash')
            ->has('products.data', 1),
        );
});

it('does not show active products on the trash page', function () {
    Product::factory()->create(); // active

    actingAs($this->admin)
        ->get('/admin/products/trash')
        ->assertInertia(fn ($page) => $page->has('products.data', 1)); // only the trashed one
});

it('searches trashed products by name', function () {
    $other = Product::factory()->create(['name' => 'Different Product']);
    $other->delete();

    actingAs($this->admin)
        ->get('/admin/products/trash?search='.urlencode($this->product->name))
        ->assertInertia(fn ($page) => $page->has('products.data', 1));
});

// --- restore ---

it('redirects guests from restore', function () {
    post("/admin/products/{$this->product->id}/restore")->assertRedirect('/login');
});

it('forbids users without delete_product from restoring', function () {
    actingAs($this->user)
        ->post("/admin/products/{$this->product->id}/restore")
        ->assertForbidden();
});

it('restores a soft-deleted product', function () {
    actingAs($this->admin)
        ->post("/admin/products/{$this->product->id}/restore")
        ->assertRedirect('/admin/products/trash');

    expect(Product::find($this->product->id))->not->toBeNull();
});

// --- force delete ---

it('redirects guests from force delete', function () {
    delete("/admin/products/{$this->product->id}/force")->assertRedirect('/login');
});

it('forbids non-admins from force deleting', function () {
    actingAs($this->user)
        ->delete("/admin/products/{$this->product->id}/force")
        ->assertForbidden();
});

it('permanently deletes a soft-deleted product', function () {
    actingAs($this->admin)
        ->delete("/admin/products/{$this->product->id}/force")
        ->assertRedirect('/admin/products/trash');

    expect(Product::withTrashed()->find($this->product->id))->toBeNull();
});

it('deletes the image from storage on force delete', function () {
    $path = UploadedFile::fake()->image('product.jpg')->store('products', 'public');
    $this->product->update(['image' => $path]);

    actingAs($this->admin)->delete("/admin/products/{$this->product->id}/force");

    Storage::disk('public')->assertMissing($path);
});
