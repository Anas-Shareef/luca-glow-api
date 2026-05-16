<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\OrderItem;

class ReviewController extends Controller
{
    /**
     * Display a listing of reviews for admin.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProductReview::with(['product:id,name', 'user:id,name,email'])
            ->latest();

        if ($request->has('published')) {
            $query->where('is_published', $request->boolean('published'));
        }

        if ($request->has('manual')) {
            $query->where('is_manual', $request->boolean('manual'));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('comment', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('reviewer_name', 'like', "%{$search}%")
                  ->orWhere('reviewer_email', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('product', fn($pq) => $pq->where('name', 'like', "%{$search}%"));
            });
        }

        $reviews = $query->paginate($request->get('per_page', 10));
        
        $reviews->getCollection()->transform(fn($r) => $this->transform($r));

        return response()->json($reviews);
    }

    /**
     * Store a new review from storefront.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating'     => 'required|integer|min:1|max:5',
            'title'      => 'nullable|string|max:255',
            'comment'    => 'required|string|min:3',
            'images.*'   => 'nullable|image|max:5120',
        ]);

        $user = Auth::user();
        
        $existing = ProductReview::where('product_id', $request->product_id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You have already reviewed this product.'], 422);
        }

        $isVerified = OrderItem::whereHas('order', function($q) use ($user) {
            $q->where('customer_id', $user->id)
              ->where('status', 'Delivered');
        })->where('product_id', $request->product_id)
          ->exists();

        $review = DB::transaction(function() use ($request, $user, $isVerified) {
            $review = ProductReview::create([
                'product_id'           => $request->product_id,
                'user_id'              => $user->id,
                'rating'               => $request->rating,
                'title'                => $request->title,
                'comment'              => $request->comment,
                'is_published'         => false, // Default to false for moderation
                'is_verified_purchase' => $isVerified,
                'is_manual'            => false,
            ]);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    $review->addMedia($file)->toMediaCollection('photos');
                }
            }

            return $review;
        });

        return response()->json([
            'message' => 'Review submitted successfully. It will be visible after approval.',
            'review'  => $this->transform($review->load(['user:id,name', 'media']))
        ], 201);
    }

    /**
     * Admin: Manually create a review.
     */
    public function adminStore(Request $request): JsonResponse
    {
        $request->validate([
            'product_id'           => 'required|exists:products,id',
            'rating'               => 'required|integer|min:1|max:5',
            'title'                => 'nullable|string|max:255',
            'comment'              => 'required|string',
            'reviewer_name'        => 'required|string|max:255',
            'reviewer_email'       => 'nullable|email|max:255',
            'is_verified_purchase' => 'required|boolean',
            'is_published'         => 'required|boolean',
            'images.*'             => 'nullable|image|max:5120',
        ]);

        $review = DB::transaction(function() use ($request) {
            $review = ProductReview::create([
                'product_id'           => $request->product_id,
                'user_id'              => null,
                'reviewer_name'        => $request->reviewer_name,
                'reviewer_email'       => $request->reviewer_email,
                'rating'               => $request->rating,
                'title'                => $request->title,
                'comment'              => $request->comment,
                'is_published'         => $request->is_published,
                'is_verified_purchase' => $request->is_verified_purchase,
                'is_manual'            => true,
            ]);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    $review->addMedia($file)->toMediaCollection('photos');
                }
            }

            return $review;
        });

        return response()->json([
            'message' => 'Manual review created successfully.',
            'review'  => $this->transform($review->load(['product:id,name', 'media']))
        ], 201);
    }

    /**
     * Update review (admin).
     */
    public function update(Request $request, $id): JsonResponse
    {
        $review = ProductReview::findOrFail($id);

        $data = $request->validate([
            'is_published' => 'sometimes|boolean',
            'comment'      => 'sometimes|string',
            'rating'       => 'sometimes|integer|min:1|max:5',
            'title'        => 'sometimes|nullable|string',
        ]);

        $review->update($data);

        return response()->json([
            'message' => 'Review updated successfully.',
            'review'  => $this->transform($review->fresh()->load(['user:id,name', 'product:id,name', 'media']))
        ]);
    }

    /**
     * Remove review (admin).
     */
    public function destroy($id): JsonResponse
    {
        $review = ProductReview::findOrFail($id);
        $review->delete();

        return response()->json(['message' => 'Review deleted successfully.']);
    }

    /**
     * Bulk Delete (admin).
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'exists:product_reviews,id']);
        
        ProductReview::whereIn('id', $request->ids)->delete();

        return response()->json(['message' => 'Selected reviews deleted successfully.']);
    }

    /**
     * Get reviews for a specific product (storefront).
     */
    public function productReviews($slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->firstOrFail();

        $reviews = $product->reviews()
            ->where('is_published', true)
            ->with(['user:id,name', 'media'])
            ->latest()
            ->paginate(10);

        // Transform reviews to include media URLs
        $reviews->getCollection()->transform(fn($r) => $this->transform($r));

        return response()->json([
            'rating'  => $product->average_rating,
            'count'   => $product->review_count,
            'reviews' => $reviews
        ]);
    }

    private function transform(ProductReview $r): array
    {
        return [
            'id'                   => $r->id,
            'product_id'           => $r->product_id,
            'user'                 => $r->user ? $r->user->only(['id', 'name', 'email']) : [
                'name'  => $r->reviewer_name,
                'email' => $r->reviewer_email
            ],
            'reviewer_name'        => $r->reviewer_name,
            'reviewer_email'       => $r->reviewer_email,
            'product'              => $r->product?->only(['id', 'name']),
            'rating'               => $r->rating,
            'title'                => $r->title,
            'comment'              => $r->comment,
            'is_published'         => $r->is_published,
            'is_verified_purchase' => $r->is_verified_purchase,
            'is_manual'            => $r->is_manual,
            'helpful_count'        => $r->helpful_count,
            'created_at'           => $r->created_at->toIso8601String(),
            'photos'               => $r->getMedia('photos')->map(fn($m) => [
                'id'  => $m->id,
                'url' => $m->getUrl(),
            ]),
        ];
    }
}
