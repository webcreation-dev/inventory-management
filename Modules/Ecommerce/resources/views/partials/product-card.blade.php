@php
    $money = $money ?? function ($amount) {
        return function_exists('formatAmount') ? formatAmount($amount) : ('$' . number_format((float) $amount, 2));
    };
    $productImage = $productImage ?? function ($product) {
        return !empty($product['image_is_url'])
            ? $product['image']
            : route('ecommerce.image', ['file' => $product['image']]);
    };
    $badge = $badge ?? null;
    $badgeClass = $badgeClass ?? 'ec-badge-sale';
    $badgeIcons = [
        'ec-badge-sale' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01M14.5 13.5h.01M7 21l-4-4 9.5-9.5a2.1 2.1 0 0 1 3 0l1 1a2.1 2.1 0 0 1 0 3L7 21Z"/>',
        'ec-badge-new' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v3m0 12v3M3 12h3m12 0h3M6.3 6.3l2.1 2.1m7.2 7.2 2.1 2.1m0-11.4-2.1 2.1M8.4 15.6l-2.1 2.1"/>',
        'ec-badge-hot' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3c2.5 3 4 5.2 4 8a4 4 0 1 1-8 0c0-2.8 1.5-5 4-8Zm0 18a6.5 6.5 0 0 0 6.5-6.5c0-1.2-.4-2.3-1-3.3"/>',
        'ec-badge-offer' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v8m-4-4h8M7 4h10l3 7-8 9-8-9 3-7Z"/>',
    ];
    $badgeIcon = $badgeIcons[$badgeClass] ?? $badgeIcons['ec-badge-sale'];
    $wishlistIds = $wishlistIds ?? [];
    $inWishlist = in_array((int) ($product['id'] ?? 0), array_map('intval', (array) $wishlistIds), true);
    $showWishlistIcon = $showWishlistIcon ?? true;
    $showRemoveBesideCart = $showRemoveBesideCart ?? false;
    $isVariation = ! empty($product['is_variation']) || (($product['type'] ?? '') === 'Variation_Product');
