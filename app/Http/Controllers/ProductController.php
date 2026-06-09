<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::query()
            ->with('category:id,name')
            ->when(
                $request->input('search'),
                fn ($query, string $search) => $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                })
            )
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('products/index', [
            'products' => $products,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('products/create', [
            'categories' => Category::query()->ordered()->get(['id', 'name']),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('products', 'public')
            : null;

        Product::create([
            ...$request->safe()->except('image'),
            'image' => $imagePath,
        ]);

        return redirect()->route('admin.products');
    }
}
