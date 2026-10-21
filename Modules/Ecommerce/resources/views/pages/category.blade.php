@extends('ecommerce::layouts.app')

@section('content')
    @php
        $priceMinBound = (int) ($priceMinBound ?? 0);
        $priceMaxBound = (int) ($priceMaxBound ?? 5000);
        $minPrice = (int) ($minPrice ?? $priceMinBound);
        $maxPrice = (int) ($maxPrice ?? $priceMaxBound);
        $q = $q ?? '';
        $totalProducts = (int) ($totalProducts ?? ($products->total() ?? count($products)));
        $hasMore = (bool) ($hasMore ?? false);
        $nextPage = $nextPage ?? null;
        $productsUrl = $productsUrl ?? route('ecommerce.category.products', ['slug' => $slug]);
        $formAction = $formAction ?? route('ecommerce.category', ['slug' => $slug]);
        $resetUrl = $resetUrl ?? route('ecommerce.category', ['slug' => $slug]);
        $productBadge = $productBadge ?? null;
        $productBadgeClass = $productBadgeClass ?? 'ec-badge-sale';
        $money = function ($amount) {
            return function_exists('formatAmount') ? formatAmount($amount) : ('$' . number_format((float) $amount, 2));
        };
        $productImage = function ($product) {
            return !empty($product['image_is_url'])
                ? $product['image']
                : (str_starts_with((string) ($product['image'] ?? ''), 'http')
                    ? $product['image']
                    : route('ecommerce.image', ['file' => $product['image'] ?? 'banner.png']));
        };
    @endphp
    <style>
        .price-range-wrap { position: relative; height: 28px; }
        .price-range {
            pointer-events: none; position: absolute; inset-inline: 0; top: 0; height: 28px; margin: 0;
            background: transparent; appearance: none; -webkit-appearance: none;
        }
        .price-range::-webkit-slider-runnable-track, .price-range::-moz-range-track { height: 8px; background: transparent; border: none; }
        .price-range::-webkit-slider-thumb {
            pointer-events: auto; -webkit-appearance: none; width: 18px; height: 18px; margin-top: -5px;
            border-radius: 9999px; background: var(--accent-strong); border: 2px solid #fff;
            box-shadow: 0 0 0 2px var(--accent-glow); cursor: pointer;
        }
        .price-range::-moz-range-thumb {
            pointer-events: auto; width: 18px; height: 18px; border-radius: 9999px;
            background: var(--accent-strong); border: 2px solid #fff; box-shadow: 0 0 0 2px var(--accent-glow); cursor: pointer;
        }
        #minRange { z-index: 3; } #maxRange { z-index: 4; }
        #rangeTrack {
            background: linear-gradient(
                to right,
                #e2e8f0 0%,
                rgb(var(--accent-rgb)) 0%,
                rgb(var(--accent-rgb)) 100%,
                #e2e8f0 100%
            );
        }
    </style>

    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="mb-8 ec-reveal">
            <h1 class="ec-title text-3xl sm:text-4xl" id="catalogTitle">{{ $categoryTitle }}</h1>
            <p class="ec-subtitle mt-2 text-base text-slate-600">
                {{ __('Explore All Products') }}
                (<span id="catalogTotal">{{ $totalProducts }}</span> {{ __('Products Found') }})
                @if($q !== '')
                    <span class="text-slate-500">— “{{ $q }}”</span>
                @endif
            </p>
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-4">
            <aside class="ec-panel h-fit p-5 lg:col-span-1 lg:sticky lg:top-24">
                <form id="categoryFilterForm" method="GET" action="{{ $formAction }}" class="space-y-6" data-ajax="1">
                    @if($q !== '')
                        <input type="hidden" name="q" value="{{ $q }}">
                    @endif
                    <div>
                        <h3 class="ec-display text-sm font-bold text-slate-900">{{ __('Price Range') }}</h3>
                        <div class="mt-3 rounded-2xl border border-slate-200 bg-slate-50/80 p-3">
                            <div class="mb-3 flex items-center justify-between text-xs font-semibold text-slate-600">
                                <span>{{ __('Selected') }}</span>
                                <span>$<span id="rangeValueMin">{{ $minPrice }}</span> - $<span id="rangeValueMax">{{ $maxPrice }}</span></span>
                            </div>
                            <div class="relative mb-4">
                                <div id="rangeTrack" class="absolute left-0 right-0 top-2 h-2 rounded-full bg-slate-200"></div>
                                <div class="price-range-wrap">
                                    <input id="minRange" type="range" min="{{ $priceMinBound }}" max="{{ $priceMaxBound }}" step="1" value="{{ $minPrice }}" class="price-range">
                                    <input id="maxRange" type="range" min="{{ $priceMinBound }}" max="{{ $priceMaxBound }}" step="1" value="{{ $maxPrice }}" class="price-range">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="ec-label">{{ __('Min') }}</label>
                                    <input id="minPriceInput" type="number" name="min_price" value="{{ $minPrice }}" min="{{ $priceMinBound }}" max="{{ $priceMaxBound }}" class="ec-input !py-2">
                                </div>
                                <div>
                                    <label class="ec-label">{{ __('Max') }}</label>
                                    <input id="maxPriceInput" type="number" name="max_price" value="{{ $maxPrice }}" min="{{ $priceMinBound }}" max="{{ $priceMaxBound }}" class="ec-input !py-2">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="ec-display text-sm font-bold text-slate-900">{{ __('Sort By') }}</h3>
                        <div class="ec-choice-list mt-3">
                            @foreach([
                                'newest' => __('Newest'),
                                'price_low' => __('Price Low To High'),
                                'price_high' => __('Price High To Low'),
                                'name_az' => __('Name A-Z'),
                                'name_za' => __('Name Z-A'),
                            ] as $value => $label)
                                <label class="ec-choice">
                                    <input type="radio" name="sort" value="{{ $value }}" class="ec-choice__input js-auto-filter" {{ $sort === $value ? 'checked' : '' }}>
                                    <span class="ec-choice__control ec-choice__control--radio" aria-hidden="true"></span>
                                    <span class="ec-choice__label">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <h3 class="ec-display text-sm font-bold text-slate-900">{{ __('Filter by Brand') }}</h3>
                        <div id="brandFilterList" class="ec-choice-list mt-3 max-h-52 overflow-y-auto pr-1">
                            @forelse($brands as $brand)
                                <label class="ec-choice">
                                    <input type="checkbox" name="brands[]" value="{{ $brand }}" class="ec-choice__input js-auto-filter" {{ in_array($brand, $selectedBrands, true) ? 'checked' : '' }}>
                                    <span class="ec-choice__control ec-choice__control--check" aria-hidden="true"></span>
                                    <span class="ec-choice__label">{{ $brand }}</span>
                                </label>
                            @empty
                                <p class="text-xs text-slate-400 js-no-brands">{{ __('No brands available') }}</p>
                            @endforelse
                        </div>
                    </div>

                    <a href="{{ $resetUrl }}" id="catalogResetBtn" class="ec-btn ec-btn-ghost ec-btn-block">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M5 19a9 9 0 0 0 14.2-3M19 5a9 9 0 0 0-14.2 3"/></svg>
                        {{ __('Reset') }}
                    </a>
                </form>
            </aside>

            <div class="lg:col-span-3">
                <div id="catalogProductsGrid" class="grid items-start content-start gap-5 self-start sm:grid-cols-2 xl:grid-cols-3">
                    @forelse($products as $product)
                        @include('ecommerce::partials.product-card', [
                            'product' => $product,
                            'money' => $money,
                            'productImage' => $productImage,
                            'badge' => $productBadge,
                            'badgeClass' => $productBadgeClass,
                        ])
                    @empty
                        <div id="catalogEmpty" class="ec-panel border-dashed p-10 text-center text-sm text-slate-500 sm:col-span-2 xl:col-span-3">
                            {{ __('No products found for the selected filters.') }}
                        </div>
                    @endforelse
                </div>
                <div id="catalogLoadingMore" class="mt-8 hidden text-center text-sm font-semibold text-slate-500">{{ __('Loading more products...') }}</div>
                <div id="catalogNoMore" class="mt-8 {{ $hasMore ? 'hidden' : '' }} text-center text-sm text-slate-400">
                    @if($totalProducts > 0)
                        {{ __('You have reached the end') }}
                    @endif
                </div>
                <div id="catalogScrollSentinel"
                     class="h-4 w-full"
                     data-url="{{ $productsUrl }}"
                     data-has-more="{{ $hasMore ? '1' : '0' }}"
                     data-next-page="{{ $nextPage ?: '' }}"></div>
            </div>
        </div>
    </section>

    <script>
        (function () {
            const form = document.getElementById('categoryFilterForm');
            const minRange = document.getElementById('minRange');
            const maxRange = document.getElementById('maxRange');
            const minInput = document.getElementById('minPriceInput');
            const maxInput = document.getElementById('maxPriceInput');
            const minLabel = document.getElementById('rangeValueMin');
            const maxLabel = document.getElementById('rangeValueMax');
            const rangeTrack = document.getElementById('rangeTrack');
            const grid = document.getElementById('catalogProductsGrid');
            const totalEl = document.getElementById('catalogTotal');
            const loadingEl = document.getElementById('catalogLoadingMore');
            const noMoreEl = document.getElementById('catalogNoMore');
            const sentinel = document.getElementById('catalogScrollSentinel');
            const brandList = document.getElementById('brandFilterList');
            const emptyHtml = `<div id="catalogEmpty" class="ec-panel border-dashed p-10 text-center text-sm text-slate-500 sm:col-span-2 xl:col-span-3">{{ __('No products found for the selected filters.') }}</div>`;

            if (!form || !grid || !sentinel || !minRange || !maxRange || !minInput || !maxInput || !minLabel || !maxLabel || !rangeTrack) return;

            let boundMin = parseInt(minRange.min, 10);
            let boundMax = parseInt(minRange.max, 10);
            let gap = Math.max(1, Math.round((boundMax - boundMin) * 0.01));
            let loading = false;
            let filtering = false;
            let hasMore = sentinel.getAttribute('data-has-more') === '1';
            let nextPage = parseInt(sentinel.getAttribute('data-next-page') || '0', 10) || null;
            const productsUrl = sentinel.getAttribute('data-url');
            let filterTimer = null;

            const paintTrack = (minVal, maxVal) => {
                const start = ((minVal - boundMin) / (boundMax - boundMin)) * 100;
                const end = ((maxVal - boundMin) / (boundMax - boundMin)) * 100;
                rangeTrack.style.background = `linear-gradient(to right, #e2e8f0 ${start}%, rgb(var(--accent-rgb)) ${start}%, rgb(var(--accent-rgb)) ${end}%, #e2e8f0 ${end}%)`;
            };

            const syncZIndex = (minVal) => {
                const mid = (boundMin + boundMax) / 2;
                minRange.style.zIndex = minVal > mid ? '5' : '3';
                maxRange.style.zIndex = '4';
            };

            const applyValues = (minVal, maxVal) => {
                minVal = Math.max(boundMin, Math.min(boundMax, minVal));
                maxVal = Math.max(boundMin, Math.min(boundMax, maxVal));
                if (minVal > maxVal - gap) {
                    if (document.activeElement === minRange || document.activeElement === minInput) {
                        minVal = Math.max(boundMin, maxVal - gap);
                    } else {
                        maxVal = Math.min(boundMax, minVal + gap);
                    }
                }
                minRange.value = String(minVal);
                maxRange.value = String(maxVal);
                minInput.value = String(minVal);
                maxInput.value = String(maxVal);
                minLabel.textContent = String(minVal);
                maxLabel.textContent = String(maxVal);
                paintTrack(minVal, maxVal);
                syncZIndex(minVal);
            };

            const fromRanges = () => applyValues(parseInt(minRange.value, 10), parseInt(maxRange.value, 10));
            const fromInputs = () => applyValues(parseInt(minInput.value || String(boundMin), 10), parseInt(maxInput.value || String(boundMax), 10));

            minRange.addEventListener('input', fromRanges);
            maxRange.addEventListener('input', fromRanges);
            minRange.addEventListener('mousedown', () => { minRange.style.zIndex = '6'; });
            maxRange.addEventListener('mousedown', () => { maxRange.style.zIndex = '6'; });
            minInput.addEventListener('input', fromInputs);
            maxInput.addEventListener('input', fromInputs);
            fromRanges();

            const buildQuery = (page) => {
                const params = new URLSearchParams();
                const q = form.querySelector('input[name="q"]');
                if (q && q.value) params.set('q', q.value);
                params.set('min_price', minInput.value || String(boundMin));
                params.set('max_price', maxInput.value || String(boundMax));
                const sort = form.querySelector('input[name="sort"]:checked');
                if (sort) params.set('sort', sort.value);
                form.querySelectorAll('input[name="brands[]"]:checked').forEach((el) => {
                    params.append('brands[]', el.value);
                });
                if (page) params.set('page', String(page));
                return params.toString();
            };

            const syncUrl = () => {
                const qs = buildQuery(null);
                const next = window.location.pathname + (qs ? ('?' + qs) : '');
                window.history.replaceState({}, '', next);
            };

            const renderBrands = (brands, selected) => {
                if (!brandList) return;
                const selectedSet = new Set((selected || []).map(String));
                if (!brands || !brands.length) {
                    brandList.innerHTML = `<p class="text-xs text-slate-400 js-no-brands">{{ __('No brands available') }}</p>`;
                    return;
                }
                brandList.innerHTML = brands.map((brand) => {
                    const checked = selectedSet.has(String(brand)) ? 'checked' : '';
                    return `<label class="ec-choice">
                        <input type="checkbox" name="brands[]" value="${String(brand).replace(/"/g, '&quot;')}" class="ec-choice__input js-auto-filter" ${checked}>
                        <span class="ec-choice__control ec-choice__control--check" aria-hidden="true"></span>
                        <span class="ec-choice__label">${String(brand).replace(/</g, '&lt;')}</span>
                    </label>`;
                }).join('');
                brandList.querySelectorAll('.js-auto-filter').forEach((el) => {
                    el.addEventListener('change', () => scheduleFilter());
                });
            };

            const setPaginationState = (data) => {
                hasMore = !!data.has_more;
                nextPage = data.next_page || null;
                sentinel.setAttribute('data-has-more', hasMore ? '1' : '0');
                sentinel.setAttribute('data-next-page', nextPage || '');
                if (totalEl) totalEl.textContent = String(data.total ?? 0);
                if (noMoreEl) {
                    noMoreEl.classList.toggle('hidden', hasMore || !(data.total > 0));
                    if (!hasMore && data.total > 0) noMoreEl.textContent = @json(__('You have reached the end'));
                }
            };

            const fetchProducts = async (page, { replace = false } = {}) => {
                if (!productsUrl) return;
                if (!replace && (loading || filtering || !hasMore || !nextPage)) return;
                if (replace) filtering = true; else loading = true;
                if (loadingEl) loadingEl.classList.toggle('hidden', false);

                try {
                    const qs = buildQuery(page);
                    const res = await fetch(productsUrl + (productsUrl.includes('?') ? '&' : '?') + qs, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });
                    if (!res.ok) throw new Error('Failed');
                    const data = await res.json();

                    if (replace) {
                        grid.innerHTML = data.html && data.html.trim() ? data.html : emptyHtml;
                        if (Array.isArray(data.brands)) {
                            renderBrands(data.brands, data.selected_brands || []);
                        }
                        if (typeof data.price_min_bound !== 'undefined') {
                            boundMin = parseInt(data.price_min_bound, 10);
                            boundMax = parseInt(data.price_max_bound, 10);
                            gap = Math.max(1, Math.round((boundMax - boundMin) * 0.01));
                            minRange.min = String(boundMin);
                            maxRange.min = String(boundMin);
                            minRange.max = String(boundMax);
                            maxRange.max = String(boundMax);
                            minInput.min = String(boundMin);
                            maxInput.min = String(boundMin);
                            minInput.max = String(boundMax);
                            maxInput.max = String(boundMax);
                            applyValues(parseInt(data.min_price, 10), parseInt(data.max_price, 10));
                        }
                        syncUrl();
                    } else if (data.html) {
                        const empty = document.getElementById('catalogEmpty');
                        if (empty) empty.remove();
                        grid.insertAdjacentHTML('beforeend', data.html);
                    }

                    setPaginationState(data);
                } catch (e) {
                    console.error(e);
                } finally {
                    loading = false;
                    filtering = false;
                    if (loadingEl) loadingEl.classList.add('hidden');
                }
            };

            const scheduleFilter = () => {
                clearTimeout(filterTimer);
                filterTimer = setTimeout(() => {
                    hasMore = false;
                    nextPage = null;
                    fetchProducts(1, { replace: true });
                }, 280);
            };

            form.querySelectorAll('.js-auto-filter').forEach((el) => {
                el.addEventListener('change', scheduleFilter);
            });

            ['change', 'mouseup', 'touchend'].forEach((evt) => {
                minRange.addEventListener(evt, scheduleFilter);
                maxRange.addEventListener(evt, scheduleFilter);
            });
            minInput.addEventListener('change', scheduleFilter);
            maxInput.addEventListener('change', scheduleFilter);

            form.addEventListener('submit', (e) => {
                e.preventDefault();
                scheduleFilter();
            });

            const loadMore = () => {
                if (!hasMore || !nextPage || loading || filtering) return;
                fetchProducts(nextPage, { replace: false });
            };

            if ('IntersectionObserver' in window) {
                const io = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) loadMore();
                    });
                }, { rootMargin: '280px 0px' });
                io.observe(sentinel);
            } else {
                window.addEventListener('scroll', () => {
                    if ((window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 400)) {
                        loadMore();
                    }
                }, { passive: true });
            }
        })();
    </script>
@endsection