@endphp
<article class="ec-card group">
    <div class="ec-product-media">
        <a href="{{ route('ecommerce.item-details', ['id' => $product['encrypted_id'] ?? encrypt($product['id'])]) }}">
            <img src="{{ $productImage($product) }}" alt="{{ $product['name'] }}" loading="lazy">
        </a>
        @if($showWishlistIcon)
            <button type="button"
                    class="ec-wish-btn {{ $inWishlist ? 'is-active' : '' }}"
                    data-wishlist-toggle
                    data-product-id="{{ $product['id'] }}"
                    data-in-wishlist="{{ $inWishlist ? '1' : '0' }}"
                    aria-label="{{ $inWishlist ? __('Remove from wishlist') : __('Add to wishlist') }}">
                <svg viewBox="0 0 24 24" fill="{{ $inWishlist ? 'currentColor' : 'none' }}" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" d="m21.752 7.516c0 4.653-4.807 8.19-9.033 12.068a1.1 1.1 0 0 1-1.438 0C7.056 15.706 2.248 12.169 2.248 7.516A5.392 5.392 0 0 1 7.64 2.124c1.46 0 2.86.595 3.893 1.649a5.47 5.47 0 0 1 3.894-1.649 5.392 5.392 0 0 1 5.325 5.392Z"/>
                </svg>
            </button>
        @endif
        @if($isVariation)
            <span class="ec-badge ec-badge-variation">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h10M4 17h7"/>
                </svg>
                {{ __('Variation') }}
            </span>
        @elseif($badge)
            <span class="ec-badge {{ $badgeClass }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">{!! $badgeIcon !!}</svg>
                {{ $badge }}
            </span>
        @elseif(!empty($product['discount']))
            <span class="ec-badge ec-badge-sale">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">{!! $badgeIcons['ec-badge-sale'] !!}</svg>
                -{{ $product['discount'] }}%
            </span>
        @endif
        @if(!$isVariation && $badge && !empty($product['discount']) && $badgeClass !== 'ec-badge-sale')
            <span class="ec-badge ec-badge-sale ec-badge-right">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">{!! $badgeIcons['ec-badge-sale'] !!}</svg>
                -{{ $product['discount'] }}%
            </span>
        @endif
    </div>
    <div class="ec-product-body">
        <h3 class="ec-product-name">
            <a href="{{ route('ecommerce.item-details', ['id' => $product['encrypted_id'] ?? encrypt($product['id'])]) }}" class="hover:text-[var(--accent-strong)]">{{ $product['name'] }}</a>
        </h3>
        <button type="button"
                class="ec-card-rating"
                data-rate-product
                data-product-id="{{ $product['id'] }}"
                aria-label="{{ __('Rate this product') }}">
            <span class="ec-card-rating__stars" aria-hidden="true">
                @php $cardRating = (float) ($product['rating'] ?? 0); @endphp
                @for($i = 1; $i <= 5; $i++)
                    <svg class="{{ $cardRating >= ($i - 0.25) ? 'is-on' : ($cardRating >= ($i - 0.75) ? 'is-half' : '') }}" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3.4 2.5 5.1 5.6.8-4 3.9.9 5.6L12 16.2l-5 2.6.9-5.6-4-3.9 5.6-.8L12 3.4Z"/></svg>
                @endfor
            </span>
            <span class="ec-card-rating__meta">
                <strong>{{ number_format((float) ($product['rating'] ?? 0), 1) }}</strong>
                <span>({{ (int) ($product['reviews'] ?? 0) }})</span>
            </span>
        </button>
        <div class="ec-price-row">
            @if($isVariation)
                <span class="inline-flex items-center rounded-full border border-[rgba(var(--accent-rgb),0.28)] bg-[rgba(var(--accent-rgb),0.12)] px-2.5 py-1 text-[0.7rem] font-extrabold uppercase tracking-wide text-[var(--accent-strong)]">
                    {{ __('Variation') }}
                </span>
            @else
                <span class="ec-price">{{ $money($product['price']) }}</span>
                @if(($product['old_price'] ?? 0) > ($product['price'] ?? 0))
                    <span class="ec-price-old">{{ $money($product['old_price']) }}</span>
                @endif
            @endif
        </div>
        @if($isVariation)
            <a href="{{ route('ecommerce.item-details', ['id' => $product['encrypted_id'] ?? encrypt($product['id'])]) }}" class="ec-btn ec-btn-primary ec-btn-block mt-3">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.5 12S6.5 5.5 12 5.5 21.5 12 21.5 12 17.5 18.5 12 18.5 2.5 12 2.5 12Z"/>
                </svg>
                {{ __('Details') }}
            </a>
        @elseif($showRemoveBesideCart)
            <div class="mt-3 flex items-center gap-2">
                <button type="button"
                        data-add-to-cart
                        data-product-id="{{ $product['id'] }}"
                        data-price="{{ $product['price'] }}"
                        class="ec-btn ec-btn-primary flex-1">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l1.6 9.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.5L21 6H7"/>
                        <circle cx="9" cy="19" r="1.2"/>
                        <circle cx="18" cy="19" r="1.2"/>
                    </svg>
                    {{ __('Add to Cart') }}
                </button>
                <form method="POST" action="{{ route('ecommerce.wishlist.remove') }}" class="shrink-0">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product['id'] }}">
                    <button type="submit" class="ec-btn ec-btn-ghost !px-3 !text-rose-600 hover:!border-rose-200 hover:!bg-rose-50" title="{{ __('Remove') }}" aria-label="{{ __('Remove') }}">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 6 12 12M18 6 6 18"/></svg>
                    </button>
                </form>
            </div>
        @else
            <button type="button"
                    data-add-to-cart
                    data-product-id="{{ $product['id'] }}"
                    data-price="{{ $product['price'] }}"
                    class="ec-btn ec-btn-primary ec-btn-block mt-3">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l1.6 9.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.5L21 6H7"/>
                    <circle cx="9" cy="19" r="1.2"/>
                    <circle cx="18" cy="19" r="1.2"/>
                </svg>
                {{ __('Add to Cart') }}
            </button>
        @endif
    </div>
</article>
