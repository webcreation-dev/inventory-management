@extends('ecommerce::layouts.app')

@section('content')
    <section class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-emerald-200 bg-white p-10 text-center shadow-sm">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 13 4 4L19 7"/></svg>
            </div>
            <h1 class="mt-6 text-3xl font-bold text-slate-900">{{ __('Order Placed') }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ __('Thank you') }}{{ !empty($order['customer']['first_name']) ? ', '.$order['customer']['first_name'] : '' }}. {{ __('Your order has been received.') }}</p>
            <p class="mt-4 text-sm font-semibold text-brand-700">{{ __('Invoice') }}: {{ $order['invoice'] }}</p>
            @if(!empty($order['fulfillment_type']))
                <p class="mt-2 text-sm text-slate-600">
                    {{ __('Fulfillment') }}:
                    <span class="font-semibold text-slate-900">{{ $order['fulfillment_type'] === 'pickup' ? 'Pickup' : 'Delivery' }}</span>
                </p>
            @endif
            @if(!empty($order['outlet']['name']))
                <p class="mt-1 text-sm text-slate-600">{{ __('Branch') }}: <span class="font-semibold text-slate-900">{{ $order['outlet']['name'] }}</span></p>
            @endif
            @if(!empty($order['payment_method']))
                <p class="mt-1 text-sm text-slate-600">{{ __('Payment') }}: <span class="font-semibold text-slate-900">{{ $order['payment_method'] }}</span></p>
            @endif
            <div class="mt-8 space-y-2 text-left text-sm text-slate-600">
                @foreach($order['items'] as $item)
                    <div class="flex justify-between border-b border-slate-100 py-2">
                        <span>{{ $item['name'] }} × {{ $item['qty'] }}</span>
                        <span class="font-medium text-slate-900">${{ number_format($item['price'] * $item['qty'], 2) }}</span>
                    </div>
                @endforeach
                <div class="flex justify-between pt-2"><span>{{ __('Subtotal') }}</span><span>${{ number_format($order['subtotal'] ?? 0, 2) }}</span></div>
                <div class="flex justify-between"><span>{{ __('Tax') }}</span><span>${{ number_format($order['tax'] ?? 0, 2) }}</span></div>
                <div class="flex justify-between"><span>{{ __('Shipping') }}</span><span>${{ number_format($order['shipping'] ?? 0, 2) }}</span></div>
                <div class="flex justify-between pt-3 text-base font-bold text-slate-900">
                    <span>{{ __('Total') }}</span>
                    <span>${{ number_format($order['total'], 2) }}</span>
                </div>
            </div>
            <a href="{{ route('ecommerce.home') }}" class="ec-btn ec-btn-primary mt-8">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1v-10.5Z"/></svg>
                {{ __('Back to Home') }}
            </a>
        </div>
    </section>
@endsection
