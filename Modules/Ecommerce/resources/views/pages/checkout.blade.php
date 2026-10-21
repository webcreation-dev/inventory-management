@extends('ecommerce::layouts.app')

@section('content')
    @php
        $fieldClass = 'ec-input';
        $fieldErrorClass = ' border-rose-400';
        $req = '<span class="text-rose-600">*</span>';
    @endphp
    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <p class="ec-kicker">{{ __('Secure checkout') }}</p>
        <h1 class="ec-title text-3xl sm:text-4xl">{{ __('Checkout') }}</h1>
        <p class="ec-subtitle">{{ __('Complete your order in 3 simple steps') }}</p>

        @if(empty($cartItems))
            <div class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <p class="text-slate-600">{{ __('Your cart is empty') }}</p>
                <a href="{{ route('ecommerce.home') }}" class="mt-4 inline-block ec-btn ec-btn-primary">{{ __('Continue Shopping') }}</a>
            </div>
        @else
            <div class="mt-8 flex justify-center flex-wrap items-center gap-3 text-sm font-semibold">
                <div class="checkout-step-indicator flex items-center gap-2 rounded-full bg-[var(--accent-strong)] pe-3 ps-2 py-2 text-white" data-step="1">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/20 text-xs">1</span>
                    {{ __('Cart') }}
                </div>
                <div class="h-px w-8 bg-slate-300"></div>
                <div class="checkout-step-indicator flex items-center gap-2 rounded-full bg-slate-200 pe-3 ps-2 py-2 text-slate-600" data-step="2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/20 text-xs">2</span>
                    {{ __('Branch / Delivery') }}
                </div>
                <div class="h-px w-8 bg-slate-300"></div>
                <div class="checkout-step-indicator flex items-center gap-2 rounded-full bg-slate-200 pe-3 ps-2 py-2 text-slate-600" data-step="3">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/20 text-xs">3</span>
                    {{ __('Payment') }}
                </div>
            </div>

            <form id="checkoutForm" method="POST" action="{{ route('ecommerce.checkout.place') }}" class="mt-8 grid gap-8 lg:grid-cols-3" novalidate>
                @csrf
                <input type="hidden" name="fulfillment_type" id="fulfillment_type" value="{{ old('fulfillment_type', 'delivery') }}">
                <input type="hidden" name="payment_method_id" id="payment_method_id" value="{{ old('payment_method_id') }}">

                <div class="space-b-6 lg:col-span-2">
                    {{-- Step 1: Cart --}}
                    <div class="checkout-step ec-panel p-6" data-step-panel="1">
                        <h2 class="ec-display text-lg font-bold text-slate-900">1. {{ __('Cart Section') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Review items before continuing') }}</p>
                        <div class="mt-5 space-y-4">
                            @foreach($cartItems as $item)
                                @php
                                    $img = !empty($item['image_is_url'])
                                        ? $item['image']
                                        : route('ecommerce.image', ['file' => $item['image']]);
                                @endphp
                                <div class="flex gap-4 rounded-xl border border-slate-200 p-4">
                                    <img src="{{ $img }}" alt="{{ $item['name'] }}" class="h-20 w-20 rounded-lg object-cover">
                                    <div class="flex-1">
                                        <p class="font-semibold text-slate-900">{{ $item['name'] }}</p>
                                        <p class="mt-1 text-sm text-slate-500">Qty: {{ $item['qty'] }}</p>
                                        <p class="mt-1 text-sm font-bold text-brand-700">${{ number_format($item['price'] * $item['qty'], 2) }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-6 flex justify-end">
                            <button type="button" class="checkout-next ec-btn ec-btn-primary" data-next="2">
                                {{ __('Continue to Branch / Delivery') }}
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                            </button>
                        </div>
                    </div>

                    {{-- Step 2: Branch / Delivery --}}
                    <div class="checkout-step hidden ec-panel p-6" data-step-panel="2">
                        <h2 class="text-lg font-semibold text-slate-900">2. {{ __('Branch / Outlet / Location') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Choose pickup from a branch or home delivery') }}</p>

                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            <label class="fulfillment-option cursor-pointer rounded-xl border-2 border-brand-600 bg-brand-50 p-4" data-type="delivery">
                                <input type="radio" name="fulfillment_radio" value="delivery" class="sr-only" {{ old('fulfillment_type', 'delivery') === 'delivery' ? 'checked' : '' }}>
                                <p class="font-semibold text-slate-900">{{ __('Delivery Point') }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ __('Ship to your address') }}</p>
                            </label>
                            <label class="fulfillment-option cursor-pointer rounded-xl border-2 border-slate-200 bg-white p-4" data-type="pickup">
                                <input type="radio" name="fulfillment_radio" value="pickup" class="sr-only" {{ old('fulfillment_type') === 'pickup' ? 'checked' : '' }}>
                                <p class="font-semibold text-slate-900">{{ __('Pickup Point') }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ __('Collect from a branch') }}</p>
                            </label>
                        </div>

                        <div id="pickupSection" class="mt-6 {{ old('fulfillment_type') === 'pickup' ? '' : 'hidden' }}">
                            <h3 class="text-sm font-semibold text-slate-900">{{ __('Select Branch / Outlet') }} {!! $req !!}</h3>
                            <div id="outletError" class="mt-2 hidden rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700"></div>
                            @error('outlet_id')
                                <div class="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $message }}</div>
                            @enderror
                            <div class="ec-choice-list mt-3">
                                @forelse($outlets as $outlet)
                                    <label class="ec-choice">
                                        <input type="radio" name="outlet_id" value="{{ $outlet->id }}" class="ec-choice__input"
                                            {{ (string) old('outlet_id') === (string) $outlet->id ? 'checked' : '' }}>
                                        <span class="ec-choice__control ec-choice__control--radio" aria-hidden="true"></span>
                                        <span class="ec-choice__label">
                                            <span class="font-bold text-slate-900">{{ $outlet->outlet_name }}</span>
                                            @if(!empty($outlet->address))
                                                <span class="font-normal text-slate-500"> — {{ $outlet->address }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @empty
                                    <p class="rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-500">{{ __('No active branches available.') }}</p>
                                @endforelse
                            </div>
                        </div>

                        <div id="deliverySection" class="mt-6 {{ old('fulfillment_type', 'delivery') === 'delivery' ? '' : 'hidden' }}">
                            <h3 class="text-base font-semibold text-slate-900">{{ __('Customer Information') }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ __('Email is locked to your account. Other fields are editable.') }}</p>

                            <div class="mt-5 space-y-4">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-600" for="first_name">{{ __('First Name') }} {!! $req !!}</label>
                                        <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $customer['first_name'] ?? '') }}" placeholder="First name"
                                               class="{{ $fieldClass }} @error('first_name') {{ $fieldErrorClass }} @enderror">
                                        <p class="field-error mt-1 hidden text-xs text-rose-600" data-error-for="first_name"></p>
                                        @error('first_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-600" for="last_name">{{ __('Last Name') }}</label>
                                        <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $customer['last_name'] ?? '') }}" placeholder="Last name"
                                               class="{{ $fieldClass }} @error('last_name') {{ $fieldErrorClass }} @enderror">
                                        <p class="field-error mt-1 hidden text-xs text-rose-600" data-error-for="last_name"></p>
                                        @error('last_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-600" for="email">{{ __('Email Address') }} {!! $req !!}</label>
                                        <input type="email" id="email" name="email" value="{{ old('email', $customer['email'] ?? '') }}" readonly
                                               class="mt-1 w-full cursor-not-allowed rounded-xl border border-slate-300 bg-slate-100 px-4 py-3 text-sm text-slate-600 shadow-sm">
                                        @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-600" for="phone">{{ __('Phone Number') }} {!! $req !!}</label>
                                        <input type="text" id="phone" name="phone" value="{{ old('phone', $customer['phone'] ?? '') }}" placeholder="+880..."
                                               class="{{ $fieldClass }} @error('phone') {{ $fieldErrorClass }} @enderror">
                                        <p class="field-error mt-1 hidden text-xs text-rose-600" data-error-for="phone"></p>
                                        @error('phone')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-600" for="delivery_area_id">{{ __('Delivery Area') }} {!! $req !!}</label>
                                    <select id="delivery_area_id" name="delivery_area_id"
                                            class="{{ $fieldClass }} @error('delivery_area_id') {{ $fieldErrorClass }} @enderror">
                                        <option value="">{{ __('Select delivery area') }}</option>
                                        @foreach(($deliveryAreas ?? []) as $area)
                                            <option value="{{ $area->id }}"
                                                    data-amount="{{ (float) $area->amount }}"
                                                    @selected((string) old('delivery_area_id') === (string) $area->id)>
                                                {{ $area->name }} — ${{ number_format((float) $area->amount, 2) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="field-error mt-1 hidden text-xs text-rose-600" data-error-for="delivery_area_id"></p>
                                    @error('delivery_area_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    @if(($deliveryAreas ?? collect())->isEmpty())
                                        <p class="mt-1 text-xs text-amber-600">{{ __('No delivery areas are available. Please contact support.') }}</p>
                                    @endif
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-600" for="address">{{ __('Delivery Address') }} {!! $req !!}</label>
                                    <textarea id="address" name="address" rows="3" placeholder="{{ __('House, road, area, city') }}"
                                              class="{{ $fieldClass }} @error('address') {{ $fieldErrorClass }} @enderror">{{ old('address', $customer['address'] ?? '') }}</textarea>
                                    <p class="field-error mt-1 hidden text-xs text-rose-600" data-error-for="address"></p>
                                    @error('address')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-between gap-3">
                            <button type="button" class="checkout-prev ec-btn ec-btn-ghost" data-prev="1">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 12H5m6 6-6-6 6-6"/></svg>
                                {{ __('Back') }}
                            </button>
                            <button type="button" class="checkout-next ec-btn ec-btn-primary" data-next="3">
                                {{ __('Continue to Payment') }}
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                            </button>
                        </div>
                    </div>

                    {{-- Step 3: Payment --}}
                    <div class="checkout-step hidden ec-panel p-6" data-step-panel="3">
                        <h2 class="text-lg font-semibold text-slate-900">3. {{ __('Payment') }} {!! $req !!}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Choose an online-enabled payment method and place your order') }}</p>

                        @if(session('error'))
                            <div class="mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ session('error') }}</div>
                        @endif

                        <div class="mt-5 text-sm text-slate-600">
                            <div class="ec-choice-list">
                                @forelse(($paymentMethods ?? []) as $method)
                                    @php
                                        $gateway = match ($method->account_type) {
                                            'Stripe' => 'stripe',
                                            'Paypal' => 'paypal',
                                            default => strtolower((string) $method->account_type),
                                        };
                                        $isSelected = (string) old('payment_method_id') === (string) $method->id
                                            || (! old('payment_method_id') && $loop->first);
                                    @endphp
                                    <label class="ec-choice">
                                        <input type="radio"
                                               name="payment_method_radio"
                                               value="{{ $method->id }}"
                                               data-gateway="{{ $gateway }}"
                                               class="ec-choice__input"
                                               {{ $isSelected ? 'checked' : '' }}>
                                        <span class="ec-choice__control ec-choice__control--radio" aria-hidden="true"></span>
                                        <span class="ec-choice__label">
                                            <span class="font-bold text-slate-900">{{ $method->name }}</span>
                                            @if(!empty($method->account_type))
                                                <span class="font-normal text-slate-500"> — {{ $method->account_type }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @empty
                                    <div class="rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-500">
                                        {{ __('No online payment methods are enabled.') }}
                                    </div>
                                @endforelse
                            </div>
                            <p id="paymentMethodError" class="mt-2 hidden text-xs text-rose-600"></p>
                            @error('payment_method_id')<p class="mt-2 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="mt-6 flex justify-between gap-3">
                            <button type="button" class="checkout-prev ec-btn ec-btn-ghost" data-prev="2">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 12H5m6 6-6-6 6-6"/></svg>
                                {{ __('Back') }}
                            </button>
                            <button type="submit" class="ec-btn ec-btn-primary" {{ empty($paymentMethods) ? 'disabled' : '' }}>
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                {{ __('Place Order') }}
                            </button>
                        </div>
                    </div>
                </div>

                <aside class="h-fit ec-panel p-6 lg:sticky lg:top-28">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Order Summary') }}</h2>
                    <div class="mt-4 space-y-3 text-sm">
                        @foreach($cartItems as $item)
                            <div class="flex justify-between gap-3">
                                <span class="text-slate-600">{{ $item['name'] }} × {{ $item['qty'] }}</span>
                                <span class="font-medium text-slate-900">${{ number_format($item['price'] * $item['qty'], 2) }}</span>
                            </div>
                        @endforeach
                        <div class="border-t border-slate-200 pt-3">
                            <div class="flex justify-between text-slate-600"><span>{{ __('Subtotal') }}</span><span>${{ number_format($subtotal, 2) }}</span></div>
                            <div class="mt-2 flex justify-between text-slate-600"><span>{{ __('Tax') }}</span><span>${{ number_format($tax, 2) }}</span></div>
                            <div id="summaryShippingRow" class="mt-2 flex justify-between text-slate-600"><span>{{ __('Shipping') }}</span><span id="summaryShipping">${{ number_format(0, 2) }}</span></div>
                            <div class="mt-3 flex justify-between text-base font-bold text-slate-900"><span>{{ __('Total') }}</span><span id="summaryTotal">${{ number_format($subtotal + $tax, 2) }}</span></div>
                        </div>
                    </div>
                </aside>
            </form>
        @endif
    </section>

    @if(!empty($cartItems))
    <script>
        (function () {
            const subtotal = {{ (float) $subtotal }};
            const tax = {{ (float) $tax }};
            let shippingFee = 0;
            const fulfillmentInput = document.getElementById('fulfillment_type');
            const paymentMethodInput = document.getElementById('payment_method_id');
            const pickupSection = document.getElementById('pickupSection');
            const deliverySection = document.getElementById('deliverySection');
            const deliveryAreaSelect = document.getElementById('delivery_area_id');
            const summaryShipping = document.getElementById('summaryShipping');
            const summaryShippingRow = document.getElementById('summaryShippingRow');
            const summaryTotal = document.getElementById('summaryTotal');
            const outletError = document.getElementById('outletError');
            const paymentMethodError = document.getElementById('paymentMethodError');
            const hasOutlets = {{ ($outlets ?? collect())->count() > 0 ? 'true' : 'false' }};
            const hasDeliveryAreas = {{ ($deliveryAreas ?? collect())->count() > 0 ? 'true' : 'false' }};

            let activeFulfillmentType = fulfillmentInput?.value || 'delivery';

            const money = (n) => window.formatEcommerceMoney(n);

            const getSelectedAreaShipping = () => {
                const selected = deliveryAreaSelect?.selectedOptions?.[0];
                if (!selected || !selected.value) {
                    return 0;
                }
                const amount = parseFloat(selected.getAttribute('data-amount') || '0');
                return Number.isFinite(amount) ? amount : 0;
            };

            const refreshShippingSummary = (type = getFulfillmentType()) => {
                shippingFee = getSelectedAreaShipping();
                const ship = type === 'pickup' ? 0 : shippingFee;
                if (summaryShipping) summaryShipping.textContent = money(ship);
                if (summaryShippingRow) summaryShippingRow.classList.toggle('hidden', type === 'pickup');
                if (summaryTotal) summaryTotal.textContent = money(subtotal + tax + ship);
            };

            const setFieldError = (name, message) => {
                const input = document.querySelector(`[name="${name}"]`);
                const errorEl = document.querySelector(`[data-error-for="${name}"]`);
                if (input) {
                    input.classList.toggle('border-rose-400', !!message);
                    input.classList.toggle('border-slate-300', !message);
                }
                if (errorEl) {
                    errorEl.textContent = message || '';
                    errorEl.classList.toggle('hidden', !message);
                }
            };

            const clearStep2Errors = () => {
                ['first_name', 'phone', 'address', 'delivery_area_id'].forEach((name) => setFieldError(name, ''));
                if (outletError) {
                    outletError.textContent = '';
                    outletError.classList.add('hidden');
                }
            };

            const getFulfillmentType = () => {
                const checked = document.querySelector('input[name="fulfillment_radio"]:checked');
                if (checked?.value) {
                    return checked.value;
                }
                const activeOption = document.querySelector('.fulfillment-option.border-brand-600, .fulfillment-option.bg-brand-50');
                if (activeOption?.getAttribute('data-type')) {
                    return activeOption.getAttribute('data-type');
                }
                return activeFulfillmentType || fulfillmentInput.value || 'delivery';
            };

            const scrollToFirstError = () => {
                const target = document.querySelector('#outletError:not(.hidden), .field-error:not(.hidden), .border-rose-400');
                if (target && typeof target.scrollIntoView === 'function') {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            };

            const showOutletError = (message) => {
                if (!outletError) return;
                outletError.textContent = message;
                outletError.classList.remove('hidden');
            };

            const validateDeliveryFields = () => {
                let valid = true;
                const firstName = document.querySelector('input[name="first_name"]')?.value?.trim() || '';
                const phone = document.querySelector('input[name="phone"]')?.value?.trim() || '';
                const address = document.querySelector('[name="address"]')?.value?.trim() || '';
                const deliveryAreaId = deliveryAreaSelect?.value || '';

                if (!firstName) {
                    setFieldError('first_name', @json(__('First name is required.')));
                    valid = false;
                }
                if (!phone) {
                    setFieldError('phone', @json(__('Phone number is required.')));
                    valid = false;
                }
                if (!deliveryAreaId) {
                    setFieldError('delivery_area_id', hasDeliveryAreas
                        ? @json(__('Please select a delivery area.'))
                        : @json(__('No delivery areas are available.')));
                    valid = false;
                }
                if (!address) {
                    setFieldError('address', @json(__('Delivery address is required.')));
                    valid = false;
                }
                return valid;
            };

            const validatePickupBranch = () => {
                const selected = document.querySelector('input[name="outlet_id"]:checked');
                if (!hasOutlets) {
                    showOutletError(@json(__('No active branches available. Please choose Delivery Point or contact support.')));
                    return false;
                }
                if (!selected) {
                    showOutletError(@json(__('Please select a pickup branch.')));
                    return false;
                }
                return true;
            };

            const validateStep2 = () => {
                clearStep2Errors();

                const selectedType = getFulfillmentType();
                const deliveryOk = validateDeliveryFields();
                // Require a branch whenever outlets exist (both tabs must be complete).
                // If user chose Pickup but no outlets exist, still block and show pickup error.
                const needPickupCheck = hasOutlets || selectedType === 'pickup';
                const pickupOk = needPickupCheck ? validatePickupBranch() : true;

                // Jump to the tab that still has missing fields (Delivery first, then Pickup).
                if (!deliveryOk) {
                    setFulfillment('delivery');
                    scrollToFirstError();
                    return false;
                }

                if (!pickupOk) {
                    setFulfillment('pickup');
                    scrollToFirstError();
                    return false;
                }

                // Both complete — keep the tab the customer last chose for order type.
                setFulfillment(selectedType);
                return true;
            };

            const setStep = (step) => {
                document.querySelectorAll('[data-step-panel]').forEach((panel) => {
                    panel.classList.toggle('hidden', Number(panel.getAttribute('data-step-panel')) !== step);
                });
                document.querySelectorAll('.checkout-step-indicator').forEach((el) => {
                    const active = Number(el.getAttribute('data-step')) === step;
                    el.classList.toggle('bg-[var(--accent-strong)]', active);
                    el.classList.toggle('bg-brand-600', active);
                    el.classList.toggle('text-white', active);
                    el.classList.toggle('bg-slate-200', !active);
                    el.classList.toggle('text-slate-600', !active);
                });
            };

            const setFulfillment = (type) => {
                activeFulfillmentType = type;
                fulfillmentInput.value = type;
                pickupSection.classList.toggle('hidden', type !== 'pickup');
                deliverySection.classList.toggle('hidden', type !== 'delivery');
                document.querySelectorAll('.fulfillment-option').forEach((el) => {
                    const active = el.getAttribute('data-type') === type;
                    el.classList.toggle('border-brand-600', active);
                    el.classList.toggle('bg-brand-50', active);
                    el.classList.toggle('border-slate-200', !active);
                    el.classList.toggle('bg-white', !active);
                    const radio = el.querySelector('input[name="fulfillment_radio"]');
                    if (radio) radio.checked = active;
                });
                refreshShippingSummary(type);
            };

            document.querySelectorAll('.checkout-next').forEach((btn) => {
                btn.addEventListener('click', (event) => {
                    event.preventDefault();
                    const next = Number(btn.getAttribute('data-next'));
                    if (next === 3 && !validateStep2()) {
                        return;
                    }
                    setStep(next);
                });
            });

            document.querySelectorAll('.checkout-prev').forEach((btn) => {
                btn.addEventListener('click', () => setStep(Number(btn.getAttribute('data-prev'))));
            });

            document.querySelectorAll('.fulfillment-option').forEach((el) => {
                el.addEventListener('click', (event) => {
                    event.preventDefault();
                    const type = el.getAttribute('data-type');
                    clearStep2Errors();
                    setFulfillment(type);
                });
            });

            document.querySelectorAll('input[name="fulfillment_radio"]').forEach((input) => {
                input.addEventListener('change', () => {
                    clearStep2Errors();
                    setFulfillment(input.value);
                });
            });

            document.querySelectorAll('input[name="outlet_id"]').forEach((input) => {
                input.addEventListener('change', () => {
                    if (outletError) {
                        outletError.textContent = '';
                        outletError.classList.add('hidden');
                    }
                });
            });

            deliveryAreaSelect?.addEventListener('change', () => {
                setFieldError('delivery_area_id', '');
                refreshShippingSummary();
            });

            document.querySelectorAll('input[name="payment_method_radio"]').forEach((input) => {
                const syncPayment = () => {
                    paymentMethodInput.value = input.value;
                    if (paymentMethodError) {
                        paymentMethodError.classList.add('hidden');
                        paymentMethodError.textContent = '';
                    }
                };
                input.addEventListener('change', syncPayment);
                if (input.checked) syncPayment();
            });

            ['first_name', 'phone', 'address'].forEach((name) => {
                const el = document.querySelector(`[name="${name}"]`);
                if (!el) return;
                el.addEventListener('input', () => setFieldError(name, ''));
            });

            document.getElementById('checkoutForm')?.addEventListener('submit', (event) => {
                if (!validateStep2()) {
                    event.preventDefault();
                    setStep(2);
                    return;
                }
                if (!paymentMethodInput.value) {
                    event.preventDefault();
                    if (paymentMethodError) {
                        paymentMethodError.textContent = @json(__('Please select a payment method.'));
                        paymentMethodError.classList.remove('hidden');
                    }
                    setStep(3);
                }
            });

            setFulfillment(getFulfillmentType());
            @if($errors->has('payment_method_id'))
                setStep(3);
            @elseif($errors->has('outlet_id'))
                setFulfillment('pickup');
                setStep(2);
            @elseif($errors->any())
                setFulfillment('delivery');
                setStep(2);
            @endif
        })();
    </script>
    @endif
@endsection
