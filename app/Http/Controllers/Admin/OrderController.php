<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $orders = Order::with('user:id,first_name,last_name,email')
            ->withCount('items')
            ->when($search, function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('order_number', 'ilike', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u
                            ->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%")
                        );
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/orders/index', [
            'orders' => $orders,
            'filters' => $request->only('search'),
        ]);
    }

    public function show(Order $order): Response
    {
        return Inertia::render('admin/orders/show', [
            'order' => $order->load('items', 'user'),
        ]);
    }
}
