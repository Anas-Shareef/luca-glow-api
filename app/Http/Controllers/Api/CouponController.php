<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CouponController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Coupon::orderByDesc('created_at')->get()->map(fn ($c) => $this->transform($c))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'               => 'required|string|max:60|unique:coupons,code',
            'type'               => 'required|in:percentage,fixed',
            'value'              => 'required|integer|min:1',
            'min_cart_value'     => 'nullable|integer|min:0',
            'usage_limit'        => 'nullable|integer|min:1',
            'usage_per_customer' => 'integer|min:1',
            'is_active'          => 'boolean',
            'starts_at'          => 'nullable|date',
            'expires_at'         => 'nullable|date|after_or_equal:starts_at',
        ]);

        $data['code'] = strtoupper($data['code']);
        $coupon = Coupon::create($data);

        return response()->json($this->transform($coupon), 201);
    }

    public function show(Coupon $coupon): JsonResponse
    {
        return response()->json($this->transform($coupon));
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $data = $request->validate([
            'type'           => 'in:percentage,fixed',
            'value'          => 'integer|min:1',
            'min_cart_value' => 'nullable|integer|min:0',
            'usage_limit'    => 'nullable|integer|min:1',
            'is_active'      => 'boolean',
            'starts_at'      => 'nullable|date',
            'expires_at'     => 'nullable|date',
        ]);

        $coupon->update($data);
        return response()->json($this->transform($coupon->fresh()));
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();
        return response()->json(['message' => 'Coupon deleted.']);
    }

    public function toggleStatus(Coupon $coupon): JsonResponse
    {
        $coupon->update(['is_active' => !$coupon->is_active]);
        return response()->json(['is_active' => $coupon->fresh()->is_active]);
    }

    private function transform(Coupon $c): array
    {
        return [
            'id'                 => $c->id,
            'code'               => $c->code,
            'type'               => $c->type,
            'value'              => $c->value,
            'min_cart_value'     => $c->min_cart_value,
            'usage_limit'        => $c->usage_limit,
            'usage_per_customer' => $c->usage_per_customer,
            'used_count'         => $c->used_count,
            'is_active'          => $c->is_active,
            'is_valid'           => $c->isValid(),
            'starts_at'          => $c->starts_at?->toDateString(),
            'expires_at'         => $c->expires_at?->toDateString(),
            'is_expired'         => $c->expires_at && $c->expires_at->isPast(),
            'created_at'         => $c->created_at->toDateString(),
        ];
    }
}
