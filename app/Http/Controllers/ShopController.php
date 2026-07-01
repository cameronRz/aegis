<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::query()
            ->active()
            ->with('category:id,name')
            ->search($request->input('search'))
            ->when(
                $request->input('category'),
                fn ($query, string $slug) => $query->whereHas('category', fn ($q) => $q->where('slug', $slug))
            )
            ->orderBy('category_id')
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        $cartItems = $request->user()
            ? CartItem::whereHas('cart', fn ($q) => $q->where('user_id', $request->user()->id))
                ->whereIn('product_id', $products->pluck('id'))
                ->pluck('id', 'product_id')
                ->all()
            : [];

        return Inertia::render('shop/index', [
            'products' => $products,
            'categories' => Category::query()->active()->ordered()->get(['id', 'name', 'slug']),
            'filters' => $request->only('search', 'category'),
            'cartItems' => $cartItems,
        ]);
    }

    public function show(Request $request, Product $product): Response
    {
        // Route model binding excludes soft-deleted products; this guards against
        // any future withTrashed() on the binding.
        abort_if($product->trashed(), 404);
        abort_unless($product->is_active, 404);

        $cartItemId = $request->user()
            ? CartItem::whereHas('cart', fn ($q) => $q->where('user_id', $request->user()->id))
                ->where('product_id', $product->id)
                ->value('id')
            : null;

        return Inertia::render('shop/show', [
            'product' => $product->load('category:id,name'),
            'imageUrl' => $product->image ? Storage::url($product->image) : null,
            'cartItemId' => $cartItemId,
        ]);
    }
}
