<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customers = User::with('customerGroup')
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'Order Manager', 'Catalog Editor', 'Viewer']))
            ->when($request->search, fn ($q) => $q->search($request->search))
            ->when($request->group, fn ($q) => $q->whereHas('customerGroup', fn ($cg) => $cg->where('name', $request->group)))
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_dir ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => $customers->map(fn ($u) => $this->transform($u)),
            'meta' => [
                'total'        => $customers->total(),
                'current_page' => $customers->currentPage(),
                'last_page'    => $customers->lastPage(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('customerGroup', 'orders');
        return response()->json([
            ...$this->transform($user),
            'orders' => $user->orders()->latest()->limit(10)->get()->map(fn ($o) => [
                'id'           => $o->id,
                'order_number' => $o->order_number,
                'total_inr'    => $o->total_amount_inr,
                'status'       => $o->status,
                'created_at'   => $o->created_at->toDateString(),
            ]),
        ]);
    }

    public function updateGroup(Request $request, User $user): JsonResponse
    {
        $request->validate(['group' => 'required|exists:customer_groups,name']);
        $group = CustomerGroup::where('name', $request->group)->firstOrFail();
        $user->update(['customer_group_id' => $group->id]);
        return response()->json(['group' => $request->group, 'message' => 'Group updated.']);
    }

    public function suspend(User $user): JsonResponse
    {
        $user->update(['is_active' => !$user->is_active]);
        return response()->json(['is_active' => $user->fresh()->is_active]);
    }

    public function sendPasswordReset(User $user): JsonResponse
    {
        Password::sendResetLink(['email' => $user->email]);
        return response()->json(['message' => 'Password reset email sent.']);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $customers = User::with('customerGroup')
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'Order Manager', 'Catalog Editor', 'Viewer']))
            ->when($request->search, fn ($q) => $q->search($request->search))
            ->when($request->group && $request->group !== 'all', fn ($q) => $q->whereHas('customerGroup', fn ($cg) => $cg->where('name', $request->group)))
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_dir ?? 'desc')
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="customers-' . now()->format('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($customers) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM

            fputcsv($handle, [
                'Name', 'Email', 'Group', 'Total Spend (INR)',
                'Total Orders', 'Last Active', 'Joined Date',
            ]);

            foreach ($customers as $u) {
                fputcsv($handle, [
                    $u->name,
                    $u->email,
                    $u->customerGroup?->name ?? 'Regular',
                    '₹' . number_format($u->total_spend),
                    $u->total_orders,
                    $u->last_login_at?->toDateTimeString() ?? 'Never',
                    $u->created_at->toDateString(),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * GET /api/v1/admin/customers/export/pdf
     */
    public function exportPdf(Request $request)
    {
        $customers = User::with('customerGroup')
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'Order Manager', 'Catalog Editor', 'Viewer']))
            ->when($request->search, fn ($q) => $q->search($request->search))
            ->when($request->group && $request->group !== 'all', fn ($q) => $q->whereHas('customerGroup', fn ($cg) => $cg->where('name', $request->group)))
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_dir ?? 'desc')
            ->get();

        $pdf = Pdf::loadView('reports.customers', compact('customers'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('customers-' . now()->format('Y-m-d') . '.pdf');
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();
        return response()->json(['message' => 'Customer deleted successfully.']);
    }

    private function transform(User $u): array
    {
        return [
            'id'          => $u->id,
            'name'        => $u->name,
            'email'       => $u->email,
            'phone'       => $u->phone,
            'avatar'      => $u->initials,
            'group'       => $u->customerGroup?->name ?? 'Regular',
            'is_active'   => $u->is_active,
            'total_spend' => $u->total_spend,
            'total_orders'=> $u->total_orders,
            'avg_order_value' => $u->avg_order_value,
            'skin_type'   => $u->skin_type,
            'skin_concern'=> $u->skin_concern,
            'last_login'  => $u->last_login_at?->diffForHumans() ?? 'Never',
            'joined'      => $u->created_at->toDateString(),
        ];
    }
}
