<?php

namespace App\Http\Controllers;

use App\Enum\OrderStatus;
use App\Enum\Tier;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return Inertia::render('admin/dashboard', [
                'revenueAllTime' => Inertia::defer(fn () => (int) Order::where('status', OrderStatus::Paid)->sum('total')),
                'revenueMtd' => Inertia::defer(fn () => (int) Order::where('status', OrderStatus::Paid)
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('total')
                ),
                'activeSubscriptions' => Inertia::defer(fn () => Subscription::whereIn('status', ['active', 'trialing'])->count()
                ),
                'newClientsThisMonth' => Inertia::defer(fn () => User::where('tier', Tier::User)
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count()
                ),
                'recentOrders' => Inertia::defer(fn () => Order::with(['user:id,first_name,last_name,email'])
                    ->withCount('items')
                    ->latest()
                    ->take(5)
                    ->get()
                ),
            ]);
        }

        return Inertia::render('dashboard', [
            'orderCount' => Inertia::defer(fn () => Order::forUser($user)->count()),
            'activeSubscriptionCount' => Inertia::defer(fn () => Subscription::forUser($user)
                ->whereIn('status', ['active', 'trialing'])
                ->count()
            ),
            'recentOrders' => Inertia::defer(fn () => Order::forUser($user)->withCount('items')->latest()->take(5)->get()
            ),
        ]);
    }
}
