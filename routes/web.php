<?php

use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\BillingPortalController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Public — Stripe signature verifies authenticity
Route::post('webhooks/stripe', [WebhookController::class, 'handle'])->name('webhooks.stripe');

// Public invitation accept — token validates the request, no auth required
Route::get('invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('shop', [ShopController::class, 'index'])->name('shop');
    Route::get('shop/{product}', [ShopController::class, 'show'])->name('shop.show');

    // Checkout — literal /checkout/success and /cancel declared before any future /checkout/{id}
    Route::get('checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('checkout/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');
    Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    // Orders — literal /orders declared before parametric /orders/{order}
    Route::get('orders', [OrderController::class, 'index'])->name('orders');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');

    // Subscriptions
    Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions');
    Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');

    // Billing portal
    Route::post('billing/portal', [BillingPortalController::class, 'redirect'])->name('billing.portal');

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
            Route::post('users/bulk-assign-roles', [UserController::class, 'bulkAssignRoles'])->name('users.bulk-assign-roles');
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

        // Roles — literal /create before parametric /{role}
        Route::middleware('can:admin')->group(function () {
            Route::get('roles', [RoleController::class, 'index'])->name('roles');
            Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
            Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
            Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
            Route::patch('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
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

        Route::middleware('can:admin')->group(function () {
            Route::get('orders', [AdminOrderController::class, 'index'])->name('orders');
            Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        });

        // Invitations — admin only
        Route::middleware('can:admin')->group(function () {
            Route::get('invitations', [InvitationController::class, 'index'])->name('invitations');
            Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
            Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
            Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
        });
    });
});

require __DIR__.'/settings.php';
