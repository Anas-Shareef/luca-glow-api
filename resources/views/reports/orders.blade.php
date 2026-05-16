<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        body { font-family: sans-serif; font-size: 10pt; color: #1e293b; }
        .header { margin-bottom: 20px; }
        .title { font-size: 18pt; font-weight: bold; color: #7c3aed; }
        .subtitle { color: #64748b; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { 
            background-color: #f8fafc; 
            color: #475569; 
            font-weight: bold; 
            font-size: 11pt;
            text-align: left; 
            padding: 12px 8px; 
            border: 1px solid #e2e8f0;
            text-transform: uppercase;
        }
        td { 
            padding: 10px 8px; 
            border: 1px solid #e2e8f0; 
            vertical-align: middle;
        }
        .order-id { font-family: monospace; font-weight: bold; color: #7c3aed; }
        .amount { font-weight: bold; text-align: right; }
        .status { 
            padding: 4px 8px; 
            border-radius: 9999px; 
            font-size: 8pt; 
            font-weight: bold; 
            text-transform: uppercase;
        }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-processing { background-color: #ffedd5; color: #9a3412; }
        .status-shipped { background-color: #dbeafe; color: #1e40af; }
        .status-delivered { background-color: #d1fae5; color: #065f46; }
        .status-cancelled { background-color: #fee2e2; color: #991b1b; }
        .footer { margin-top: 30px; font-size: 8pt; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Orders Report</div>
        <div class="subtitle">Generated on {{ now()->format('d/m/Y h:i A') }} · Luca Glow Admin</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>City</th>
                <th>Total</th>
                <th>Items</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orders as $o)
                <tr>
                    <td class="order-id">{{ $o->order_number }}</td>
                    <td>
                        <div style="font-weight: bold;">{{ $o->customer?->name ?? 'Guest' }}</div>
                        <div style="font-size: 8pt; color: #64748b;">{{ $o->customer?->email }}</div>
                    </td>
                    <td>{{ $o->shippingAddress?->city ?? '—' }}</td>
                    <td class="amount">Rs. {{ number_format($o->total_amount_inr) }}</td>
                    <td>{{ $o->items->count() }} items</td>
                    <td>
                        <span class="status status-{{ $o->status }}">{{ $o->status }}</span>
                    </td>
                    <td>{{ $o->created_at->format('d/m/Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        © {{ date('Y') }} Luca Glow. All rights reserved.
    </div>
</body>
</html>
