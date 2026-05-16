<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 13px; color: #1e293b; background: #fff; }

  .page { padding: 40px 48px; }

  /* Header */
  .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #FDBA74; padding-bottom: 24px; margin-bottom: 28px; }
  .brand-name { font-size: 26px; font-weight: 700; color: #7c2d12; letter-spacing: -0.5px; }
  .brand-sub  { font-size: 11px; color: #f97316; font-weight: 600; text-transform: uppercase; letter-spacing: 1.5px; margin-top: 4px; }
  .invoice-meta { text-align: right; }
  .invoice-title { font-size: 22px; font-weight: 700; color: #FDBA74; }
  .invoice-num   { font-size: 14px; font-weight: 600; color: #334155; margin-top: 4px; }
  .invoice-date  { font-size: 11px; color: #94a3b8; margin-top: 2px; }

  /* Info grid */
  .info-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; margin-bottom: 28px; }
  .info-box h4 { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 8px; }
  .info-box p  { font-size: 12px; color: #334155; line-height: 1.6; }

  /* Status badge */
  .status-badge { display: inline-block; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
  .status-delivered  { background: #d1fae5; color: #065f46; }
  .status-shipped    { background: #dbeafe; color: #1e40af; }
  .status-processing { background: #ffedd5; color: #9a3412; }
  .status-pending    { background: #fef9c3; color: #854d0e; }
  .status-cancelled  { background: #fee2e2; color: #991b1b; }

  /* Items table */
  .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  .items-table thead tr { background: #fff7ed; }
  .items-table thead th { padding: 10px 12px; text-align: left; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #92400e; border-bottom: 2px solid #FDBA74; }
  .items-table thead th.right { text-align: right; }
  .items-table tbody tr { border-bottom: 1px solid #f1f5f9; }
  .items-table tbody tr:hover { background: #fafafa; }
  .items-table td { padding: 10px 12px; font-size: 12px; color: #334155; vertical-align: top; }
  .items-table td.right { text-align: right; }
  .product-name { font-weight: 600; }
  .product-sku  { font-size: 10px; color: #94a3b8; font-family: monospace; margin-top: 2px; }

  /* Totals */
  .totals { display: flex; justify-content: flex-end; margin-top: 12px; }
  .totals-table { width: 280px; }
  .totals-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 12px; color: #475569; }
  .totals-row.total { padding: 10px 12px; background: #fff7ed; border-radius: 8px; margin-top: 6px; }
  .totals-row.total .label { font-weight: 700; color: #7c2d12; font-size: 14px; }
  .totals-row.total .value { font-weight: 700; color: #7c2d12; font-size: 14px; }

  /* GST note */
  .gst-note { font-size: 10px; color: #94a3b8; margin-top: 4px; text-align: right; }

  /* Footer */
  .footer { margin-top: 40px; padding-top: 16px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
  .footer-brand { font-size: 12px; font-weight: 600; color: #f97316; }
  .footer-text  { font-size: 11px; color: #94a3b8; }
  .footer-gst   { font-size: 11px; color: #64748b; }

  /* Stamp */
  .paid-stamp { display: inline-block; border: 3px solid #10b981; color: #10b981; padding: 4px 14px; border-radius: 4px; font-size: 18px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; transform: rotate(-8deg); opacity: 0.7; }
</style>
</head>
<body>
<div class="page">

  <!-- Header -->
  <div class="header">
    <div>
      <div class="brand-name">✦ {{ $storeName }}</div>
      <div class="brand-sub">Feel The Change · Clean Beauty</div>
    </div>
    <div class="invoice-meta">
      <div class="invoice-title">INVOICE</div>
      <div class="invoice-num">{{ $order->order_number }}</div>
      <div class="invoice-date">{{ $order->created_at->format('d M Y') }}</div>
      <div style="margin-top:8px">
        <span class="status-badge status-{{ $order->status }}">{{ ucfirst($order->status) }}</span>
      </div>
    </div>
  </div>

  <!-- Info Grid -->
  <div class="info-grid">
    <div class="info-box">
      <h4>Billed By</h4>
      <p>
        <strong>{{ $storeName }}</strong><br>
        Kozhikode, Kerala<br>
        India — 673001<br>
        hello@lucaglow.com
      </p>
    </div>
    <div class="info-box">
      <h4>Billed To</h4>
      <p>
        <strong>{{ $order->customer->name }}</strong><br>
        {{ $order->customer->email }}<br>
        {{ $order->customer->phone }}<br>
        @if($order->billingAddress)
          {{ $order->billingAddress->address_line_1 }}<br>
          {{ $order->billingAddress->city }}, {{ $order->billingAddress->state }}<br>
          {{ $order->billingAddress->pincode }}
        @endif
      </p>
    </div>
    <div class="info-box">
      <h4>Ship To</h4>
      <p>
        @if($order->shippingAddress)
          {{ $order->shippingAddress->full_name }}<br>
          {{ $order->shippingAddress->address_line_1 }}
          @if($order->shippingAddress->address_line_2)
            , {{ $order->shippingAddress->address_line_2 }}
          @endif<br>
          {{ $order->shippingAddress->city }}, {{ $order->shippingAddress->state }}<br>
          {{ $order->shippingAddress->pincode }}<br>
          Ph: {{ $order->shippingAddress->phone }}
        @endif
      </p>
    </div>
  </div>

  <!-- Items Table -->
  <table class="items-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Product</th>
        <th class="right">Unit Price</th>
        <th class="right">Qty</th>
        <th class="right">Amount</th>
      </tr>
    </thead>
    <tbody>
      @foreach($order->items as $i => $item)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>
          <div class="product-name">{{ $item->product_name }}</div>
          <div class="product-sku">{{ $item->product_sku }}{{ $item->variant_info ? ' · ' . $item->variant_info : '' }}</div>
        </td>
        <td class="right">₹{{ number_format($item->unit_price_inr, 0) }}</td>
        <td class="right">{{ $item->quantity }}</td>
        <td class="right"><strong>₹{{ number_format($item->subtotal_inr, 0) }}</strong></td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <!-- Totals -->
  <div class="totals">
    <div class="totals-table">
      <div class="totals-row">
        <span class="label">Subtotal</span>
        <span class="value">₹{{ number_format($order->subtotal_inr, 0) }}</span>
      </div>
      <div class="totals-row">
        <span class="label">Shipping</span>
        <span class="value">₹{{ number_format($order->shipping_amount_inr, 0) }}</span>
      </div>
      @if($order->discount_amount_inr > 0)
      <div class="totals-row" style="color:#10b981">
        <span class="label">Discount {{ $order->coupon_code ? "({$order->coupon_code})" : '' }}</span>
        <span class="value">- ₹{{ number_format($order->discount_amount_inr, 0) }}</span>
      </div>
      @endif
      <div class="totals-row total">
        <span class="label">TOTAL</span>
        <span class="value">₹{{ number_format($order->total_amount_inr, 0) }}</span>
      </div>
    </div>
  </div>

  <!-- Payment status stamp -->
  @if($order->payment_status === 'paid')
  <div style="text-align:right; margin-top:16px">
    <div class="paid-stamp">Paid</div>
  </div>
  @endif

  <!-- Footer -->
  <div class="footer">
    <div>
      <div class="footer-brand">{{ $storeName }} · lucaglow.com</div>
      <div class="footer-text">Thank you for your order! Questions? hello@lucaglow.com</div>
    </div>
    <div style="text-align:right">
      <div class="footer-text">Payment: {{ ucfirst($order->payment_method) }}</div>
    </div>
  </div>

</div>
</body>
</html>
