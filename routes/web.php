<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PermissionSetController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('shop', [ShopController::class, 'index'])->name('shop');
    Route::get('shop/{product}', [ShopController::class, 'show'])->name('shop.show');

    // Checkout — literal /checkout/success and /cancel declared before any future /checkout/{id}
    Route::get('checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('checkout/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');
    Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    // Cart — literal /cart/items before parametric /cart/{...}
    Route::get('cart', [CartController::class, 'show'])->name('cart');
    Route::post('cart/items', [CartController::class, 'store'])->name('cart.items.store');
    Route::patch('cart/items/{cartItem}', [CartController::class, 'update'])->name('cart.items.update');
    Route::delete('cart/items/{cartItem}', [CartController::class, 'destroy'])->name('cart.items.destroy');
    Route::delete('cart', [CartController::class, 'clear'])->name('cart.clear');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('can:view_users')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users');
        });

        Route::middleware('can:create_user')->group(function () {
            Route::get('users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
        });

        Route::middleware('can:edit_user')->group(function () {
            Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        });

        Route::middleware('can:delete_user')->group(function () {
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        });

        // Literal segments declared before parametric {user} routes
        Route::middleware('can:admin')->group(function () {
            Route::get('users/trash', [UserController::class, 'trash'])->name('users.trash');
            Route::delete('users/{user}/force', [UserController::class, 'forceDestroy'])
                ->withTrashed()
                ->name('users.force-destroy');
        });

        Route::middleware('can:delete_user')->group(function () {
            Route::post('users/{user}/restore', [UserController::class, 'restore'])
                ->withTrashed()
                ->name('users.restore');
        });

        Route::middleware('can:view_users')->group(function () {
            Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        });

        // Permission sets — literal /create before parametric /{permissionSet}
        Route::middleware('can:admin')->group(function () {
            Route::get('permission-sets', [PermissionSetController::class, 'index'])->name('permission-sets');
            Route::get('permission-sets/create', [PermissionSetController::class, 'create'])->name('permission-sets.create');
            Route::post('permission-sets', [PermissionSetController::class, 'store'])->name('permission-sets.store');
            Route::get('permission-sets/{permissionSet}/edit', [PermissionSetController::class, 'edit'])->name('permission-sets.edit');
            Route::patch('permission-sets/{permissionSet}', [PermissionSetController::class, 'update'])->name('permission-sets.update');
            Route::delete('permission-sets/{permissionSet}', [PermissionSetController::class, 'destroy'])->name('permission-sets.destroy');
        });

        Route::middleware('can:view_categories')->group(function () {
            Route::get('categories', [CategoryController::class, 'index'])->name('categories');
        });

        Route::middleware('can:create_category')->group(function () {
            Route::get('categories/create', [CategoryController::class, 'create'])->name('categories.create');
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        });

        Route::middleware('can:edit_category')->group(function () {
            Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
            Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        });

        Route::middleware('can:delete_category')->group(function () {
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
        });

        Route::middleware('can:view_products')->group(function () {
            Route::get('products', [ProductController::class, 'index'])->name('products');
        });

        Route::middleware('can:create_product')->group(function () {
            Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
            Route::post('products', [ProductController::class, 'store'])->name('products.store');
        });

        // Literal segments declared before parametric {product} routes
        Route::middleware('can:admin')->group(function () {
            Route::get('products/trash', [ProductController::class, 'trash'])->name('products.trash');
            Route::delete('products/{product}/force', [ProductController::class, 'forceDestroy'])
                ->withTrashed()
                ->name('products.force-destroy');
        });

        Route::middleware('can:delete_product')->group(function () {
            Route::post('products/{product}/restore', [ProductController::class, 'restore'])
                ->withTrashed()
                ->name('products.restore');
        });

        Route::middleware('can:edit_product')->group(function () {
            Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
            Route::patch('products/{product}', [ProductController::class, 'update'])->name('products.update');
        });

        Route::middleware('can:delete_product')->group(function () {
            Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        });

        Route::middleware('can:view_products')->group(function () {
            Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
        });
    });
});

require __DIR__.'/settings.php';
