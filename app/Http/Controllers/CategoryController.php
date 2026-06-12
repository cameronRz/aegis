<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = Category::query()
            ->with('parent:id,name')
            ->when(
                $request->input('search'),
                fn ($query, string $search) => $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                        ->orWhere('slug', 'ilike', "%{$search}%");
                })
            )
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('categories/index', [
            'categories' => $categories,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('categories/create', [
            'parentCategories' => Category::query()->ordered()->get(['id', 'name']),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        return redirect()->route('admin.categories');
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('categories/edit', [
            'category' => $category,
            'parentCategories' => Category::query()
                ->where('id', '!=', $category->id)
                ->ordered()
                ->get(['id', 'name']),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('admin.categories');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('admin.categories');
    }
}
