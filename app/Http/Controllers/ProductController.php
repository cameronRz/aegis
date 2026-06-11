<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::query()
            ->with('category:id,name')
            ->search($request->input('search'))
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('products/index', [
            'products' => $products,
            'filters' => $request->only('search'),
        ]);
    }

    public function trash(Request $request): Response
    {
        $products = Product::onlyTrashed()
            ->with('category:id,name')
            ->search($request->input('search'))
            ->latest('deleted_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('products/trash', [
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

    public function show(Product $product): Response
    {
        return Inertia::render('products/show', [
            'product' => $product->load('category:id,name'),
            'imageUrl' => $product->image ? Storage::url($product->image) : null,
            'canEdit' => Gate::allows('edit_product'),
            'canDelete' => Gate::allows('delete_product'),
        ]);
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('products/edit', [
            'product' => $product,
            'categories' => Category::query()->ordered()->get(['id', 'name']),
            'imageUrl' => $product->image ? Storage::url($product->image) : null,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'remove_image']);

        // Reset sort_order when moving to a different category
        if ($data['category_id'] !== $product->category_id) {
            $data['sort_order'] = (int) Product::where('category_id', $data['category_id'])->max('sort_order') + 1;
        }

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        } elseif ($request->boolean('remove_image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = null;
        }

        $product->update($data);

        return redirect()->route('admin.products.show', $product);
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products');
    }

    public function restore(Product $product): RedirectResponse
    {
        $product->restore();

        return redirect()->route('admin.products.trash');
    }

    public function forceDestroy(Product $product): RedirectResponse
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->forceDelete();

        return redirect()->route('admin.products.trash');
    }
}
