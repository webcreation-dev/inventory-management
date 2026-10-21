@extends('ecommerce::layouts.app')

@section('content')
    @php
        $mainImage = !empty($product['image_is_url'])
            ? $product['image']
            : route('ecommerce.image', ['file' => $product['image']]);
        $galleryImages = !empty($product['gallery']) && is_array($product['gallery'])
            ? array_values(array_unique(array_filter($product['gallery'])))
            : [$mainImage];
        if (empty($galleryImages)) {
            $galleryImages = [$mainImage];
        }
        $money = function ($amount) {
            return function_exists('formatAmount') ? formatAmount($amount) : ('$' . number_format((float) $amount, 2));
        };
        $productImage = function ($product) {
            return !empty($product['image_is_url'])
                ? $product['image']
                : route('ecommerce.image', ['file' => $product['image']]);
        };
        $inWishlist = $inWishlist ?? false;
        $isVariation = ! empty($product['is_variation']);
        $variations = $variations ?? [];
        $simpleInStock = ! $isVariation && ! empty($product['in_stock']);
        $availabilityLabel = $isVariation
            ? ''
            : ($simpleInStock ? __('In Stock') : __('Out Stock'));
        $availabilityClass = $isVariation
            ? 'text-slate-500'
            : ($simpleInStock ? 'text-emerald-600' : 'text-rose-600');
        $ratingPanel = $ratingPanel ?? [
            'average' => (float) ($product['rating'] ?? 0),
            'total' => (int) ($product['reviews'] ?? 0),
            'recommend_count' => 0,
            'recommend_percent' => 0,
            'snapshot' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
            'reviews' => [],
            'can_rate' => false,
            'has_purchased' => false,
            'my_rating' => null,
        ];
        $breadcrumbs = $breadcrumbs ?? [];
        $avgRating = (float) ($ratingPanel['average'] ?? 0);
        $reviewTotal = (int) ($ratingPanel['total'] ?? 0);
    @endphp
    <section class="mx-auto max-w-7xl px-4 pt-8 sm:px-6 lg:px-8">
        @if(!empty($breadcrumbs))
            <nav class="ec-product-crumbs" aria-label="{{ __('Breadcrumb') }}">
                <ol>
                    @foreach($breadcrumbs as $index => $crumb)
                        <li>
                            @if(!empty($crumb['url']) && empty($crumb['active']))
                                <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
                            @else
                                <span aria-current="page">{{ $crumb['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
    </section>
    <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid items-start gap-10 lg:grid-cols-2">
            <div class="ec-panel h-fit self-start p-5 sm:p-6">
                <div class="overflow-hidden rounded-2xl">
                    <img id="mainProductImage" src="{{ $galleryImages[0] }}" alt="{{ $product['name'] }}" class="h-[28rem] w-full object-contain p-4">
                </div>
                <div id="thumbSliderWrap" class="ec-thumb-slider-wrap mt-4 {{ count($galleryImages) > 1 ? '' : 'hidden' }}">
                    <button id="thumbPrev" type="button" class="ec-icon-btn !h-9 !w-9 shrink-0">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7"/></svg>
                    </button>
                    <div id="thumbSlider" class="ec-thumb-slider">
                        @foreach($galleryImages as $index => $image)
                            <button
                                type="button"
                                class="thumb-item {{ $index === 0 ? 'is-active' : '' }}"
                                data-image="{{ $image }}"
                            >
                                <img src="{{ $image }}" alt="thumb-{{ $index + 1 }}">
                            </button>
                        @endforeach
                    </div>
                    <button id="thumbNext" type="button" class="ec-icon-btn !h-9 !w-9 shrink-0">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
            <div class="ec-reveal">
                <h1 class="ec-title text-3xl sm:text-4xl">{{ $product['name'] }}</h1>

                @if($isVariation)
                    <div class="mt-5">
                        <p class="text-sm font-bold text-slate-900">{{ __('Choose variation') }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Change option to update price and availability.') }}</p>
                        @if(empty($variations))
                            <p class="mt-4 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ __('No variations are available for this product yet.') }}</p>
                        @else
                            <div class="ec-variation-list mt-4" id="variationOptions" data-parent-name="{{ $product['name'] }}">
                                @foreach($variations as $index => $variation)
                                    <label class="ec-variation-option {{ empty($variation['in_stock']) ? 'is-oos' : '' }} {{ $index === 0 ? 'is-selected' : '' }}" data-variation-option>
                                        <input type="radio"
                                               name="variation_id"
                                               value="{{ $variation['id'] }}"
                                               data-price="{{ $variation['price'] }}"
                                               data-old-price="{{ $variation['old_price'] }}"
                                               data-label="{{ $variation['label'] }}"
                                               data-cart-name="{{ $variation['cart_name'] ?? '' }}"
                                               data-image="{{ $variation['image'] ?? '' }}"
                                               data-gallery='@json($variation['gallery'] ?? array_values(array_filter([$variation['image'] ?? null])))'
                                               data-stock="{{ $variation['stock'] ?? 0 }}"
                                               data-in-stock="{{ !empty($variation['in_stock']) ? '1' : '0' }}"
                                               {{ $index === 0 ? 'checked' : '' }}>
                                        <span class="ec-variation-option-body">
                                            <span class="ec-variation-option-label">{{ $variation['label'] }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                <button type="button"
                        class="ec-inline-rating mt-3"
                        data-rate-product
                        data-product-id="{{ $product['id'] }}"
                        id="productInlineRating">
                    <span class="ec-inline-rating__stars" aria-hidden="true">
                        @for($i = 1; $i <= 5; $i++)
                            <svg class="{{ $avgRating >= ($i - 0.25) ? 'is-on' : '' }}" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3.4 2.5 5.1 5.6.8-4 3.9.9 5.6L12 16.2l-5 2.6.9-5.6-4-3.9 5.6-.8L12 3.4Z"/></svg>
                        @endfor
                    </span>
                    <span class="ec-inline-rating__score" data-rating-average>{{ number_format($avgRating, 1) }}</span>
                    <span class="ec-inline-rating__count">(<span data-rating-total>{{ $reviewTotal }}</span> {{ __('Reviews') }})</span>
                    @if($isVariation)
                        <span class="ml-1 inline-flex items-center rounded-full border border-[rgba(var(--accent-rgb),0.28)] bg-[rgba(var(--accent-rgb),0.12)] px-2.5 py-1 text-[0.7rem] font-extrabold uppercase tracking-wide text-[var(--accent-strong)]">
                            {{ __('Variation') }}
                        </span>
                    @endif
                </button>

                @if($isVariation)
                    <div class="mt-4 flex items-baseline gap-3" id="variationPriceWrap">
                        <p id="variationPrice" class="ec-display text-3xl font-bold text-[var(--accent-strong)]"></p>
                        <p id="variationOldPrice" class="hidden text-base text-slate-400 line-through"></p>
                    </div>
                @else
                    <div class="mt-4 flex items-baseline gap-3">
                        <p class="ec-display text-3xl font-bold text-[var(--accent-strong)]">{{ $money($product['price']) }}</p>
                        @if(($product['old_price'] ?? 0) > ($product['price'] ?? 0))
                            <p class="text-base text-slate-400 line-through">{{ $money($product['old_price']) }}</p>
                        @endif
                    </div>
                @endif

                
                <div class="mt-6 grid grid-cols-2 gap-4 text-sm">
                    <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                        <p class="font-bold text-slate-900">{{ __('Availability') }}</p>
                        <p id="productAvailability" class="mt-1 font-semibold {{ $availabilityClass }}">{{ $availabilityLabel }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                        <p class="font-bold text-slate-900">{{ __('Shipping') }}</p>
                        <p class="mt-1 text-slate-600">{{ __('2-4 business days') }}</p>
                    </div>
                </div>
                @if(!empty($product['spec']))
                <div class="mt-4 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm">
                    <p class="font-bold text-slate-900">{{ __('Key Specs') }}</p>
                    <p class="mt-1 text-slate-600">{{ $product['spec'] }}</p>
                </div>
                @endif

                @if($isVariation)
                    <div class="mt-7 flex flex-wrap gap-3">
                        <button type="button"
                                id="variationAddToCart"
                                class="ec-btn ec-btn-ghost"
                                disabled>
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l1.6 9.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.5L21 6H7"/>
                                <circle cx="9" cy="19" r="1.2"/>
                                <circle cx="18" cy="19" r="1.2"/>
                            </svg>
                            {{ __('Add to Cart') }}
                        </button>
                        <button type="button"
                                id="variationBuyNow"
                                class="ec-btn ec-btn-primary"
                                disabled>
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            {{ __('Buy Now') }}
                        </button>
                        <button type="button"
                                id="wishlistToggleBtn"
                                data-wishlist-toggle
                                data-product-id="{{ $product['id'] }}"
                                data-in-wishlist="{{ $inWishlist ? '1' : '0' }}"
                                class="ec-btn ec-btn-ghost {{ $inWishlist ? 'is-wishlisted' : '' }}">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="{{ $inWishlist ? 'currentColor' : 'none' }}" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21.752 7.516c0 4.653-4.807 8.19-9.033 12.068a1.1 1.1 0 0 1-1.438 0C7.056 15.706 2.248 12.169 2.248 7.516A5.392 5.392 0 0 1 7.64 2.124c1.46 0 2.86.595 3.893 1.649a5.47 5.47 0 0 1 3.894-1.649 5.392 5.392 0 0 1 5.325 5.392Z"/>
                            </svg>
                            <span data-wishlist-label>{{ $inWishlist ? __('Wishlisted') : __('Add to Wishlist') }}</span>
                        </button>
                    </div>
                @else
                    <div class="mt-7 flex flex-wrap gap-3">
                        <button type="button"
                                data-add-to-cart
                                data-product-id="{{ $product['id'] }}"
                                data-price="{{ $product['price'] }}"
                                class="ec-btn ec-btn-ghost"
                                @disabled(! $simpleInStock)>
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l1.6 9.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.5L21 6H7"/>
                                <circle cx="9" cy="19" r="1.2"/>
                                <circle cx="18" cy="19" r="1.2"/>
                            </svg>
                            {{ __('Add to Cart') }}
                        </button>
                        <button type="button"
                                data-add-to-cart
                                data-buy-now
                                data-product-id="{{ $product['id'] }}"
                                data-price="{{ $product['price'] }}"
                                class="ec-btn ec-btn-primary"
                                @disabled(! $simpleInStock)>
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            {{ __('Buy Now') }}
                        </button>
                        <button type="button"
                                id="wishlistToggleBtn"
                                data-wishlist-toggle
                                data-product-id="{{ $product['id'] }}"
                                data-in-wishlist="{{ $inWishlist ? '1' : '0' }}"
                                class="ec-btn ec-btn-ghost {{ $inWishlist ? 'is-wishlisted' : '' }}">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="{{ $inWishlist ? 'currentColor' : 'none' }}" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21.752 7.516c0 4.653-4.807 8.19-9.033 12.068a1.1 1.1 0 0 1-1.438 0C7.056 15.706 2.248 12.169 2.248 7.516A5.392 5.392 0 0 1 7.64 2.124c1.46 0 2.86.595 3.893 1.649a5.47 5.47 0 0 1 3.894-1.649 5.392 5.392 0 0 1 5.325 5.392Z"/>
                            </svg>
                            <span data-wishlist-label>{{ $inWishlist ? __('Wishlisted') : __('Add to Wishlist') }}</span>
                        </button>
                    </div>
                @endif
                <p class="mt-5 text-sm leading-7 text-slate-600">
                    {!! !empty($product['description']) ? $product['description'] : e(__('Premium quality product with modern design, excellent durability, and customer-focused finishing.')) !!}
                </p>
            </div>
        </div>
    </section>

    <section class="ec-section mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" id="productReviews">
        <div class="ec-rating-panel" data-rating-panel>
            <div class="ec-rating-panel__summary">
                <div class="ec-rating-panel__score">
                    <p class="ec-rating-panel__average" data-rating-average>{{ number_format($avgRating, 1) }}</p>
                    <div class="ec-rating-panel__stars" aria-hidden="true">
                        @for($i = 1; $i <= 5; $i++)
                            <svg class="{{ $avgRating >= ($i - 0.25) ? 'is-on' : '' }}" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3.4 2.5 5.1 5.6.8-4 3.9.9 5.6L12 16.2l-5 2.6.9-5.6-4-3.9 5.6-.8L12 3.4Z"/></svg>
                        @endfor
                    </div>
                    <p class="ec-rating-panel__count">
                        <span data-rating-total>{{ $reviewTotal }}</span> {{ __('Reviews') }}
                    </p>
                    <p class="ec-rating-panel__verified">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3 4.5 6.2v5.3c0 4.4 3 8.4 7.5 9.5 4.5-1.1 7.5-5.1 7.5-9.5V6.2L12 3Z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9.2 12.1 1.9 1.9 3.7-3.8"/></svg>
                        {{ __('Verified by Shop') }}
                    </p>
                    <p class="ec-rating-panel__recommend" data-rating-recommend>
                        @if($reviewTotal > 0)
                            {{ __(':count out of :total (:percent%) reviewers recommend this product', [
                                'count' => $ratingPanel['recommend_count'],
                                'total' => $reviewTotal,
                                'percent' => $ratingPanel['recommend_percent'],
                            ]) }}
                        @else
                            {{ __('No reviews yet. Be the first to rate this product') }}
                        @endif
                    </p>
                    <button type="button"
                            class="ec-btn ec-btn-primary mt-4"
                            data-rate-product
                            data-product-id="{{ $product['id'] }}">
                        {{ !empty($ratingPanel['my_rating']) ? __('Update Your Rating') : __('Write a Review') }}
                    </button>
                </div>
                <div class="ec-rating-panel__snapshot">
                    <h3 class="ec-rating-panel__snapshot-title">{{ __('Rating snapshot') }}</h3>
                    <ul class="ec-rating-bars">
                        @foreach([5,4,3,2,1] as $star)
                            @php
                                $count = (int) ($ratingPanel['snapshot'][$star] ?? 0);
                                $pct = $reviewTotal > 0 ? round(($count / $reviewTotal) * 100) : 0;
                            @endphp
                            <li>
                                <span class="ec-rating-bars__label">{{ $star }} {{ __('stars') }}</span>
                                <span class="ec-rating-bars__track"><span style="width: {{ $pct }}%"></span></span>
                                <span class="ec-rating-bars__count" data-star-count="{{ $star }}">{{ $count }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="ec-rating-list mt-8" data-rating-list>
                @forelse($ratingPanel['reviews'] as $review)
                    <article class="ec-rating-list__item">
                        <div class="ec-rating-list__head">
                            <div>
                                <p class="ec-rating-list__name">{{ $review['customer_name'] }}</p>
                                <p class="ec-rating-list__time">{{ $review['created_at'] }}</p>
                            </div>
                            <div class="ec-inline-rating__stars" aria-label="{{ $review['rating'] }} {{ __('stars') }}">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="{{ $review['rating'] >= $i ? 'is-on' : '' }}" viewBox="0 0 24 24" fill="currentColor"><path d="m12 3.4 2.5 5.1 5.6.8-4 3.9.9 5.6L12 16.2l-5 2.6.9-5.6-4-3.9 5.6-.8L12 3.4Z"/></svg>
                                @endfor
                            </div>
                        </div>
                        @if(!empty($review['comment']))
                            <p class="ec-rating-list__comment">{{ $review['comment'] }}</p>
                        @endif
                        @if(!empty($review['recommend']))
                            <p class="ec-rating-list__recommend">{{ __('Recommends this product') }}</p>
                        @endif
                    </article>
                @empty
                    <div class="ec-panel border-dashed p-8 text-center text-sm text-slate-500">
                        {{ __('No customer reviews yet') }}
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="ec-section mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="ec-section-head">
            <div>
                <p class="ec-kicker">{{ __('More to love') }}</p>
                <h2 class="ec-title">{{ __('Related Products') }}</h2>
                <p class="ec-subtitle">{{ __('You may also like these pick') }}</p>
            </div>
            <div class="items-center gap-2 flex">
                <button id="relatedPrev" type="button" class="ec-icon-btn !h-9 !w-9">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7"/></svg>
                </button>
                <button id="relatedNext" type="button" class="ec-icon-btn !h-9 !w-9">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/></svg>
                </button>
            </div>
        </div>
        <div id="relatedSlider" class="ec-related-slider flex gap-5 pb-3">
            @foreach($relatedProducts as $related)
                <div class="ec-related-slide">
                    @include('ecommerce::partials.product-card', [
                        'product' => $related,
                        'money' => $money,
                        'productImage' => $productImage,
                    ])
                </div>
            @endforeach
        </div>
    </section>

    <script>
        (function () {
            const slider = document.getElementById('relatedSlider');
            const prev = document.getElementById('relatedPrev');
            const next = document.getElementById('relatedNext');
            if (!slider) return;

            const scrollAmount = () => Math.max(260, Math.floor(slider.clientWidth * 0.85));
            if (prev) {
                prev.addEventListener('click', () => slider.scrollBy({ left: -scrollAmount(), behavior: 'smooth' }));
            }
            if (next) {
                next.addEventListener('click', () => slider.scrollBy({ left: scrollAmount(), behavior: 'smooth' }));
            }

            // Native touch swipe; improve feel with pointer drag on non-touch devices.
            let isPointerDown = false;
            let startX = 0;
            let startScroll = 0;
            let moved = false;

            slider.addEventListener('pointerdown', (e) => {
                if (e.pointerType === 'touch') return;
                if (e.button !== 0) return;
                isPointerDown = true;
                moved = false;
                startX = e.clientX;
                startScroll = slider.scrollLeft;
                slider.classList.add('is-dragging');
                slider.setPointerCapture?.(e.pointerId);
            });

            slider.addEventListener('pointermove', (e) => {
                if (!isPointerDown || e.pointerType === 'touch') return;
                const dx = e.clientX - startX;
                if (Math.abs(dx) > 4) moved = true;
                slider.scrollLeft = startScroll - dx;
            });

            const endPointer = (e) => {
                if (!isPointerDown) return;
                isPointerDown = false;
                slider.classList.remove('is-dragging');
                if (moved) {
                    e.preventDefault();
                }
            };

            slider.addEventListener('pointerup', endPointer);
            slider.addEventListener('pointercancel', endPointer);
            slider.addEventListener('click', (e) => {
                if (moved) {
                    e.preventDefault();
                    e.stopPropagation();
                    moved = false;
                }
            }, true);
        })();

        (function () {
            const mainImage = document.getElementById('mainProductImage');
            const thumbWrap = document.getElementById('thumbSliderWrap');
            const thumbSlider = document.getElementById('thumbSlider');
            const prev = document.getElementById('thumbPrev');
            const next = document.getElementById('thumbNext');
            if (!mainImage || !thumbSlider || !thumbWrap) return;

            let currentIndex = 0;

            const getThumbs = () => Array.from(thumbSlider.querySelectorAll('.thumb-item'));

            const goTo = (index) => {
                const thumbs = getThumbs();
                if (!thumbs.length) return;
                currentIndex = ((index % thumbs.length) + thumbs.length) % thumbs.length;
                const active = thumbs[currentIndex];
                const image = active.getAttribute('data-image');
                if (image) mainImage.src = image;
                thumbs.forEach((item, i) => item.classList.toggle('is-active', i === currentIndex));
                active.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            };

            const bindThumbs = () => {
                getThumbs().forEach((thumb, index) => {
                    thumb.addEventListener('click', () => goTo(index));
                });
            };

            window.setProductGallery = (images) => {
                const list = Array.from(new Set((images || []).filter(Boolean)));
                if (!list.length) return;

                thumbSlider.innerHTML = '';
                list.forEach((image, index) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = `thumb-item${index === 0 ? ' is-active' : ''}`;
                    btn.setAttribute('data-image', image);
                    btn.innerHTML = `<img src="${image}" alt="thumb-${index + 1}">`;
                    thumbSlider.appendChild(btn);
                });

                thumbWrap.classList.toggle('hidden', list.length <= 1);
                thumbSlider.scrollLeft = 0;
                currentIndex = 0;
                mainImage.src = list[0];
                bindThumbs();
            };

            bindThumbs();
            if (prev) prev.addEventListener('click', () => goTo(currentIndex - 1));
            if (next) next.addEventListener('click', () => goTo(currentIndex + 1));
        })();

        (function () {
            const list = document.getElementById('variationOptions');
            const availabilityEl = document.getElementById('productAvailability');
            const priceEl = document.getElementById('variationPrice');
            const oldPriceEl = document.getElementById('variationOldPrice');
            const addBtn = document.getElementById('variationAddToCart');
            const buyBtn = document.getElementById('variationBuyNow');

            const labels = {
                select: @json(__('')),
                inStock: @json(__('In Stock')),
                outStock: @json(__('Out Stock')),
                selectFirst: @json(__('Please select a variation first.')),
            };

            const money = (amount) => window.formatEcommerceMoney
                ? window.formatEcommerceMoney(amount)
                : `$${Number(amount || 0).toFixed(2)}`;

            const setAvailability = (state) => {
                if (!availabilityEl) return;
                availabilityEl.classList.remove('text-emerald-600', 'text-rose-600', 'text-slate-500');
                if (state === 'in') {
                    availabilityEl.textContent = labels.inStock;
                    availabilityEl.classList.add('text-emerald-600');
                } else if (state === 'out') {
                    availabilityEl.textContent = labels.outStock;
                    availabilityEl.classList.add('text-rose-600');
                } else {
                    availabilityEl.textContent = labels.select;
                    availabilityEl.classList.add('text-slate-500');
                }
            };

            if (!list) return;

            const options = Array.from(list.querySelectorAll('[data-variation-option]'));
            let selected = null;

            const setActionsEnabled = (enabled) => {
                if (addBtn) addBtn.disabled = !enabled;
                if (buyBtn) buyBtn.disabled = !enabled;
            };

            const parseGallery = (input) => {
                let gallery = [];
                try {
                    gallery = JSON.parse(input.getAttribute('data-gallery') || '[]');
                } catch (e) {
                    gallery = [];
                }
                if (!Array.isArray(gallery) || !gallery.length) {
                    const fallback = input.getAttribute('data-image') || '';
                    gallery = fallback ? [fallback] : [];
                }
                return gallery.filter(Boolean);
            };

            const markSelected = (option) => {
                options.forEach((item) => item.classList.toggle('is-selected', item === option));
            };

            const applySelection = (input) => {
                if (!input) return;

                const option = input.closest('[data-variation-option]');
                const productId = Number(input.value);
                const price = Number(input.getAttribute('data-price') || 0);
                const oldPrice = Number(input.getAttribute('data-old-price') || 0);
                const inStock = input.getAttribute('data-in-stock') === '1';
                const gallery = parseGallery(input);

                input.checked = true;
                selected = inStock ? { productId, price } : null;
                markSelected(option);
                setAvailability(inStock ? 'in' : 'out');

                if (priceEl) priceEl.textContent = money(price);
                if (oldPriceEl) {
                    if (oldPrice > price) {
                        oldPriceEl.textContent = money(oldPrice);
                        oldPriceEl.classList.remove('hidden');
                    } else {
                        oldPriceEl.textContent = '';
                        oldPriceEl.classList.add('hidden');
                    }
                }

                if (typeof window.setProductGallery === 'function') {
                    window.setProductGallery(gallery);
                }

                setActionsEnabled(inStock);
            };

            const addSelected = async (buyNow = false) => {
                if (!selected) {
                    alert(labels.selectFirst);
                    return;
                }
                if (typeof window.ecommerceCartAdd !== 'function') return;

                const btn = buyNow ? buyBtn : addBtn;
                try {
                    if (btn) btn.disabled = true;
                    await window.ecommerceCartAdd(selected.productId, 1, selected.price, false);
                    setActionsEnabled(true);
                    if (buyNow) {
                        if (typeof window.ecommerceGoToCheckoutOrAuth === 'function') {
                            window.ecommerceGoToCheckoutOrAuth();
                        } else if (!window.ecommerceCart?.isLoggedIn) {
                            document.getElementById('customerLoginModal')?.classList.remove('hidden');
                            document.getElementById('customerLoginModal')?.classList.add('flex');
                            document.getElementById('customerLoginBackdrop')?.classList.remove('hidden');
                        } else if (window.ecommerceCart?.urls?.checkout) {
                            window.location.href = window.ecommerceCart.urls.checkout;
                        }
                    }
                } catch (e) {
                    console.error(e);
                    setActionsEnabled(!!selected);
                }
            };

            list.addEventListener('change', (event) => {
                const input = event.target.closest('input[type="radio"]');
                if (!input) return;
                applySelection(input);
            });

            if (addBtn) addBtn.addEventListener('click', () => addSelected(false));
            if (buyBtn) buyBtn.addEventListener('click', () => addSelected(true));

            const initialInput = list.querySelector('input[type="radio"]:checked')
                || list.querySelector('input[type="radio"]');
            if (initialInput) {
                applySelection(initialInput);
            } else {
                setActionsEnabled(false);
                setAvailability('select');
            }
        })();
    </script>
    <script>
        window.__productRatingProductId = @json((string) ($product['encrypted_id'] ?? encrypt($product['id'])));
        window.__productRatingPreset = @json($ratingPanel['my_rating'] ?? null);
        window.__productHasPurchased = @json(!empty($ratingPanel['has_purchased']));

        window.ecommerceOnRatingSaved = function (panel) {
            if (!panel) {
                window.location.reload();
                return;
            }
            window.__productRatingPreset = panel.my_rating || null;
            window.__productHasPurchased = !!panel.has_purchased;

            document.querySelectorAll('[data-rating-average]').forEach((el) => {
                el.textContent = Number(panel.average || 0).toFixed(1);
            });
            document.querySelectorAll('[data-rating-total]').forEach((el) => {
                el.textContent = String(panel.total || 0);
            });

            const recommendEl = document.querySelector('[data-rating-recommend]');
            if (recommendEl) {
                if (panel.total > 0) {
                    recommendEl.textContent = @json(__(':count out of :total (:percent%) reviewers recommend this product'))
                        .replace(':count', panel.recommend_count)
                        .replace(':total', panel.total)
                        .replace(':percent', panel.recommend_percent);
                } else {
                    recommendEl.textContent = @json(__('No reviews yet. Be the first to rate this product'));
                }
            }

            [5,4,3,2,1].forEach((star) => {
                const el = document.querySelector(`[data-star-count="${star}"]`);
                if (el) el.textContent = String((panel.snapshot && panel.snapshot[star]) || 0);
            });

            // Refresh bars widths
            document.querySelectorAll('.ec-rating-bars li').forEach((li) => {
                const countEl = li.querySelector('[data-star-count]');
                const trackFill = li.querySelector('.ec-rating-bars__track > span');
                if (!countEl || !trackFill) return;
                const count = parseInt(countEl.textContent || '0', 10);
                const pct = panel.total > 0 ? Math.round((count / panel.total) * 100) : 0;
                trackFill.style.width = pct + '%';
            });

            // Refresh filled stars under product name + panel summary
            const avg = Number(panel.average || 0);
            document.querySelectorAll('.ec-inline-rating__stars, .ec-rating-panel__stars').forEach((group) => {
                group.querySelectorAll('svg').forEach((svg, i) => {
                    svg.classList.toggle('is-on', avg >= ((i + 1) - 0.25));
                });
            });

            window.setTimeout(() => window.location.reload(), 600);
        };

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-rate-product]');
            if (!trigger) return;
            if (!window.ecommerceCart?.isLoggedIn) return;
            if (window.__productHasPurchased === false) {
                event.preventDefault();
                event.stopPropagation();
                if (typeof window.ecommerceToast === 'function') {
                    window.ecommerceToast(@json(__('You can only rate products you have purchased.')), 'error');
                }
            }
        }, true);
    </script>
@endsection
