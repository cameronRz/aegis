<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPermissionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

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
    });
});

require __DIR__.'/settings.php';
