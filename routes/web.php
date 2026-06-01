<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::middleware(['can:admin'])->prefix('admin')->group(function () {
        Route::inertia('users', 'admin/users/index')->name('admin.users');
    });
});

require __DIR__.'/settings.php';
