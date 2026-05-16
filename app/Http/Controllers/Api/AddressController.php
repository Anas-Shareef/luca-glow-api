<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    /** GET /api/storefront/addresses */
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get()
            ->map(fn($a) => $a->toApiArray());

        return response()->json($addresses);
    }

    /** POST /api/storefront/addresses */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fullName'  => 'required|string|max:80',
            'phone'     => 'required|string|max:20',
            'street'    => 'required|string|max:120',
            'apt'       => 'nullable|string|max:60',
            'city'      => 'required|string|max:60',
            'state'     => 'required|string|max:60',
            'zip'       => 'required|string|max:10',
            'country'   => 'required|in:India,UAE',
            'isDefault' => 'boolean',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user, $data, &$address) {
            if ($data['isDefault'] ?? false) {
                $user->addresses()->update(['is_default' => false]);
            }
            $address = $user->addresses()->create([
                'full_name'      => $data['fullName'],
                'phone'          => $data['phone'],
                'address_line_1' => $data['street'],
                'address_line_2' => $data['apt'] ?? null,
                'city'           => $data['city'],
                'state'          => $data['state'],
                'pincode'        => $data['zip'],
                'country'        => $data['country'],
                'is_default'     => $data['isDefault'] ?? false,
            ]);

            // Auto-set as default if it's the user's first address
            if ($user->addresses()->count() === 1) {
                $address->update(['is_default' => true]);
            }
        });

        return response()->json($address->fresh()->toApiArray(), 201);
    }

    /** PUT /api/storefront/addresses/{address} */
    public function update(Request $request, UserAddress $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'fullName'  => 'required|string|max:80',
            'phone'     => 'required|string|max:20',
            'street'    => 'required|string|max:120',
            'apt'       => 'nullable|string|max:60',
            'city'      => 'required|string|max:60',
            'state'     => 'required|string|max:60',
            'zip'       => 'required|string|max:10',
            'country'   => 'required|in:India,UAE',
            'isDefault' => 'boolean',
        ]);

        DB::transaction(function () use ($request, $data, $address) {
            if ($data['isDefault'] ?? false) {
                $request->user()->addresses()->update(['is_default' => false]);
            }
            $address->update([
                'full_name'      => $data['fullName'],
                'phone'          => $data['phone'],
                'address_line_1' => $data['street'],
                'address_line_2' => $data['apt'] ?? null,
                'city'           => $data['city'],
                'state'          => $data['state'],
                'pincode'        => $data['zip'],
                'country'        => $data['country'],
                'is_default'     => $data['isDefault'] ?? false,
            ]);
        });

        return response()->json($address->fresh()->toApiArray());
    }

    /** DELETE /api/storefront/addresses/{address} */
    public function destroy(Request $request, UserAddress $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $wasDefault = $address->is_default;
        $address->delete();

        // Promote the first remaining address to default
        if ($wasDefault) {
            $request->user()->addresses()->oldest()->first()?->update(['is_default' => true]);
        }

        return response()->json(['message' => 'Address deleted']);
    }

    /** PATCH /api/storefront/addresses/{address}/default */
    public function setDefault(Request $request, UserAddress $address): JsonResponse
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        DB::transaction(function () use ($request, $address) {
            $request->user()->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return response()->json($address->fresh()->toApiArray());
    }
}
