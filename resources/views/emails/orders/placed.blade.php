@component('mail::message')
# Order Confirmation 🌸

Hi {{ $order->customer->first_name ?? 'there' }},

Thank you for shopping at **Luca Glow**! 
Your order **{{ $order->order_number }}** has been successfully placed. We will notify you as soon as it ships.

@component('mail::table')
| Item | Quantity | Price |
|:-----|:--------:|------:|
@foreach($order->items as $item)
| {{ $item->product_name }} | {{ $item->quantity }} | ₹{{ number_format($item->unit_price_inr, 2) }} |
@endforeach
@endcomponent

**Subtotal:** ₹{{ number_format($order->subtotal_inr, 2) }}  
**Shipping:** ₹{{ number_format($order->shipping_amount_inr, 2) }}  
@if($order->discount_amount_inr > 0)
**Discount:** -₹{{ number_format($order->discount_amount_inr, 2) }}  
@endif
**Total:** **₹{{ number_format($order->total_amount_inr, 2) }}**

@component('mail::button', ['url' => env('FRONTEND_URL', 'https://lucaworld.vercel.app') . '/account'])
View Your Order
@endcomponent

With glowing love,  
**The Luca Glow Team**
@endcomponent
