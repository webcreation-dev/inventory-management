@extends('ecommerce::layouts.app')

@section('content')
    <section class="mx-auto max-w-xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="rounded-3xl bg-white p-8 shadow-xl ring-1 ring-slate-200">
            <h1 class="text-2xl font-bold text-slate-900">{{ __('Complete Stripe Payment') }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ __('Order') }}: {{ $sale->sale_no }} — ${{ number_format((float) $sale->total_payable, 2) }}</p>
            <div id="stripe-payment-element" class="mt-6"></div>
            <p id="stripe-error" class="mt-3 hidden text-sm text-rose-600"></p>
            <button id="stripe-submit" type="button" class="mt-6 w-full rounded-lg bg-brand-600 px-5 py-3 text-sm font-semibold text-white hover:bg-brand-700">
                {{ __('Pay Now') }}
            </button>
            <a href="{{ route('ecommerce.payment.stripe.cancel', ['sale' => $sale->id, 'payment_method_id' => $paymentMethod->id]) }}" class="mt-3 block text-center text-sm font-medium text-slate-600 hover:text-brand-700">{{ __('Cancel') }}</a>
        </div>
    </section>

    <script src="https://js.stripe.com/v3/"></script>
    <script>
        (async function () {
            const publishableKey = @json($publishableKey);
            const clientSecret = @json($clientSecret);
            const returnUrl = @json($returnUrl);
            const errorEl = document.getElementById('stripe-error');
            const submitBtn = document.getElementById('stripe-submit');

            if (!publishableKey || !clientSecret) {
                errorEl.textContent = @json(__('Stripe is not configured correctly.'));
                errorEl.classList.remove('hidden');
                return;
            }

            const stripe = Stripe(publishableKey);
            const elements = stripe.elements({ clientSecret, appearance: { theme: 'stripe' } });
            const paymentElement = elements.create('payment');
            paymentElement.mount('#stripe-payment-element');

            submitBtn.addEventListener('click', async () => {
                submitBtn.disabled = true;
                errorEl.classList.add('hidden');

                const { error, paymentIntent } = await stripe.confirmPayment({
                    elements,
                    confirmParams: { return_url: returnUrl },
                    redirect: 'if_required',
                });

                if (error) {
                    errorEl.textContent = error.message || 'Payment failed';
                    errorEl.classList.remove('hidden');
                    submitBtn.disabled = false;
                    return;
                }

                // No redirect required (e.g. simple card success) — send user to return URL.
                if (paymentIntent && ['succeeded', 'processing'].includes(paymentIntent.status)) {
                    const url = new URL(returnUrl, window.location.origin);
                    url.searchParams.set('payment_intent', paymentIntent.id);
                    url.searchParams.set('redirect_status', paymentIntent.status);
                    window.location.href = url.toString();
                    return;
                }

                // Fallback: always go to return URL so server can verify.
                window.location.href = returnUrl;
            });
        })();
    </script>
@endsection
