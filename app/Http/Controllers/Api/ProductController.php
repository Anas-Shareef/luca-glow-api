<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductController extends Controller
{
    /**
     * GET /api/v1/admin/products
     * Server-side: pagination, search, sort, filter by category/status
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'media'])
            ->withCount('orderItems')
            ->when($request->search, fn ($q) => $q->search($request->search))
            ->when($request->category_id, fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->status !== null, function ($q) use ($request) {
                $q->where('is_active', filter_var($request->status, FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->low_stock, fn ($q) => $q->lowStock())
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_dir ?? 'desc');

        $products = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => $products->map(fn ($p) => $this->transform($p)),
            'meta' => [
                'total'        => $products->total(),
                'per_page'     => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/products
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateProduct($request);

        $product = DB::transaction(function () use ($validated) {
            $product = Product::create($validated);

            if (isset($validated['attribute_values'])) {
                foreach ($validated['attribute_values'] as $attrId => $value) {
                    $product->attributeValues()->create([
                        'attribute_id' => $attrId,
                        'value'        => $value,
                    ]);
                }
            }

            return $product;
        });

        \Illuminate\Support\Facades\Cache::forget('storefront_data');

        return response()->json($this->transform($product->load(['category', 'media'])), 201);
    }

    /**
     * GET /api/v1/admin/products/{product}
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json($this->transform(
            $product->load(['category', 'media', 'attributeValues.attribute'])
        ));
    }

    /**
     * PUT /api/v1/admin/products/{product}
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $this->validateProduct($request, $product->id);

        DB::transaction(function () use ($product, $validated) {
            $product->update($validated);

            if (isset($validated['attribute_values'])) {
                foreach ($validated['attribute_values'] as $attrId => $value) {
                    $product->attributeValues()->updateOrCreate(
                        ['attribute_id' => $attrId],
                        ['value' => $value]
                    );
                }
            }
        });

        \Illuminate\Support\Facades\Cache::forget('storefront_data');

        return response()->json($this->transform($product->fresh()->load(['category', 'media'])));
    }

    /**
     * DELETE /api/v1/admin/products/{product}  (soft delete)
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();
        \Illuminate\Support\Facades\Cache::forget('storefront_data');
        return response()->json(['message' => 'Product deleted.']);
    }

    /**
     * PATCH /api/v1/admin/products/{product}/toggle
     */
    public function toggleStatus(Product $product): JsonResponse
    {
        $product->update(['is_active' => !$product->is_active]);
        \Illuminate\Support\Facades\Cache::forget('storefront_data');
        return response()->json(['is_active' => $product->fresh()->is_active]);
    }

    /**
     * POST /api/v1/admin/products/{product}/media
     * Accepts multiple image uploads; Spatie handles WebP conversion
     */
    public function uploadMedia(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'images'     => 'required|array|max:10',
            'images.*'   => 'image|mimes:jpeg,png,webp,gif|max:2048', // Reduced to 2MB to avoid 413 errors
            'cover_idx'  => 'nullable|integer',
        ]);

        $added = [];
        foreach ($request->file('images') as $idx => $file) {
            $media = $product->addMedia($file)
                ->usingFileName(Str::uuid() . '.webp')
                ->toMediaCollection('gallery');
            
            // If this index is marked as cover, move it to the front
            if ($request->has('cover_idx') && (int)$request->cover_idx === $idx) {
                $media->moveOrderTo(1);
            }

            $added[] = $this->mediaTransform($media);
        }

        \Illuminate\Support\Facades\Cache::forget('storefront_data');

        return response()->json(['media' => $added], 201);
    }

    /**
     * DELETE /api/v1/admin/products/{product}/media/{media}
     */
    public function deleteMedia(Product $product, Media $media): JsonResponse
    {
        abort_if($media->model_id !== $product->id, 404);
        $media->delete();
        \Illuminate\Support\Facades\Cache::forget('storefront_data');
        return response()->json(['message' => 'Image deleted.']);
    }

    /**
     * POST /api/v1/admin/products/{product}/media/reorder
     * Body: { "order": [3, 1, 2] }  — array of media IDs in desired order
     */
    public function reorderMedia(Request $request, Product $product): JsonResponse
    {
        $request->validate(['order' => 'required|array']);

        Media::setNewOrder($request->order);
        \Illuminate\Support\Facades\Cache::forget('storefront_data');

        return response()->json(['message' => 'Media reordered.']);
    }

    // ── Private helpers ───────────────────────────────────────
    private function validateProduct(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'                    => 'required|string|max:200',
            'sku'                     => 'required|string|max:80|unique:products,sku' . ($ignoreId ? ",{$ignoreId}" : ''),
            'slug'                    => 'required|string|max:220|unique:products,slug' . ($ignoreId ? ",{$ignoreId}" : ''),
            'category_id'             => 'nullable|exists:categories,id',
            'type'                    => 'in:simple,configurable',
            'description'             => 'nullable|string',
            'ingredients'             => 'nullable|string',
            'how_to_use'              => 'nullable|string',
            'skin_type'               => 'nullable|string|max:50',
            'gender'                  => 'nullable|in:men,women,unisex',
            'volume'                  => 'nullable|string|max:30',
            'price_inr'               => 'required|integer|min:1',
            'special_price'           => 'nullable|integer|lt:price_inr',
            'special_price_starts_at' => 'nullable|date',
            'special_price_ends_at'   => 'nullable|date|after_or_equal:special_price_starts_at',
            'stock_quantity'          => 'required|integer|min:0',
            'low_stock_threshold'     => 'integer|min:0',
            'meta_title'              => 'nullable|string|max:120',
            'meta_description'        => 'nullable|string|max:300',
            'shipping_returns'        => 'nullable|string',
            'is_active'               => 'boolean',
            'attribute_values'        => 'nullable|array',
        ]);
    }

    private function transform(Product $p): array
    {
        return [
            'id'               => $p->id,
            'sku'              => $p->sku,
            'name'             => $p->name,
            'slug'             => $p->slug,
            'type'             => $p->type,
            'category'         => $p->category?->only(['id', 'name', 'slug']),
            'description'      => $p->description,
            'ingredients'      => $p->ingredients,
            'how_to_use'       => $p->how_to_use,
            'skin_type'        => $p->skin_type,
            'gender'           => $p->gender ?? 'unisex',
            'volume'           => $p->volume,
            'price_inr'        => $p->price_inr,
            'special_price'    => $p->special_price,
            'effective_price'  => $p->effective_price,
            'discount_pct'     => $p->discount_percentage,
            'special_price_starts_at' => $p->special_price_starts_at?->toDateString(),
            'special_price_ends_at'   => $p->special_price_ends_at?->toDateString(),
            'stock_quantity'   => $p->stock_quantity,
            'low_stock_threshold' => $p->low_stock_threshold,
            'is_low_stock'     => $p->is_low_stock,
            'meta_title'       => $p->meta_title,
            'meta_description' => $p->meta_description,
            'shipping_returns' => $p->shipping_returns,
            'is_active'        => $p->is_active,
            'is_new'           => $p->new_arrival,
            'is_bestseller'    => $p->best_seller,
            'dynamic_tags'     => $p->dynamic_tags,
            'average_rating'   => $p->average_rating,
            'review_count'     => $p->review_count,
            'cover_image'      => $this->ensureAbsoluteUrl($p->getFirstMediaUrl('gallery', 'medium')),
            'media'            => $p->getMedia('gallery')->map(fn ($m) => $this->mediaTransform($m)),
            'created_at'       => $p->created_at->toDateString(),
        ];
    }

    private function mediaTransform(Media $m): array
    {
        return [
            'id'        => $m->id,
            'url'       => $this->ensureAbsoluteUrl($m->getUrl('medium')),
            'thumb_url' => $this->ensureAbsoluteUrl($m->getUrl('thumb')),
            'original'  => $this->ensureAbsoluteUrl($m->getUrl()),
            'order'     => $m->order_column,
        ];
    }

    private function ensureAbsoluteUrl(?string $url): ?string
    {
        if (!$url) return null;
        
        // Strip out literal newlines, carriage returns, tabs, and leading/trailing whitespace
        $url = trim(str_replace(["\r", "\n", "\t"], '', $url));

        // Auto-heal any misconfigured legacy "products" bucket URLs to the new "media" bucket
        $url = str_replace('/object/public/products', '/object/public/media', $url);

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        return rtrim(request()->root(), '/') . '/' . ltrim($url, '/');
    }
}
