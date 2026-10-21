@extends('ecommerce::layouts.app')

@section('content')
    @php
        $money = function ($amount) {
            return function_exists('formatAmount') ? formatAmount($amount) : ('$' . number_format((float) $amount, 2));
        };
        $productImage = function ($product) {
            return !empty($product['image_is_url'])
                ? $product['image']
                : route('ecommerce.image', ['file' => $product['image']]);
        };
    @endphp

    @if(count($heroSlides))
    <section class="mx-auto max-w-7xl px-4 pt-8 sm:px-6 lg:px-8 ec-reveal">
        <div class="ec-hero">
            <div id="heroSlider" class="flex transition-transform duration-700 ease-out">
                @foreach($heroSlides as $slide)
                    <article class="relative w-full shrink-0">
                        @php
                            $slideImage = !empty($slide['is_url']) ? $slide['image'] : route('ecommerce.image', ['file' => $slide['image']]);
                            $slideLink = $slide['link'] ?? null;
                        @endphp
                        @if(!empty($slideLink))
                            <a href="{{ $slideLink }}" target="_blank" rel="noopener noreferrer">
                                <img src="{{ $slideImage }}" alt="{{ $slide['alt'] ?? 'Banner' }}">
                            </a>
                        @else
                            <img src="{{ $slideImage }}" alt="{{ $slide['alt'] ?? 'Banner' }}">
                        @endif
                    </article>
                @endforeach
            </div>
            @if(count($heroSlides) > 1)
            <button type="button" id="heroPrev" class="ec-hero-arrow ec-hero-arrow--prev" aria-label="{{ __('Previous slide') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15 6 9 12l6 6"/>
                </svg>
            </button>
            <button type="button" id="heroNext" class="ec-hero-arrow ec-hero-arrow--next" aria-label="{{ __('Next slide') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="m9 6 6 6-6 6"/>
                </svg>
            </button>
            <div id="heroDots" class="ec-hero-dots">
                @foreach($heroSlides as $slide)
                    <button type="button" class="ec-hero-dot hero-dot" aria-label="Slide navigation"></button>
                @endforeach
            </div>
            @endif
        </div>
    </section>
    @endif

    @if(count($browseCategories))
    <section class="ec-section pb-0 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 ec-reveal ec-reveal-delay-1">
        <div class="ec-section-head">
            <div>
                <p class="ec-kicker">{{ __('Explore') }}</p>
                <h2 class="ec-title">{{ __('Browse Category') }}</h2>
                <p class="ec-subtitle">{{ __('Shop by curated collection') }}</p>
            </div>
            <a href="{{ route('ecommerce.category', ['slug' => 'all']) }}" class="ec-link-more">{{ __('View All') }} →</a>
        </div>
        <div class="grid gap-4 grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            @foreach($browseCategories as $category)
                <a href="{{ route('ecommerce.category', ['slug' => $category['slug']]) }}" class="ec-cat-tile">
                    @if(!empty($category['image']))
                        <img src="{{ !empty($category['is_url']) ? $category['image'] : route('ecommerce.image', ['file' => $category['image']]) }}" alt="{{ $category['name'] }}">
                    @else
                        <div class="flex h-40 items-center justify-center bg-slate-100 text-slate-400">{{ $category['name'] }}</div>
                    @endif
                    <div class="p-4">
                        <h3 class="ec-display text-base font-bold text-slate-900 text-center">{{ $category['name'] }}</h3>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
    @endif

    @if(count($offerProducts ?? $flashSaleProducts ?? []))
    @php
        $offerList = $offerProducts ?? $flashSaleProducts;
        $offerEndsAtValue = $offerEndsAt ?? $flashSaleEndsAt ?? null;
    @endphp
    <section class="ec-section pb-0 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 ec-reveal">
        <div id="offer"></div>
        <div class="ec-offer-shell mb-8">
            <div class="relative z-[1] flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[var(--accent-strong)] inline-flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v5l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        {{ __('Limited time') }}
                    </p>
                    <h2 class="ec-display mt-2 text-2xl font-bold text-slate-900 sm:text-3xl">{{ $offerTitle ?? $flashSaleTitle ?? __('Special Offer') }}</h2>
                    <p class="mt-2 max-w-xl text-sm text-slate-600">{{ $offerDescription ?: __('Limited-time offers on selected products.') }}</p>
                </div>
                <div class="flex flex-col items-end gap-3">
                    @if(!empty($offerEndsAtValue))
                        <div class="ec-countdown" id="offerCountdown" data-ends-at="{{ $offerEndsAtValue }}" aria-live="polite">
                            <div class="ec-countdown-unit"><span data-unit="days">00</span><small>{{ __('Days') }}</small></div>
                            <span class="ec-countdown-sep" aria-hidden="true">:</span>
                            <div class="ec-countdown-unit"><span data-unit="hours">00</span><small>{{ __('Hrs') }}</small></div>
                            <span class="ec-countdown-sep" aria-hidden="true">:</span>
                            <div class="ec-countdown-unit"><span data-unit="minutes">00</span><small>{{ __('Mins') }}</small></div>
                            <span class="ec-countdown-sep" aria-hidden="true">:</span>
                            <div class="ec-countdown-unit"><span data-unit="seconds">00</span><small>{{ __('Secs') }}</small></div>
                        </div>
                    @else
                        <span class="rounded-full border border-[rgba(var(--accent-rgb),0.2)] bg-white px-3 py-1.5 text-xs font-bold text-[var(--accent-strong)] shadow-sm">{{ __('Live Offer') }}</span>
                    @endif
                    <a href="{{ route('ecommerce.offers') }}" class="ec-link-more">{{ __('View all offers') }} →</a>
                </div>
            </div>
        </div>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($offerList as $product)
                @include('ecommerce::partials.product-card', [
                    'product' => $product,
                    'money' => $money,
                    'productImage' => $productImage,
                    'badge' => __('Offer'),
                    'badgeClass' => 'ec-badge-offer',
                ])
            @endforeach
        </div>
    </section>
    @endif

    @if(count($newTrendProducts ?? []))
    <section class="ec-section pb-0 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="ec-section-head">
            <div>
                <p class="ec-kicker">{{ __('Fresh') }}</p>
                <h2 class="ec-title">{{ __('New Trend') }}</h2>
                <p class="ec-subtitle">{{ __('Fresh arrivals and trending picks') }}</p>
            </div>
            <a href="{{ route('ecommerce.collection', ['type' => 'new-trend']) }}" class="ec-link-more">{{ __('View All') }} →</a>
        </div>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($newTrendProducts as $product)
                @include('ecommerce::partials.product-card', [
                    'product' => $product,
                    'money' => $money,
                    'productImage' => $productImage,
                    'badge' => __('New'),
                    'badgeClass' => 'ec-badge-new',
                ])
            @endforeach
        </div>
    </section>
    @endif

    @if(count($mostPopularProducts ?? []))
    <section class="ec-section pb-0 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="ec-section-head">
            <div>
                <p class="ec-kicker">{{ __('Bestsellers') }}</p>
                <h2 class="ec-title">{{ __('Most Popular Products') }}</h2>
                <p class="ec-subtitle">{{ __('Customer favorites and best sellers') }}</p>
            </div>
            <a href="{{ route('ecommerce.collection', ['type' => 'popular']) }}" class="ec-link-more">{{ __('View All') }} →</a>
        </div>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($mostPopularProducts as $product)
                @include('ecommerce::partials.product-card', [
                    'product' => $product,
                    'money' => $money,
                    'productImage' => $productImage,
                    'badge' => __('Popular'),
                    'badgeClass' => 'ec-badge-hot',
                ])
            @endforeach
        </div>
    </section>
    @endif

    
    @if(count($featuredProducts))
    <section class="ec-section pb-0 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="ec-section-head">
            <div>
                <p class="ec-kicker">{{ __('Curated') }}</p>
                <h2 class="ec-title">{{ __('Featured Products') }}</h2>
                <p class="ec-subtitle">{{ __('Carefully selected professional collection') }}</p>
            </div>
        </div>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($featuredProducts as $product)
                @include('ecommerce::partials.product-card', ['product' => $product, 'money' => $money, 'productImage' => $productImage])
            @endforeach
        </div>
    </section>
    @endif

    @if(!count($heroSlides) && !count($browseCategories) && !count($offerProducts ?? []) && !count($newTrendProducts ?? []) && !count($mostPopularProducts ?? []) && !count($popularBrands) && !count($featuredProducts))
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="ec-panel rounded-[1.75rem] border-dashed p-12 text-center">
            <h2 class="ec-display text-xl font-bold text-slate-900">{{ __('Storefront content is empty') }}</h2>
            <p class="mt-2 text-sm text-slate-500">{{ __('Add banners, categories, brands, new trend, popular products, or offers from Ecommerce CMS.') }}</p>
        </div>
    </section>
    @endif

    @if(count($popularBrands))
    <section class="ec-section pb-0 mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="ec-section-head">
            <div>
                <p class="ec-kicker">{{ __('Partners') }}</p>
                <h2 class="ec-title">{{ __('Popular Brands') }}</h2>
                <p class="ec-subtitle">{{ __('Trusted brands on our storefront') }}</p>
            </div>
        </div>
        <div class="ec-brand-track-wrap" id="brandTrackWrap">
            <div id="brandSlider" class="ec-brand-track">
                @php
                    $brandLoop = array_merge($popularBrands, $popularBrands, $popularBrands);
                @endphp
                @foreach($brandLoop as $brand)
                    @php $brandName = is_array($brand) ? ($brand['name'] ?? '') : $brand; $brandImage = is_array($brand) ? ($brand['image'] ?? null) : null; @endphp
                    <div class="ec-brand-pill">
                        @if($brandImage)
                            <img src="{{ $brandImage }}" alt="{{ $brandName }}" class="h-10 object-contain">
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <section class="ec-trust-band ec-reveal" aria-label="{{ __('Why shop with us') }}">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="ec-trust">
                <article class="ec-trust__item">
                    <span class="ec-trust__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3a7 7 0 0 0-7 7v2.2a2.8 2.8 0 0 0 1.4 2.4l1.1.7V17a2 2 0 0 0 2 2h1.2"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.5 20.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18 15.5V13a6 6 0 0 0-1.2-3.6"/>
                        </svg>
                    </span>
                    <h3 class="ec-trust__title">{{ __('Professional Service') }}</h3>
                    <p class="ec-trust__text">{{ __('Efficient customer support from passionate team') }}</p>
                </article>
                <article class="ec-trust__item">
                    <span class="ec-trust__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3 4.5 6.2v5.3c0 4.4 3 8.4 7.5 9.5 4.5-1.1 7.5-5.1 7.5-9.5V6.2L12 3Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9.2 12.1 1.9 1.9 3.7-3.8"/>
                        </svg>
                    </span>
                    <h3 class="ec-trust__title">{{ __('Secure Payment') }}</h3>
                    <p class="ec-trust__text">{{ __('Different secure payment methods') }}</p>
                </article>
                <article class="ec-trust__item">
                    <span class="ec-trust__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7.5h9.5V15H3z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12.5 10h4.2l2.8 2.8V15H12.5z"/>
                            <circle cx="7" cy="17.5" r="1.6" stroke-width="1.8"/>
                            <circle cx="17" cy="17.5" r="1.6" stroke-width="1.8"/>
                        </svg>
                    </span>
                    <h3 class="ec-trust__title">{{ __('Fast Delivery') }}</h3>
                    <p class="ec-trust__text">{{ __('Fast and convenient door to door delivery') }}</p>
                </article>
                <article class="ec-trust__item">
                    <span class="ec-trust__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3.5 14.1 8l4.9.4-3.7 3.2 1.1 4.8L12 14.2 7.6 16.4l1.1-4.8L5 8.4 9.9 8 12 3.5Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7.5 19.5h9"/>
                        </svg>
                    </span>
                    <h3 class="ec-trust__title">{{ __('Quality & Savings') }}</h3>
                    <p class="ec-trust__text">{{ __('Comprehensive quality control and affordable prices') }}</p>
                </article>
            </div>
        </div>
    </section>


    <script>
        (function () {
            const slider = document.getElementById('heroSlider');
            const dots = document.querySelectorAll('.hero-dot');
            const prevBtn = document.getElementById('heroPrev');
            const nextBtn = document.getElementById('heroNext');
            if (slider && dots.length) {
                let current = 0;
                const total = dots.length;
                let timer = null;

                const render = () => {
                    slider.style.transform = `translateX(-${current * 100}%)`;
                    dots.forEach((dot, i) => {
                        dot.classList.toggle('is-active', i === current);
                    });
                };

                const goTo = (index) => {
                    current = ((index % total) + total) % total;
                    render();
                };

                const next = () => goTo(current + 1);
                const prev = () => goTo(current - 1);

                const startAuto = () => {
                    clearInterval(timer);
                    timer = setInterval(next, 4200);
                };

                dots.forEach((dot, i) => {
                    dot.addEventListener('click', () => {
                        goTo(i);
                        startAuto();
                    });
                });

                if (prevBtn) {
                    prevBtn.addEventListener('click', () => {
                        prev();
                        startAuto();
                    });
                }
                if (nextBtn) {
                    nextBtn.addEventListener('click', () => {
                        next();
                        startAuto();
                    });
                }

                render();
                startAuto();
            }

            const brandSlider = document.getElementById('brandSlider');
            const brandWrap = document.getElementById('brandTrackWrap');
            if (brandSlider && brandWrap) {
                let offset = 0;
                let paused = false;
                const speed = 0.45;
                const loopWidth = () => Math.max(1, brandSlider.scrollWidth / 3);
                const tick = () => {
                    if (!paused) {
                        offset -= speed;
                        const half = loopWidth();
                        if (Math.abs(offset) >= half) offset += half;
                        brandSlider.style.transform = `translate3d(${offset}px,0,0)`;
                    }
                    requestAnimationFrame(tick);
                };
                brandWrap.addEventListener('mouseenter', () => { paused = true; });
                brandWrap.addEventListener('mouseleave', () => { paused = false; });
                brandWrap.addEventListener('touchstart', () => { paused = true; }, { passive: true });
                brandWrap.addEventListener('touchend', () => { paused = false; }, { passive: true });
                requestAnimationFrame(tick);
            }

            const countdown = document.getElementById('offerCountdown');
            if (countdown) {
                const endsAt = Date.parse(countdown.getAttribute('data-ends-at') || '');
                const units = {
                    days: countdown.querySelector('[data-unit="days"]'),
                    hours: countdown.querySelector('[data-unit="hours"]'),
                    minutes: countdown.querySelector('[data-unit="minutes"]'),
                    seconds: countdown.querySelector('[data-unit="seconds"]'),
                };
                const pad = (n) => String(Math.max(0, n)).padStart(2, '0');
                const tick = () => {
                    if (!Number.isFinite(endsAt)) return false;
                    const diff = endsAt - Date.now();
                    if (diff <= 0) {
                        countdown.innerHTML = `<div class="ec-countdown-ended">{{ __('Offer ended') }}</div>`;
                        return false;
                    }
                    const totalSec = Math.floor(diff / 1000);
                    if (units.days) units.days.textContent = pad(Math.floor(totalSec / 86400));
                    if (units.hours) units.hours.textContent = pad(Math.floor((totalSec % 86400) / 3600));
                    if (units.minutes) units.minutes.textContent = pad(Math.floor((totalSec % 3600) / 60));
                    if (units.seconds) units.seconds.textContent = pad(totalSec % 60);
                    return true;
                };
                if (tick()) {
                    const timer = setInterval(() => {
                        if (!tick()) clearInterval(timer);
                    }, 1000);
                }
            }
        })();
    </script>
@endsection
