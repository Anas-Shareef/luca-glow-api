<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * GET /api/v1/admin/dashboard/stats
     * KPI cards: total_sales, total_orders, total_customers, avg_order_value + growth %
     */
    public function stats(): JsonResponse
    {
        $now   = now();
        $start = $now->copy()->startOfMonth();
        $prevStart = $now->copy()->subMonth()->startOfMonth();
        $prevEnd   = $now->copy()->subMonth()->endOfMonth();

        // This month
        $salesThisMonth    = Order::whereBetween('created_at', [$start, $now])
            ->whereNotIn('status', ['cancelled'])->sum('total_amount_inr');
        $ordersThisMonth   = Order::whereBetween('created_at', [$start, $now])
            ->whereNotIn('status', ['cancelled'])->count();
        $customersThisMonth = User::whereBetween('created_at', [$start, $now])->count();

        // Previous month (for growth %)
        $salesLastMonth  = Order::whereBetween('created_at', [$prevStart, $prevEnd])
            ->whereNotIn('status', ['cancelled'])->sum('total_amount_inr') ?: 1;
        $ordersLastMonth = Order::whereBetween('created_at', [$prevStart, $prevEnd])
            ->whereNotIn('status', ['cancelled'])->count() ?: 1;
        $customersLastMonth = User::whereBetween('created_at', [$prevStart, $prevEnd])->count() ?: 1;

        // Totals (all time)
        $totalSales     = Order::whereNotIn('status', ['cancelled'])->sum('total_amount_inr');
        $totalOrders    = Order::whereNotIn('status', ['cancelled'])->count();
        $totalCustomers = User::whereNull('customer_group_id')->orWhereNotNull('id')->count();
        $avgOrderValue  = $totalOrders > 0 ? (int) ($totalSales / $totalOrders) : 0;

        $minYear = Order::min('created_at');
        $startYear = $minYear ? \Carbon\Carbon::parse($minYear)->year : now()->year;
        $endYear = now()->year;
        $years = range($endYear, min($startYear, $endYear));

        return response()->json([
            'total_sales'      => $totalSales,
            'total_orders'     => $totalOrders,
            'total_customers'  => $totalCustomers,
            'avg_order_value'  => $avgOrderValue,
            'sales_growth'     => $this->growthPct($salesThisMonth, $salesLastMonth),
            'orders_growth'    => $this->growthPct($ordersThisMonth, $ordersLastMonth),
            'customers_growth' => $this->growthPct($customersThisMonth, $customersLastMonth),
            'aov_growth'       => 0,
            'available_years'  => $years,
        ]);
    }

    /**
     * GET /api/v1/admin/dashboard/chart?period=monthly
     * Revenue + order count time series for Recharts LineChart
     */
    public function salesChart(Request $request): JsonResponse
    {
        $period = $request->get('period', 'monthly'); // weekly | monthly | yearly
        $year   = $request->get('year', now()->year);

        $data = match ($period) {
            'weekly' => $this->weeklyChart(),
            'yearly' => $this->yearlyChart(),
            default  => $this->monthlyChart((int) $year),
        };

        return response()->json($data);
    }

    /**
     * GET /api/v1/admin/dashboard/categories-chart
     * Category sales breakdown for Recharts Donut
     */
    public function categoryChart(): JsonResponse
    {
        $COLORS = ['#FDBA74', '#FB923C', '#F472B6', '#A78BFA', '#34D399', '#60A5FA'];

        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('products as p', 'p.id', '=', 'oi.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('categories as parent', 'parent.id', '=', 'c.parent_id')
            ->whereNotIn('o.status', ['cancelled'])
            ->groupBy(DB::raw('COALESCE(parent.id, c.id)'), DB::raw('COALESCE(parent.name, c.name)'))
            ->select(
                DB::raw('COALESCE(parent.name, c.name) as category_name'),
                DB::raw('SUM(oi.subtotal_inr) as total')
            )
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $grandTotal = $rows->sum('total') ?: 1;

        return response()->json(
            $rows->values()->map(fn ($r, $i) => [
                'name'  => $r->category_name,
                'value' => round($r->total / $grandTotal * 100, 1),
                'color' => $COLORS[$i % count($COLORS)],
            ])
        );
    }

    /**
     * GET /api/v1/admin/dashboard/low-stock
     */
    public function lowStock(): JsonResponse
    {
        $products = Product::active()
            ->lowStock()
            ->with('media')
            ->select('id', 'sku', 'name', 'stock_quantity', 'low_stock_threshold')
            ->orderBy('stock_quantity')
            ->limit(8)
            ->get()
            ->map(fn ($p) => [
                'id'             => $p->id,
                'sku'            => $p->sku,
                'name'           => $p->name,
                'stock_quantity' => $p->stock_quantity,
                'threshold'      => $p->low_stock_threshold,
                'image'          => $p->getFirstMediaUrl('gallery', 'thumb'),
            ]);

        return response()->json($products);
    }

    /**
     * GET /api/v1/admin/dashboard/recent-orders
     */
    public function recentOrders(): JsonResponse
    {
        $orders = Order::with(['customer', 'shippingAddress'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn ($o) => [
                'id'           => $o->id,
                'order_number' => $o->order_number,
                'customer'     => ['name' => $o->customer->name, 'email' => $o->customer->email],
                'total_inr'    => $o->total_amount_inr,
                'status'       => $o->status,
                'items'        => $o->items()->count(),
                'city'         => $o->shippingAddress?->city ?? '—',
                'created_at'   => $o->created_at->toDateString(),
            ]);

        return response()->json($orders);
    }

    // ── Private helpers ───────────────────────────────────────
    private function monthlyChart(int $year): array
    {
        $months = collect(range(1, 12))->map(fn ($m) => [
            'date'    => now()->setMonth($m)->format('M'),
            'revenue' => Order::whereMonth('created_at', $m)
                ->whereYear('created_at', $year)
                ->whereNotIn('status', ['cancelled'])
                ->sum('total_amount_inr'),
            'orders'  => Order::whereMonth('created_at', $m)
                ->whereYear('created_at', $year)
                ->whereNotIn('status', ['cancelled'])
                ->count(),
        ]);

        return $months->all();
    }

    private function weeklyChart(): array
    {
        return collect(range(6, 0))->map(fn ($d) => [
            'date'    => now()->subDays($d)->format('D'),
            'revenue' => Order::whereDate('created_at', now()->subDays($d))
                ->whereNotIn('status', ['cancelled'])->sum('total_amount_inr'),
            'orders'  => Order::whereDate('created_at', now()->subDays($d))
                ->whereNotIn('status', ['cancelled'])->count(),
        ])->values()->all();
    }

    private function yearlyChart(): array
    {
        return collect(range(4, 0))->map(fn ($y) => [
            'date'    => (string) now()->subYears($y)->year,
            'revenue' => Order::whereYear('created_at', now()->subYears($y)->year)
                ->whereNotIn('status', ['cancelled'])->sum('total_amount_inr'),
            'orders'  => Order::whereYear('created_at', now()->subYears($y)->year)
                ->whereNotIn('status', ['cancelled'])->count(),
        ])->values()->all();
    }

    private function growthPct(int|float $current, int|float $previous): float
    {
        if ($previous == 0) return 0;
        return round((($current - $previous) / $previous) * 100, 1);
    }
}
