@extends('ecommerce::layouts.app')

@section('content')
    @php
        $money = function ($amount) {
            return function_exists('formatAmount') ? formatAmount($amount) : ('$' . number_format((float) $amount, 2));
        };
        $productImage = function ($product) {
            return !empty($product['image_is_url'])
                ? $product['image']
                : route('ecommerce.image', ['file' => $product['image'] ?? 'banner.png']);
        };
    @endphp
    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="ec-kicker">{{ __('Saved') }}</p>
                <h1 class="ec-title text-3xl">{{ __('Wishlist') }}</h1>
                <p class="ec-subtitle">{{ __('Your saved products for future purchase') }}</p>
            </div>
            <a href="{{ route('ecommerce.home') }}" class="ec-link-more">{{ __('Continue Shopping') }} →</a>
        </div>

        <div class="grid items-start gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @forelse($wishlistItems as $item)
                @include('ecommerce::partials.product-card', [
                    'product' => $item,
                    'money' => $money,
                    'productImage' => $productImage,
                    'showWishlistIcon' => false,
                    'showRemoveBesideCart' => true,
                ])
            @empty
                <div class="ec-panel border-dashed p-10 text-center sm:col-span-2 lg:col-span-4">
                    <p class="text-sm text-slate-500">{{ __('Your wishlist is empty') }}</p>
                    <a href="{{ route('ecommerce.category', ['slug' => 'all']) }}" class="ec-btn ec-btn-primary mt-4">{{ __('Browse Products') }}</a>
                </div>
            @endforelse
        </div>
    </section>
@endsection
