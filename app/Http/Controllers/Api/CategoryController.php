<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::with('media')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($c) => $this->transform($c));

        return response()->json($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'slug'        => 'required|string|unique:categories,slug',
            'parent_id'   => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'sort_order'  => 'integer',
            'is_active'   => 'boolean',
        ]);

        $category = Category::create($data);

        if ($request->hasFile('banner')) {
            $category->addMedia($request->file('banner'))->toMediaCollection('banner');
        }

        \Illuminate\Support\Facades\Cache::forget('storefront_data');

        return response()->json($this->transform($category), 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json($this->transform($category->load(['children', 'products']), true));
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        \Log::info('Category Update Request:', ['data' => $request->all(), 'files' => $request->allFiles()]);
        $data = $request->validate([
            'name'        => 'string|max:120',
            'slug'        => "string|unique:categories,slug,{$category->id}",
            'parent_id'   => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'sort_order'  => 'integer',
            'is_active'   => 'boolean',
        ]);

        $category->update($data);

        if ($request->hasFile('banner')) {
            $category->clearMediaCollection('banner');
            $category->addMedia($request->file('banner'))->toMediaCollection('banner');
        }

        \Illuminate\Support\Facades\Cache::forget('storefront_data');

        return response()->json($this->transform($category->fresh()));
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->count() > 0) {
            return response()->json(['message' => 'Cannot delete category with products.'], 422);
        }
        $category->delete();
        \Illuminate\Support\Facades\Cache::forget('storefront_data');
        return response()->json(['message' => 'Category deleted.']);
    }

    public function toggleStatus(Category $category): JsonResponse
    {
        $category->update(['is_active' => !$category->is_active]);
        \Illuminate\Support\Facades\Cache::forget('storefront_data');
        return response()->json(['is_active' => $category->fresh()->is_active]);
    }

    private function transform(Category $c, bool $withChildren = false): array
    {
        $data = [
            'id'          => $c->id,
            'parent_id'   => $c->parent_id,
            'name'        => $c->name,
            'slug'        => $c->slug,
            'description' => $c->description,
            'is_active'   => $c->is_active,
            'sort_order'  => $c->sort_order,
            'products'    => $c->products()->count(),
            'banner_url'  => $c->getFirstMediaUrl('banner'),
        ];

        if ($withChildren && $c->relationLoaded('children')) {
            $data['children'] = $c->children->map(fn ($child) => $this->transform($child));
        }

        return $data;
    }
}
