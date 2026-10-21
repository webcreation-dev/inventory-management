@php
    $walkInCustomer = isset($customers) ? collect($customers)->firstWhere('name', 'Walk-in Customer') : null;
    $walkInCustomerId = $walkInCustomer ? $walkInCustomer->id : null;
    $posMcForJson = isset($pos_multiple_currencies)
        ? $pos_multiple_currencies->map(static fn ($c) => [
            'id' => $c->id,
            'currency' => $c->currency,
            'conversion_rate' => (float) $c->conversion_rate,
        ])->values()->all()
        : [];
@endphp
<div class="modal fade" id="modal_pos_payment" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-walk-in-customer-id="{{ $walkInCustomerId ?? '' }}"
    data-base-currency="{{ e(session('company.currency') ?? '') }}"
    data-text-payment-amount-label="{{ __('Payment Amount') }}"
    data-text-loyalty-points-label="{{ __('Points to redeem') }}"
    data-text-hint-default="{{ __('Each line cannot exceed the remaining total payable. Walk-in customers must pay the full amount (no due).') }}"
    data-text-hint-loyalty="{{ __('Enter how many points to redeem. The line amount is points × your company loyalty rate.') }}"
    data-text-placeholder-amount="{{ __('Enter amount') }}"
    data-text-placeholder-points="{{ __('Enter points') }}"
    data-points-exceed-template="{{ e(__('You have %s points. You cannot enter more than %s points.')) }}">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Payment') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{-- JSON must not live in a double-quoted data-attribute (breaks parsing). --}}
                <script type="application/json" id="pos-payment-multiple-currencies-json">@json($posMcForJson)</script>
                <div class="payment_modal_wrapper">
                    <!-- Left Sidebar - Payment Methods -->
                    <div class="payment-methods-sidebar">
                        <h6 class="mb-3">{{ __('Payment Methods') }}</h6>
                        <div class="payment-methods-list thin-scroll" id="payment-methods-list">
                            @php 
                                $default_payment_method = session('company.default_payment');
                            @endphp
                            @if(isset($payment_methods) && count($payment_methods) > 0)
                                @foreach($payment_methods as $method)
                                    @php
                                        $gatewayName = null;
                                        if ($method->configuration && is_array($method->configuration)) {
                                            // Support both new nested structure and old flat structure
                                            $gatewayName = $method->configuration['gateway'] ?? null;
                                            
                                            // If no gateway key, try to detect from account_type
                                            if (!$gatewayName && in_array($method->account_type, ['Stripe', 'Paypal', 'Razorpay', 'Paystack', 'Paytm', 'Flutterwave', 'SslCommerz', 'Mollie', 'Senangpay', 'Bkash', 'Mercadopago', 'Cashfree', 'Payfast', 'Skrill', 'PhonePe', 'Telr', 'Iyzico', 'Pesapal', 'Midtrans', 'MyFatoorah', 'EasyPaisa', 'Mpesa'])) {
                                                $gatewayName = strtolower($method->account_type);
                                            }
                                        }
                                    @endphp
                                    <button type="button" 
                                            class="btn btn-outline-primary w-100 payment-method-btn" 
                                            data-payment-id="{{ $method->id }}"
                                            data-payment-name="{{ $method->name }}"
                                            data-account-type="{{ $method->account_type }}"
                                            data-gateway-name="{{ $gatewayName }}"
                                            data-default="{{ $default_payment_method == $method->id ? 'Yes' : 'No' }}">
                                        {{ $method->name }}
                                    </button>
                                @endforeach
                            @else
                                <p class="text-muted text-center">{{ __('No payment methods available') }}</p>
                            @endif
                        </div>
                        @if(module_enabled('multiple_currency'))
                        <button type="button"
                            class="btn btn-danger w-100 mt-2 pos-change-currency-btn">
                            {{ __('Change Currency') }}
                        </button>
                        @endif
                    </div>

                    <!-- Right Side - Payment Details -->
                    <div class="payment-details-section-wrapper">

                        <div class="payment-customer-summary border mb-3" id="payment-customer-summary">
                            <div class="card-body py-2 px-3">
                                <div class="small text-muted mb-1">{{ __('Customer') }}</div>
                                <div class="fw-semibold">
                                    <span id="pos-payment-customer-name">—</span>
                                    (<span id="pos-payment-customer-phone">—</span>)
                                </div>
                                <div class="small mt-1 ">
                                    <span class="text-danger">{{ __('Previous Due Amount') }}:</span>
                                    <span id="pos-payment-customer-previous-due" class="fw-semibold text-danger">0.00</span>
                                </div>
                                <div id="pos-payment-loyalty-row" class="small mt-1" style="display: none;">
                                    <span class="text-muted">{{ __('Available loyalty points') }}:</span>
                                    <span id="pos-payment-customer-loyalty-points" class="fw-semibold">0</span>
                                    <span class="text-muted ms-2" id="pos-payment-loyalty-min-hint" style="display: none;"></span>
                                </div>
                            </div>
                        </div>

                        <div class="payment-details-section">
                            <div class="payment-table-section">
                                <!-- Payment Amount Input -->
                                <div class="mb-4" id="pos-payment-currency-row" style="display: none;">
                                    <label class="form-label" for="pos-payment-currency-select">{{ __('Currency') }}</label>
                                    <select id="pos-payment-currency-select" class="form-select"></select>
                                    <div class="form-text text-muted small mt-1" id="pos-payment-currency-hint"></div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label" id="pos-payment-amount-label">{{ __('Payment Amount') }}</label>
                                    <div class="form-text text-muted small mb-2" id="pos-payment-amount-hint-default">{{ __('Each line cannot exceed the remaining total payable. Walk-in customers must pay the full amount (no due).') }}</div>
                                    <div class="form-text text-muted small mb-2" id="pos-payment-amount-hint-loyalty" style="display: none;">{{ __('Enter how many points to redeem. The line amount is points × your company loyalty rate.') }}</div>
                                    <div class="input-group">
                                        <input type="text" class="form-control number-input form-control-lg" id="payment-amount-input" placeholder="{{ __('Enter amount') }}" min="0" step="0.01" autocomplete="off">
                                        <button type="button" class="btn btn-primary" id="add-payment-btn">
                                            <i class="icon-base ti tabler-plus me-1"></i>
                                            {{ __('Add') }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Total Payable -->
                                <div class="mb-4">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">{{ __('Total Payable') }}:</h6>
                                                <h4 class="mb-0 text-primary" id="pos-payment-total">0.00</h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Gateway Payment Element (Dynamic - Stripe, PayPal, etc.) -->
                            <div class="gateway-payment-section mb-4" id="gateway-payment-wrapper" style="display: none;">
                                <h6 class="mb-3" id="gateway-payment-title">{{ __('Card Payment') }}</h6>
                                <div id="gateway-payment-element" class="mb-3">
                                    <!-- Gateway payment forms will be mounted here dynamically -->
                                </div>
                                <button type="button" class="btn btn-primary w-100" id="gateway-confirm-payment-btn" style="display: none;">
                                    <i class="icon-base ti tabler-credit-card me-1"></i>
                                    <span id="gateway-confirm-btn-text">{{ __('Confirm Payment') }}</span>
                                </button>
                            </div>

                            <!-- Denomination Section (for Cash) -->
                            @if(module_enabled('denomination'))
                            <div class="denomination-section mb-4" id="denomination-section">
                                <h6 class="mb-3">{{ __('Denomination') }}</h6>
                                @php 
                                $denominations = \Modules\Configuration\Models\Denomination::where('del_status', 'Live')
                                    ->where('company_id', session('company.company_id'))
                                    ->orderBy('amount', 'asc')
                                    ->get();
                                @endphp
                                <div class="denomination-wrapper thin-scroll">

                                @if(isset($denominations) && count($denominations) > 0)
                                    @foreach($denominations as $denomination)
                                        <div class="denomination-item">
                                            <label class="form-label small">{{ $denomination->amount }}</label>
                                            <input type="text" class="form-control number-input denomination-input form-control-sm" data-value="{{ $denomination->amount }}" min="0" value="0">
                                        </div>
                                    @endforeach
                                @else
                                    <div class="col-12">
                                        <p class="text-muted text-center">{{ __('No denominations available') }}</p>
                                    </div>
                                @endif
                                </div>
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong>{{ __('Total Denomination') }}:</strong>
                                        <strong id="denomination-total">0.00</strong>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>

                        

                        <!-- Payment Method Summary -->
                        <div class="payment-summary-section">
                            <h6 class="mb-3">{{ __('Payment Summary') }}</h6>
                            <div class="payment-modal-table-wrapper">
                                <table>
                                    <thead>
                                        <tr>
                                            <th class="w-50">{{ __('Payment Method') }}</th>
                                            <th class="w-25 text-center">{{ __('Amount') }}</th>
                                            <th class="w-25 text-end">{{ __('Action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="payment-summary-tbody">
                                        <tr id="payment-summary-empty">
                                            <td colspan="3" class="text-center text-muted">{{ __('No payment method added') }}</td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th class="w-50">{{ __('Total Paid') }}:</th>
                                            <th class="w-25 text-center" id="payment-summary-total">0.00</th>
                                            <th class="w-25 text-end"></th>
                                        </tr>
                                        {{-- Shown only when the customer hands over more than the remaining payable (cash overpayment). JS toggles visibility. --}}
                                        <tr id="payment-summary-given-row" style="display: none;">
                                            <th class="w-50">{{ __('Given Amount') }}:</th>
                                            <th class="w-25 text-center" id="payment-summary-given">0.00</th>
                                            <th class="w-25 text-end"></th>
                                        </tr>
                                        <tr id="payment-summary-change-row" style="display: none;">
                                            <th class="w-50 text-success">{{ __('Change Amount') }}:</th>
                                            <th class="w-25 text-center text-success" id="payment-summary-change">0.00</th>
                                            <th class="w-25 text-end"></th>
                                        </tr>
                                        <tr>
                                            <th class="w-50">{{ __('Remaining') }}:</th>
                                            <th class="w-25 text-center" id="payment-remaining-amount">0.00</th>
                                            <th class="w-25 text-end"></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer pt-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="form-check mb-0">
                        <input id="email" class="form-check-input" type="checkbox" value="1">
                        <label class="form-check-label" for="email">{{ __('Email') }}</label>
                    </div>
                    <div class="form-check mb-0">
                        <input id="sms" class="form-check-input" type="checkbox" value="1">
                        <label class="form-check-label" for="sms">{{ __('SMS') }}</label>
                    </div>
                    <div class="form-check mb-0">
                        <input id="whatsapp" class="form-check-input" type="checkbox" value="1">
                        <label class="form-check-label" for="whatsapp">{{ __('WhatsApp') }}</label>
                    </div>
                </div>
                <button type="button" class="btn btn-warning" id="pos-due-sale-btn">
                    <i class="icon-base ti tabler-clock me-1"></i>
                    {{ __('Due Sale') }}
                </button>
                <button type="button" class="btn btn-primary" id="pos-payment-submit-btn">
                    <i class="icon-base ti tabler-check me-1"></i>
                    {{ __('Complete Payment') }}
                </button>
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                    <i class="icon-base ti tabler-x me-1"></i>
                    {{ __('Close') }}
                </button>
            </div>
        </div>
    </div>
</div>
