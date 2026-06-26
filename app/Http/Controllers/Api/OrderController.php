<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Setting;
use App\Models\RefundRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    /**
     * GET /api/v1/admin/orders
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['customer', 'shippingAddress', 'items'])
            ->when($request->search,    fn ($q) => $q->search($request->search))
            ->when($request->status,    fn ($q) => $q->byStatus($request->status))
            ->when($request->date_from || $request->date_to,
                fn ($q) => $q->dateRange($request->date_from, $request->date_to))
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_dir ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => $orders->map(fn ($o) => $this->transform($o)),
            'meta' => [
                'total'        => $orders->total(),
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'status_counts' => $this->statusCounts(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/orders/{order}
     */
    public function show(Order $order): JsonResponse
    {
        $order->load([
            'customer.customerGroup',
            'items.product',
            'shippingAddress',
            'billingAddress',
            'statusHistory.changedBy',
        ]);

        return response()->json($this->transformDetail($order));
    }

    /**
     * PATCH /api/v1/admin/orders/{order}/status
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'status'            => 'required|in:pending,processing,shipped,delivered,cancelled',
            'comment'           => 'nullable|string|max:500',
            'notify_customer'   => 'boolean',
        ]);

        $oldStatus = $order->status;

        DB::transaction(function () use ($order, $request, $oldStatus) {
            $order->update(['status' => $request->status]);

            // Record in history log
            $order->statusHistory()->create([
                'from_status'       => $oldStatus,
                'to_status'         => $request->status,
                'comment'           => $request->comment,
                'customer_notified' => $request->boolean('notify_customer'),
                'changed_by'        => optional($request->user())->id,
            ]);

            // Fire notification if requested
            if ($request->boolean('notify_customer') && $order->customer?->email) {
                // Mail::to($order->customer->email)->send(new \App\Mail\OrderStatusUpdated($order));
            }

            // Restore stock if cancelled
            if ($request->status === 'cancelled' && $oldStatus !== 'cancelled') {
                foreach ($order->items as $item) {
                    if ($item->product) {
                        $item->product->increment('stock', $item->quantity);
                    }
                }

                // If paid, flag for financial refund
                if ($order->payment_status === 'paid') {
                    $order->update(['refund_status' => 'pending']);
                    
                    // Auto-create refund request if not exists
                    if (!$order->refundRequest) {
                        \App\Models\RefundRequest::create([
                            'order_id'    => $order->id,
                            'user_id'     => $order->customer_id,
                            'reason'      => 'other',
                            'description' => 'Automatic request: Admin cancelled a paid order.',
                            'status'      => 'pending',
                        ]);
                    }
                }
            }

            // Auto-check VIP upgrade on delivery
            if ($request->status === 'delivered') {
                $order->customer?->checkAndUpgradeGroup();
            }
        });

        return response()->json([
            'status'     => $order->fresh()->status,
            'message'    => "Order {$order->order_number} updated to {$request->status}.",
        ]);
    }

    /**
     * POST /api/v1/admin/orders/bulk-status
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'order_ids' => 'required|array',
            'status'    => 'required|in:pending,processing,shipped,delivered,cancelled',
        ]);

        Order::whereIn('id', $request->order_ids)->update(['status' => $request->status]);

        return response()->json([
            'message' => count($request->order_ids) . ' order(s) updated to ' . $request->status,
        ]);
    }

    /**
     * GET /api/v1/admin/orders/{order}/invoice
     * Streams a branded PDF invoice using DomPDF
     */
    public function invoice(Order $order)
    {
        $order->load(['customer', 'items.product', 'shippingAddress', 'billingAddress']);

        $gstRate    = (float) Setting::get('gst_rate', 18);
        $storeName  = Setting::get('store_name', 'Luca Glow');

        $pdf = Pdf::loadView('invoices.order', compact('order', 'gstRate', 'storeName'))
            ->setPaper('a4', 'portrait')
            ->setOptions(['dpi' => 150, 'isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);

        return $pdf->stream("invoice-{$order->order_number}.pdf");
    }

    /**
     * GET /api/v1/admin/orders/export/csv
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $orders = Order::with(['customer', 'shippingAddress', 'items'])
            ->when($request->status && $request->status !== 'all', fn ($q) => $q->where('status', $request->status))
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="orders-' . now()->format('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($orders) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Order ID', 'Customer', 'City', 'Total (INR)',
                'Items', 'Status', 'Date',
            ]);

            foreach ($orders as $o) {
                fputcsv($handle, [
                    $o->order_number,
                    $o->customer?->name ?? 'Guest',
                    $o->shippingAddress?->city ?? '—',
                    '₹' . number_format($o->total_amount_inr),
                    $o->items->count() . ' items',
                    ucfirst($o->status),
                    $o->created_at->format('Y-m-d'),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * GET /api/v1/admin/orders/export/pdf
     */
    public function exportPdf(Request $request)
    {
        $orders = Order::with(['customer', 'shippingAddress', 'items'])
            ->when($request->status && $request->status !== 'all', fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->search($request->search))
            ->latest()->get();

        $pdf = Pdf::loadView('reports.orders', compact('orders'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('orders-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * GET /api/v1/storefront/orders
     * Returns orders for the authenticated customer
     */
    public function customerOrders(Request $request): JsonResponse
    {
        $orders = Order::with(['items.product', 'shippingAddress'])
            ->where('customer_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($orders->map(fn ($o) => [
            'id'            => $o->id,
            'order_number'  => $o->order_number,
            'status'        => ucfirst($o->status ?? 'Pending'),
            'payment_method'=> $o->payment_method,
            'refund_status' => $o->refund_status,
            'date'          => $o->created_at ? $o->created_at->format('d M Y') : 'N/A',
            'total'         => $o->total_amount_inr,
            'address'       => $o->shippingAddress 
                ? ($o->shippingAddress->address_line_1 . ', ' . $o->shippingAddress->city)
                : 'No address provided',
            'items'         => $o->items->map(fn ($it) => [
                'name'  => $it->product_name,
                'qty'   => $it->quantity,
                'price' => $it->unit_price_inr,
            ]),
        ]));
    }

    // ── Helpers ───────────────────────────────────────────────
    private function transform(Order $o): array
    {
        return [
            'id'            => $o->id,
            'order_number'  => $o->order_number,
            'customer'      => [
                'name'  => $o->customer->name,
                'email' => $o->customer->email,
                'phone' => $o->customer->phone,
            ],
            'total_inr'     => $o->total_amount_inr,
            'status'        => $o->status,
            'payment_method'=> $o->payment_method,
            'refund_status' => $o->refund_status,
            'items'         => $o->items->count(),
            'city'          => $o->shippingAddress?->city ?? '—',
            'created_at'    => $o->created_at->toDateString(),
        ];
    }

    // ── Admin Refund Management ───────────────────────────────

    /**
     * GET /api/v1/admin/orders/refund-requests
     */
    public function refundRequests(Request $request): JsonResponse
    {
        $requests = \App\Models\RefundRequest::with(['order', 'user'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $requests->map(fn ($r) => [
                'id'           => $r->id,
                'order_number' => $r->order->order_number,
                'customer'     => $r->user->name,
                'email'        => $r->user->email,
                'reason'       => $r->reason,
                'description'  => $r->description,
                'status'       => $r->status,
                'admin_note'   => $r->admin_note,
                'requested_at' => $r->created_at->format('d M Y'),
                'resolved_at'  => $r->resolved_at?->format('d M Y'),
                'total'        => $r->order->total_amount_inr,
            ]),
            'meta' => [
                'total'        => $requests->total(),
                'current_page' => $requests->currentPage(),
                'last_page'    => $requests->lastPage(),
            ],
        ]);
    }

    /**
     * PATCH /api/v1/admin/orders/{order}/refund/approve
     */
    public function approveRefund(Request $request, Order $order): JsonResponse
    {
        $request->validate(['admin_note' => 'nullable|string|max:500']);

        $refund = $order->refundRequest;
        if (!$refund || $refund->status !== 'pending') {
            return response()->json(['message' => 'No pending refund request found.'], 422);
        }

        DB::transaction(function () use ($order, $refund, $request) {
            $refund->update([
                'status'      => 'approved',
                'admin_note'  => $request->admin_note,
                'resolved_at' => now(),
            ]);
            $order->update(['refund_status' => 'approved']);
        });

        return response()->json(['message' => "Refund approved for order {$order->order_number}."]);
    }

    /**
     * PATCH /api/v1/admin/orders/{order}/refund/reject
     */
    public function rejectRefund(Request $request, Order $order): JsonResponse
    {
        $request->validate(['admin_note' => 'required|string|max:500']);

        $refund = $order->refundRequest;
        if (!$refund || $refund->status !== 'pending') {
            return response()->json(['message' => 'No pending refund request found.'], 422);
        }

        DB::transaction(function () use ($order, $refund, $request) {
            $refund->update([
                'status'      => 'rejected',
                'admin_note'  => $request->admin_note,
                'resolved_at' => now(),
            ]);
            $order->update(['refund_status' => 'rejected']);
        });

        return response()->json(['message' => "Refund rejected for order {$order->order_number}."]);
    }

    private function transformDetail(Order $o): array
    {
        return [
            ...$this->transform($o),
            'subtotal_inr'        => $o->subtotal_inr,
            'shipping_amount_inr' => $o->shipping_amount_inr,
            'tax_amount_inr'      => $o->tax_amount_inr,
            'discount_amount_inr' => $o->discount_amount_inr,
            'coupon_code'         => $o->coupon_code,
            'payment_method'      => $o->payment_method,
            'payment_status'      => $o->payment_status,
            'notes'               => $o->notes,
            'items'               => $o->items->map(fn ($i) => [
                'id'           => $i->id,
                'product_name' => $i->product_name,
                'product_sku'  => $i->product_sku,
                'quantity'     => $i->quantity,
                'unit_price'   => $i->unit_price_inr,
                'subtotal'     => $i->subtotal_inr,
                'variant_info' => $i->variant_info,
                'cover_image'  => $i->product?->getFirstMediaUrl('gallery', 'thumb'),
            ]),
            'shipping_address' => $o->shippingAddress,
            'billing_address'  => $o->billingAddress,
            'status_history'   => $o->statusHistory->map(fn ($h) => [
                'from'              => $h->from_status,
                'to'                => $h->to_status,
                'comment'           => $h->comment,
                'customer_notified' => $h->customer_notified,
                'changed_by'        => $h->changedBy?->name,
                'at'                => $h->created_at,
            ]),
        ];
    }

    private function statusCounts(): array
    {
        return Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }
}
