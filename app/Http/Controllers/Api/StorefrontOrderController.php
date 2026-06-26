<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderPlaced;

class StorefrontOrderController extends Controller
{
    /**
     * POST /api/storefront/checkout
     * Create a real order from the storefront cart.
     */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'                       => 'required|array|min:1',
            'items.*.product_slug'        => 'required|string',
            'items.*.quantity'            => 'required|integer|min:1',
            'shipping.fullName'           => 'required|string|max:80',
            'shipping.phone'              => 'required|string|max:20',
            'shipping.address_line_1'     => 'required|string|max:120',
            'shipping.address_line_2'     => 'nullable|string|max:60',
            'shipping.city'               => 'required|string|max:60',
            'shipping.state'              => 'required|string|max:60',
            'shipping.pincode'            => 'required|string|max:10',
            'shipping.country'            => 'required|in:India,UAE',
            'shipping_method'             => 'required|in:standard,express',
            'payment_method'              => 'required|in:razorpay',
            'coupon_code'                 => 'nullable|string|max:30',
            'subtotal'                    => 'required|integer|min:0',
            'shipping_cost'               => 'required|integer|min:0',
            'tax'                         => 'nullable|integer|min:0',
            'discount'                    => 'nullable|integer|min:0',
            'total'                       => 'required|integer|min:1',
        ]);

        $slugs = collect($data['items'])->pluck('product_slug');
        $user  = $request->user();

        $order = DB::transaction(function () use ($data, $slugs, $user) {
            // Pessimistic Locking: Lock these products specifically to prevent overselling if 2 users buy at the same millisecond
            $products = Product::whereIn('slug', $slugs)->lockForUpdate()->get()->keyBy('slug');

            foreach ($data['items'] as $item) {
                if (!$products->has($item['product_slug'])) {
                    abort(response()->json(['message' => "Product '{$item['product_slug']}' not found."], 422));
                }
                if ($products[$item['product_slug']]->stock_quantity < $item['quantity']) {
                    abort(response()->json(['message' => "Insufficient stock for product '{$products[$item['product_slug']]->name}'."], 422));
                }
            }

            // Create order
            $order = Order::create([
                'customer_id'         => $user->id,
                'status'              => 'pending',
                'payment_method'      => $data['payment_method'],
                'payment_status'      => 'pending',
                'subtotal_inr'        => $data['subtotal'],
                'shipping_amount_inr' => $data['shipping_cost'],
                'tax_amount_inr'      => $data['tax'],
                'discount_amount_inr' => $data['discount'] ?? 0,
                'total_amount_inr'    => $data['total'],
                'coupon_code'         => $data['coupon_code'] ?? null,
            ]);

            // Create shipping address snapshot
            $ship = $data['shipping'];
            OrderAddress::create([
                'order_id'       => $order->id,
                'type'           => 'shipping',
                'full_name'      => $ship['fullName'],
                'phone'          => $ship['phone'],
                'address_line_1' => $ship['address_line_1'],
                'address_line_2' => $ship['address_line_2'] ?? null,
                'city'           => $ship['city'],
                'state'          => $ship['state'],
                'pincode'        => $ship['pincode'],
                'country'        => $ship['country'],
            ]);

            // Create order items
            foreach ($data['items'] as $item) {
                $product = $products[$item['product_slug']];
                $price   = $product->special_price && now()->between(
                    $product->special_price_starts_at ?? now()->subDay(),
                    $product->special_price_ends_at   ?? now()->addDay()
                ) ? $product->special_price : $product->price_inr;

                OrderItem::create([
                    'order_id'       => $order->id,
                    'product_id'     => $product->id,
                    'product_name'   => $product->name,
                    'product_sku'    => $product->sku,
                    'quantity'       => $item['quantity'],
                    'unit_price_inr' => $price,
                    'subtotal_inr'   => $price * $item['quantity'],
                ]);

                // Decrement stock
                $product->decrement('stock', $item['quantity']);
            }

            return $order;
        });

        // Trigger group automation (e.g. First-Time -> Regular)
        $user->fresh()->checkAndUpgradeGroup();

        // Send Order Confirmation Email
        try {
            Mail::to($user->email)->send(new OrderPlaced($order));
        } catch (\Exception $e) {
            \Log::error('Failed to send order confirmation email: ' . $e->getMessage());
        }

        // Clear storefront cache to update live stock
        \Illuminate\Support\Facades\Cache::forget('storefront_data');

        return response()->json([
            'message'      => 'Order placed successfully!',
            'order_number' => $order->order_number,
            'order_id'     => $order->id,
        ], 201);
    }

    /**
     * POST /api/storefront/orders/{order}/refund-request
     * Customer submits a refund request.
     */
    public function requestRefund(Request $request, Order $order): JsonResponse
    {
        // Authorization: only the customer who placed the order
        if ($order->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Only delivered orders, within 7 days
        if ($order->status !== 'delivered') {
            return response()->json(['message' => 'Refunds can only be requested for delivered orders.'], 422);
        }


        // No duplicate requests
        if ($order->refund_status) {
            return response()->json(['message' => 'A refund request already exists for this order.'], 422);
        }

        $data = $request->validate([
            'reason'      => 'required|in:damaged,wrong_item,allergic,other',
            'description' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($order, $data, $request) {
            RefundRequest::create([
                'order_id'    => $order->id,
                'user_id'     => $request->user()->id,
                'reason'      => $data['reason'],
                'description' => $data['description'] ?? null,
                'status'      => 'pending',
            ]);

            $order->update(['refund_status' => 'pending']);
        });

        return response()->json(['message' => 'Refund request submitted. We will review it within 2 business days.']);
    }

    /**
     * POST /api/storefront/orders/{order}/cancel
     * Customer cancels their own order (only if status is 'pending').
     */
    public function cancelOrder(Request $request, Order $order): JsonResponse
    {
        // Authorization
        if ($order->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Only pending orders can be cancelled by customer
        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Only pending orders can be cancelled. Please contact support for assistance.'], 422);
        }

        DB::transaction(function () use ($order, $request) {
            $oldStatus = $order->status;
            
            // Update order status
            $order->update(['status' => 'cancelled']);

            // Restore stock
            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->product->increment('stock', $item->quantity);
                }
            }

            // If it was already paid, flag for financial refund
            $historyComment = 'Cancelled by customer';
            if ($order->payment_status === 'paid') {
                $order->update(['refund_status' => 'pending']);
                $historyComment .= ' - Financial refund pending';
                
                // Create an automatic refund request record
                RefundRequest::create([
                    'order_id'    => $order->id,
                    'user_id'     => $order->customer_id,
                    'reason'      => 'other',
                    'description' => 'Automatic request: Order cancelled after payment.',
                    'status'      => 'pending',
                ]);
            }

            // Record in history
            $order->statusHistory()->create([
                'from_status'       => $oldStatus,
                'to_status'         => 'cancelled',
                'comment'           => $historyComment,
                'customer_notified' => false,
                'changed_by'        => $request->user()->id,
            ]);
        });

        // Clear storefront cache to update live stock
        \Illuminate\Support\Facades\Cache::forget('storefront_data');

        return response()->json(['message' => 'Order has been cancelled successfully.']);
    }
}
