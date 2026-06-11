<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPermissionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('shop', [ShopController::class, 'index'])->name('shop');
    Route::get('shop/{product}', [ShopController::class, 'show'])->name('shop.show');

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

        Route::middleware('can:view_users')->group(function () {
            Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        });

        Route::post('users/{user}/permissions/{permission}/toggle', [UserPermissionController::class, 'toggle'])
            ->middleware('can:admin')
            ->name('users.permissions.toggle');

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
