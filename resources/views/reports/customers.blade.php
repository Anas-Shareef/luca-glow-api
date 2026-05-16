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
            background-color: #1e293b; 
            color: #ffffff; 
            font-weight: bold; 
            font-size: 11pt;
            text-align: left; 
            padding: 12px 8px; 
            border: 1px solid #334155;
            text-transform: uppercase;
        }
        tr:nth-child(even) { background-color: #fffaf5; }
        td { 
            padding: 10px 8px; 
            border: 1px solid #e2e8f0; 
            vertical-align: middle;
        }
        .name { font-weight: bold; color: #0f172a; }
        .email { font-size: 8pt; color: #64748b; }
        .amount { font-weight: bold; text-align: right; color: #7c3aed; }
        .badge { 
            padding: 4px 10px; 
            border-radius: 9999px; 
            font-size: 8pt; 
            font-weight: bold;
        }
        .group-vip { background-color: #fef3c7; color: #92400e; }
        .group-regular { background-color: #f1f5f9; color: #475569; }
        .group-wholesale { background-color: #f0f9ff; color: #0369a1; }
        .group-first-time { background-color: #ecfdf5; color: #065f46; }
        .footer { margin-top: 30px; font-size: 8pt; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Customers Database Export</div>
        <div class="subtitle">Generated on {{ now()->format('d/m/Y h:i A') }} · CRM Export</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Customer</th>
                <th>Group</th>
                <th>Total Spend</th>
                <th>Orders</th>
                <th>Last Active</th>
                <th>Joined</th>
            </tr>
        </thead>
        <tbody>
            @foreach($customers as $u)
                <tr>
                    <td>
                        <div class="name">{{ $u->name }}</div>
                        <div class="email">{{ $u->email }}</div>
                    </td>
                    <td>
                        @php $g = strtolower(str_replace(' ', '-', $u->customerGroup?->name ?? 'regular')); @endphp
                        <span class="badge group-{{ $g }}">
                            {{ $u->customerGroup?->name ?? 'Regular' }}
                        </span>
                    </td>
                    <td class="amount">Rs. {{ number_format($u->total_spend) }}</td>
                    <td style="text-align: center;">{{ $u->total_orders }}</td>
                    <td style="font-size: 9pt; color: #64748b;">
                        {{ $u->last_login_at?->format('d/m/Y h:i A') ?? 'Never' }}
                    </td>
                    <td>{{ $u->created_at->format('d/m/Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        © {{ date('Y') }} Luca Glow. All rights reserved.
    </div>
</body>
</html>
