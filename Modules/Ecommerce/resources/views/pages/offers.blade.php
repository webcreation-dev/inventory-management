@extends('ecommerce::layouts.app')

@section('content')
    @include('ecommerce::partials.page-banner', [
        'title' => __('Offers'),
        'subtitle' => __('Limited-time deals curated for you'),
        'crumbs' => [
            ['label' => __('Home'), 'url' => route('ecommerce.home')],
            ['label' => __('Offers'), 'active' => true],
        ],
    ])

    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        @if(($offers ?? collect())->isEmpty())
            <div class="ec-panel border-dashed p-10 text-center">
                <p class="text-sm text-slate-500">{{ __('No active offers right now. Please check back soon') }}</p>
                <a href="{{ route('ecommerce.home') }}" class="ec-btn ec-btn-primary mt-4">{{ __('Back to Home') }}</a>
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($offers as $offer)
                    <article class="ec-offer-card">
                        <div class="ec-offer-card__media">
                            @if(!empty($offer['thumbnail']))
                                <img src="{{ $offer['thumbnail'] }}" alt="{{ $offer['title'] }}" loading="lazy">
                            @else
                                <div class="ec-offer-card__placeholder">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v8m-4-4h8M7 4h10l3 7-8 9-8-9 3-7Z"/>
                                    </svg>
                                </div>
                            @endif
                            @if(!empty($offer['is_running']))
                                <span class="ec-offer-card__badge">{{ __('Live') }}</span>
                            @else
                                <span class="ec-offer-card__badge is-soon">{{ __('Upcoming') }}</span>
                            @endif
                        </div>
                        <div class="ec-offer-card__body">
                            <h2 class="ec-offer-card__title">{{ $offer['title'] }}</h2>
                            @if(!empty($offer['description']))
                                <p class="ec-offer-card__desc">{{ \Illuminate\Support\Str::limit($offer['description'], 90) }}</p>
                            @endif

                            @if(!empty($offer['ends_at']))
                                <div class="ec-countdown ec-countdown--compact mt-4" data-offer-countdown data-ends-at="{{ $offer['ends_at'] }}" aria-live="polite">
                                    <div class="ec-countdown-unit"><span data-unit="days">00</span><small>{{ __('Days') }}</small></div>
                                    <span class="ec-countdown-sep" aria-hidden="true">:</span>
                                    <div class="ec-countdown-unit"><span data-unit="hours">00</span><small>{{ __('Hrs') }}</small></div>
                                    <span class="ec-countdown-sep" aria-hidden="true">:</span>
                                    <div class="ec-countdown-unit"><span data-unit="minutes">00</span><small>{{ __('Mins') }}</small></div>
                                    <span class="ec-countdown-sep" aria-hidden="true">:</span>
                                    <div class="ec-countdown-unit"><span data-unit="seconds">00</span><small>{{ __('Secs') }}</small></div>
                                </div>
                            @else
                                <p class="mt-4 text-xs font-bold uppercase tracking-wide text-[var(--accent-strong)]">{{ __('Limited time offer') }}</p>
                            @endif

                            <div class="ec-offer-card__footer">
                                <span class="text-xs text-slate-500">{{ __(':count products', ['count' => $offer['products_count']]) }}</span>
                                <a href="{{ route('ecommerce.offers.show', $offer['id']) }}" class="ec-btn ec-btn-primary !px-4 !py-2.5 text-sm">
                                    {{ __('View Details') }}
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
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
})();
</script>
@endpush
