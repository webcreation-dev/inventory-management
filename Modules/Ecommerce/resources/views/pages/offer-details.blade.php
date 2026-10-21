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

    @if(!empty($offer['banner']))
    <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="ec-offer-detail-banner" aria-label="{{ $offer['title'] }}">
            <img src="{{ $offer['banner'] }}" alt="{{ $offer['title'] }}">
        </div>
    </section>
    @else
        @include('ecommerce::partials.page-banner', [
            'title' => $offer['title'],
            'crumbs' => [
                ['label' => __('Home'), 'url' => route('ecommerce.home')],
                ['label' => __('Offers'), 'url' => route('ecommerce.offers')],
                ['label' => $offer['title'], 'active' => true],
            ],
        ])
    @endif

    <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="ec-offer-shell mb-8">
            <div class="relative z-[1] flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[var(--accent-strong)]">
                        {{ !empty($offer['is_running']) ? __('Limited time') : __('Offer') }}
                    </p>
                    <h2 class="ec-display mt-2 text-2xl font-bold text-slate-900 sm:text-3xl">{{ $offer['title'] }}</h2>
                    @if(!empty($offer['description']))
                        <p class="mt-2 max-w-2xl text-sm text-slate-600">{{ $offer['description'] }}</p>
                    @endif
                </div>
                @if(!empty($offer['ends_at']))
                    <div class="ec-countdown" data-offer-countdown data-ends-at="{{ $offer['ends_at'] }}" aria-live="polite">
                        <div class="ec-countdown-unit"><span data-unit="days">00</span><small>{{ __('Days') }}</small></div>
                        <span class="ec-countdown-sep" aria-hidden="true">:</span>
                        <div class="ec-countdown-unit"><span data-unit="hours">00</span><small>{{ __('Hrs') }}</small></div>
                        <span class="ec-countdown-sep" aria-hidden="true">:</span>
                        <div class="ec-countdown-unit"><span data-unit="minutes">00</span><small>{{ __('Mins') }}</small></div>
                        <span class="ec-countdown-sep" aria-hidden="true">:</span>
                        <div class="ec-countdown-unit"><span data-unit="seconds">00</span><small>{{ __('Secs') }}</small></div>
                    </div>
                @endif
            </div>
        </div>

        <div id="offerProductsGrid" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @include('ecommerce::partials.offer-products-chunk', [
                'products' => $products,
                'money' => $money,
                'productImage' => $productImage,
            ])
        </div>

        @if(empty($products))
            <div class="ec-panel mt-4 border-dashed p-10 text-center">
                <p class="text-sm text-slate-500">{{ __('No products in this offer yet.') }}</p>
            </div>
        @endif

        <div id="offerScrollSentinel" class="py-8 text-center" data-has-more="{{ !empty($hasMore) ? '1' : '0' }}" data-next-page="{{ $nextPage ?? '' }}" data-url="{{ $productsUrl }}">
            <p id="offerLoadingMore" class="hidden text-sm font-semibold text-slate-500">{{ __('Loading more products...') }}</p>
            <p id="offerNoMore" class="{{ empty($hasMore) && !empty($products) ? '' : 'hidden' }} text-sm text-slate-400">{{ __('You have reached the end') }}</p>
        </div>
    </section>
@endsection

@push('scripts')
<script>
(function () {
    const endedLabel = @json(__('Offer ended'));
    document.querySelectorAll('[data-offer-countdown]').forEach(function (countdown) {
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
                countdown.innerHTML = `<div class="ec-countdown-ended">${endedLabel}</div>`;
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
            const timer = setInterval(function () {
                if (!tick()) clearInterval(timer);
            }, 1000);
        }
    });

    const sentinel = document.getElementById('offerScrollSentinel');
    const grid = document.getElementById('offerProductsGrid');
    const loadingEl = document.getElementById('offerLoadingMore');
    const noMoreEl = document.getElementById('offerNoMore');
    if (!sentinel || !grid) return;

    let loading = false;
    let hasMore = sentinel.getAttribute('data-has-more') === '1';
    let nextPage = parseInt(sentinel.getAttribute('data-next-page') || '0', 10) || null;
    const url = sentinel.getAttribute('data-url');

    async function loadMore() {
        if (loading || !hasMore || !nextPage || !url) return;
        loading = true;
        if (loadingEl) loadingEl.classList.remove('hidden');

        try {
            const res = await fetch(url + (url.includes('?') ? '&' : '?') + 'page=' + nextPage, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            if (!res.ok) throw new Error('Failed');
            const data = await res.json();
            if (data.html) {
                grid.insertAdjacentHTML('beforeend', data.html);
            }
            hasMore = !!data.has_more;
            nextPage = data.next_page || null;
            sentinel.setAttribute('data-has-more', hasMore ? '1' : '0');
            sentinel.setAttribute('data-next-page', nextPage || '');
            if (!hasMore && noMoreEl) noMoreEl.classList.remove('hidden');
        } catch (e) {
            console.error(e);
        } finally {
            loading = false;
            if (loadingEl) loadingEl.classList.add('hidden');
        }
    }

    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) loadMore();
            });
        }, { rootMargin: '240px 0px' });
        io.observe(sentinel);
    } else {
        window.addEventListener('scroll', function () {
            if ((window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 400)) {
                loadMore();
            }
        }, { passive: true });
    }
})();
</script>
@endpush
