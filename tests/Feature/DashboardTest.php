<?php

use App\Enum\OrderStatus;
use App\Enum\Tier;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('admin sees admin dashboard component', function () {
    $admin = User::factory()->create();
    $admin->tier = Tier::Admin;
    $admin->save();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->component('admin/dashboard'));
});

test('client sees client dashboard component', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->component('dashboard'));
});

// --- Phase 4.1: Admin revenue aggregation ---

test('revenueAllTime includes only paid orders', function () {
    $admin = User::factory()->create();
    $admin->tier = Tier::Admin;
    $admin->save();

    Order::factory()->paid()->create(['total' => 5000]);
    Order::factory()->paid()->create(['total' => 3000]);
    Order::factory()->create(['status' => OrderStatus::Pending, 'total' => 1000]);
    Order::factory()->create(['status' => OrderStatus::Failed, 'total' => 2000]);
    Order::factory()->expired()->create(['total' => 4000]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->missing('revenueAllTime')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('revenueAllTime', 8000)
            )
        );
});

test('revenueMtd excludes paid orders from prior months', function () {
    $admin = User::factory()->create();
    $admin->tier = Tier::Admin;
    $admin->save();

    // This month
    Order::factory()->paid()->create(['total' => 5000]);

    // Last month — should not be included in MTD
    Order::factory()->paid()->create([
        'total' => 3000,
        'created_at' => now()->subMonth(),
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->missing('revenueMtd')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('revenueMtd', 5000)
            )
        );
});

test('activeSubscriptions counts only active and trialing statuses', function () {
    $admin = User::factory()->create();
    $admin->tier = Tier::Admin;
    $admin->save();

    Subscription::factory()->create(['status' => 'active']);
    Subscription::factory()->trialing()->create();
    Subscription::factory()->canceled()->create();
    Subscription::factory()->create(['status' => 'past_due']);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->missing('activeSubscriptions')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('activeSubscriptions', 2)
            )
        );
});

test('newClientsThisMonth counts only tier user accounts created this month', function () {
    $admin = User::factory()->create();
    $admin->tier = Tier::Admin;
    $admin->save();

    // New clients this month
    User::factory()->count(3)->create(['created_at' => now()]);

    // Client from last month — excluded
    User::factory()->create(['created_at' => now()->subMonth()]);

    // Admin — excluded
    $otherAdmin = User::factory()->create(['created_at' => now()]);
    $otherAdmin->tier = Tier::Admin;
    $otherAdmin->save();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->missing('newClientsThisMonth')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('newClientsThisMonth', 3)
            )
        );
});

// --- Phase 4.2: Client data scoping ---

test('client orderCount is scoped to the authenticated user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Order::factory()->count(3)->create(['user_id' => $userA->id]);
    Order::factory()->count(5)->create(['user_id' => $userB->id]);

    $this->actingAs($userA)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->missing('orderCount')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('orderCount', 3)
            )
        );
});

test('client activeSubscriptionCount is scoped to the authenticated user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Subscription::factory()->count(2)->create(['user_id' => $userA->id, 'status' => 'active']);
    Subscription::factory()->count(4)->create(['user_id' => $userB->id, 'status' => 'active']);

    $this->actingAs($userA)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->missing('activeSubscriptionCount')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('activeSubscriptionCount', 2)
            )
        );
});
