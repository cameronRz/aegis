<?php

use App\Enum\OrderStatus;
use App\Models\Order;

it('expires pending orders older than 25 hours', function () {
    $stale = Order::factory()->create([
        'status' => OrderStatus::Pending,
        'created_at' => now()->subHours(26),
    ]);

    $this->artisan('orders:expire-stale')->assertSuccessful();

    expect($stale->fresh()->status)->toBe(OrderStatus::Expired);
});

it('does not expire pending orders younger than 25 hours', function () {
    $recent = Order::factory()->create([
        'status' => OrderStatus::Pending,
        'created_at' => now()->subHours(24),
    ]);

    $this->artisan('orders:expire-stale')->assertSuccessful();

    expect($recent->fresh()->status)->toBe(OrderStatus::Pending);
});

it('does not affect already paid orders', function () {
    $paid = Order::factory()->paid()->create([
        'created_at' => now()->subHours(30),
    ]);

    $this->artisan('orders:expire-stale')->assertSuccessful();

    expect($paid->fresh()->status)->toBe(OrderStatus::Paid);
});

it('reports the number of expired orders', function () {
    Order::factory()->count(3)->create([
        'status' => OrderStatus::Pending,
        'created_at' => now()->subHours(26),
    ]);

    $this->artisan('orders:expire-stale')
        ->expectsOutputToContain('Expired 3 stale order(s).')
        ->assertSuccessful();
});
