<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\Category;
use App\Models\Slider;
use Illuminate\Http\Request;

class StorefrontController
{
    public function data()
    {
        $data = \Illuminate\Support\Facades\Cache::remember('storefront_data', 300, function () {
            $products = Product::with(['category', 'media'])
                ->withCount('orderItems')
                ->active()
                ->get()
                ->map(function ($p) {
                    return [
                        'id'   => $p->id,
                        'slug' => $p->slug,
                        'name' => $p->name,
                        'category' => $p->category ? $p->category->slug : null,
                        'gender' => $p->gender ?? 'unisex',
                        'tags' => $p->dynamic_tags,
                        'priceInr' => $p->price_inr,
                        'priceAed' => null,
                        'compareAtInr' => $p->special_price ? $p->price_inr : null,
                        'unit' => $p->volume ?: null,
                        'unitValue' => $p->volume ? ((int) filter_var($p->volume, FILTER_SANITIZE_NUMBER_INT) ?: 1) : null,
                        'unitType' => $p->volume ? (str_contains($p->volume, 'g') ? 'g' : (str_contains($p->volume, 'ml') ? 'ml' : 'pack')) : null,
                        'inStock' => $p->stock_quantity > 0,
                        'rating' => $p->average_rating,
                        'reviews' => $p->review_count,
                        'ingredients' => $p->ingredients ? array_values(array_filter(preg_split('/[\n,]+/', $p->ingredients))) : [],
                        'how_to_use' => $p->how_to_use ? array_values(array_filter(preg_split('/[\n,]+/', $p->how_to_use))) : [],
                        'shipping_returns' => $p->shipping_returns ? array_values(array_filter(preg_split('/\n/', $p->shipping_returns))) : [],
                        'benefits' => [],
                        'description' => $p->description ?? '',
                        'image' => count($p->getMedia('gallery')) > 0 ? $this->ensureAbsoluteUrl($p->getMedia('gallery')[0]->getUrl()) : 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=900&q=80',
                        'imageHover' => count($p->getMedia('gallery')) > 1 ? $this->ensureAbsoluteUrl($p->getMedia('gallery')[1]->getUrl()) : 'https://images.unsplash.com/photo-1570194065650-d99fb4bedf0a?auto=format&fit=crop&w=900&q=80',
                        'gallery' => $p->getMedia('gallery')->map(fn($m) => $this->ensureAbsoluteUrl($m->getUrl())),
                        'bestSeller' => $p->best_seller,
                        'newArrival' => $p->new_arrival,
                    ];
                });

            $categories = Category::with('media')->active()->get()->map(function ($c) {
                return [
                    'id' => $c->id,
                    'parent_id' => $c->parent_id,
                    'slug' => $c->slug,
                    'name' => $c->name,
                    'description' => $c->description ?? '',
                    'image' => count($c->getMedia('banner')) > 0 ? $this->ensureAbsoluteUrl($c->getFirstMediaUrl('banner')) : 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=900&q=80',
                ];
            });

            $sliders = Slider::with('media')->where('is_active', true)->orderBy('sort_order')->get()->map(function ($s) {
                return [
                    'id'          => $s->id,
                    'title'       => $s->title,
                    'subtitle'    => $s->subtitle,
                    'button_text' => $s->button_text,
                    'link_url'    => $s->link_url,
                    'image_url'   => $this->ensureAbsoluteUrl($s->getFirstMediaUrl('banner', 'banner_webp') ?: $s->getFirstMediaUrl('banner')),
                    'mobile_url'  => $this->ensureAbsoluteUrl($s->getFirstMediaUrl('mobile_banner', 'banner_webp') ?: $s->getFirstMediaUrl('mobile_banner')),
                    'is_live'     => $s->isCurrentlyActive(),
                ];
            })->filter(fn($s) => $s['is_live'])->values();

            $promoImg = \App\Models\Setting::get('promo_image_url');
            if ($promoImg) {
                $promoImg = $this->ensureAbsoluteUrl($promoImg);
            }

            $promo = [
                'tag' => \App\Models\Setting::get('promo_tag', 'New Arrival'),
                'title' => \App\Models\Setting::get('promo_title', 'Lykha Foundations'),
                'subtitle' => \App\Models\Setting::get('promo_subtitle', 'Glow Beyond Limits'),
                'image' => $promoImg ?? 'https://images.unsplash.com/photo-1631730486572-226d1f595b68?auto=format&fit=crop&w=600&q=80',
                'slug' => \App\Models\Setting::get('promo_slug', 'lykha-makeup'),
            ];

            $instagramFeedRaw = \App\Models\Setting::get('instagram_feed');
            $instagramFeed = null;
            if ($instagramFeedRaw) {
                $instagramFeed = json_decode($instagramFeedRaw, true);
            }
            if (!$instagramFeed || !is_array($instagramFeed)) {
                $instagramFeed = [
                    ['image_url' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=400&q=80', 'post_url' => 'https://instagram.com'],
                    ['image_url' => 'https://images.unsplash.com/photo-1571781926291-c477ebfd024b?auto=format&fit=crop&w=400&q=80', 'post_url' => 'https://instagram.com'],
                    ['image_url' => 'https://images.unsplash.com/photo-1599733589046-8f57e5d3a907?auto=format&fit=crop&w=400&q=80', 'post_url' => 'https://instagram.com'],
                    ['image_url' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=400&q=80', 'post_url' => 'https://instagram.com'],
                    ['image_url' => 'https://images.unsplash.com/photo-1556228841-a3c527ebefe5?auto=format&fit=crop&w=400&q=80', 'post_url' => 'https://instagram.com'],
                    ['image_url' => 'https://images.unsplash.com/photo-1631730486572-226d1f595b68?auto=format&fit=crop&w=400&q=80', 'post_url' => 'https://instagram.com'],
                ];
            } else {
                foreach ($instagramFeed as &$item) {
                    if (isset($item['image_url'])) {
                        $item['image_url'] = $this->ensureAbsoluteUrl($item['image_url']);
                    }
                }
                unset($item);
            }

            return [
                'products' => $products,
                'categories' => $categories,
                'sliders' => $sliders,
                'promo' => $promo,
                'instagram_feed' => $instagramFeed,
            ];
        });

        return response()->json($data);
    }

    private function ensureAbsoluteUrl(?string $url): ?string
    {
        if (!$url) return null;
        
        // Strip out literal newlines, carriage returns, tabs, and leading/trailing whitespace
        $url = trim(str_replace(["\r", "\n", "\t"], '', $url));

        // Auto-heal any misconfigured legacy "products" bucket URLs to the new "media" bucket
        $url = str_replace('/object/public/products', '/object/public/media', $url);

        if (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
            $parsed = parse_url($url);
            $url = ($parsed['path'] ?? '') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            if (str_starts_with($url, 'http://')) {
                $url = 'https://' . substr($url, 7);
            }
            return $url;
        }

        $root = rtrim(request()->root(), '/');
        if (str_starts_with($root, 'http://') && !str_contains($root, 'localhost') && !str_contains($root, '127.0.0.1')) {
            $root = 'https://' . substr($root, 7);
        }

        return $root . '/' . ltrim($url, '/');
    }

    public function validateCoupon(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'cart_total' => 'required|numeric'
        ]);

        $coupon = \App\Models\Coupon::where('code', strtoupper($request->code))->first();

        if (!$coupon) {
            return response()->json(['message' => 'Invalid promo code.'], 422);
        }

        if (!$coupon->is_active) {
            return response()->json(['message' => 'This promo code is no longer active.'], 422);
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            return response()->json(['message' => 'This promo code has expired.'], 422);
        }

        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            return response()->json(['message' => 'This promo code has reached its usage limit.'], 422);
        }

        if ($coupon->min_cart_value && $request->cart_total < $coupon->min_cart_value) {
            return response()->json(['message' => "This coupon requires a minimum spend of ₹{$coupon->min_cart_value}."], 422);
        }

        return response()->json([
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => $coupon->value,
            'message' => 'Coupon applied successfully!'
        ]);
    }
}
