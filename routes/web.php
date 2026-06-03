<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPermissionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('can:view_users')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users');
            Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        });

        Route::post('users/{user}/permissions/{permission}/toggle', [UserPermissionController::class, 'toggle'])
            ->middleware('can:admin')
            ->name('users.permissions.toggle');
    });
});

require __DIR__.'/settings.php';
