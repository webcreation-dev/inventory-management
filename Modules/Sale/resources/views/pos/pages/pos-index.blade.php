@extends('sale::pos.pos-layout')
@section('page-title', 'Point of Sale (POS)')
@push('page-css')
@endpush
@section('page-content')
<script>window.posPrinterSettings = @json($printer_settings ?? null);</script>
<div class="pos-container">
    <!-- Header Section -->
    <div class="pos-header">
        <div class="pos-header-left">
            <div class="mobile-menu-toggler products-view">
                <button type="button" class="cart-toggler btn btn-label-primary btn-sm text-nowrap d-inline-flex position-relative me-4">
                    <i class="icon-base ti tabler-shopping-cart"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge badge-center rounded-pill bg-primary text-white product-length-count">0</span>
                </button>
                <button type="button" class="products-toggler btn btn-label-primary btn-sm">
                    <i class="icon-base ti tabler-layout-grid"></i>
                </button>
            </div>
            <!-- Search Button -->
            <div class="pos-header-search-wrapper">
                <a class="btn btn-sm pos-header-search-toggler d-flex align-items-center rounded" href="javascript:void(0);" id="pos-menu-search-toggler">
                    <i class="icon-base ti tabler-search me-2"></i>
                    <span class="text-body-secondary xs-mobile-text">{{ __('Search Menu') }}</span>
                </a>
            </div>
            <a href="{{ url('/dashboard') }}" class="btn btn-primary btn-sm dashboard-btn">
                <i class="icon-base ti tabler-dashboard"></i>
                <span class="mobile-text">{{ __('Dashboard') }}</span>
            </a>
        </div>
        <div class="pos-header-right">
            <div class="outlet-name">
                <span class="mb-0">{{ session('outlet.outlet_name') }}</span>
            </div>

            <!-- Language Switcher -->
            <div class="pos-header-icon dropdown-language dropdown">
                <button
                    class="btn btn-primary dropdown-toggle"
                    type="button"
                    id="pos-language-switcher-btn"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    title="{{ __('Language Switcher') }}">
                    <span>
                        <i class="icon-base ti tabler-language"></i>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @foreach (collect(available_ui_locales())->sortBy(fn ($locale) => config('app.locale_names.' . $locale, ucfirst($locale)))->values() as $locale)
                    <li>
                        <a class="dropdown-item {{ app()->getLocale() === $locale ? 'active' : '' }}" href="{{ route('language.switch', $locale) }}">
                            <span>{{ config('app.locale_names.' . $locale, ucfirst($locale)) }}</span>
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>
            
            <div class="pos-header-icon btn btn-primary" id="pos-print-last-invoice-btn" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-original-title="{{ __('Print Last Invoice') }}">
                <span>
                    <i class="icon-base ti tabler-printer"></i>
                </span>
            </div>
            <div class="pos-header-icon btn btn-primary small-device-icon xs-device-icon customer-display-btn" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-original-title="{{ __('Customer') }} {{ __('Display') }}">
                <span>
                    <i class="icon-base ti tabler-device-desktop"></i>
                </span>
            </div>
            <div class="pos-header-icon btn btn-primary small-device-icon xs-device-icon" id="pos-calculator-btn" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-original-title="{{ __('Calculator') }}">
                <span>
                    <i class="icon-base ti tabler-calculator"></i>
                </span>
            </div>
            <div class="pos-header-icon btn btn-primary small-device-icon xs-device-icon" id="pos-fullscreen-btn" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-original-title="{{ __('Fullscreen') }}">
                <span>
                    <i class="icon-base ti tabler-maximize" id="pos-fullscreen-icon"></i>
                </span>
            </div>
            <div class="pos-header-icon btn btn-info mobile-icon" id="pos-sync-products-btn" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-original-title="{{ __('Re-sync Products to IndexedDB') }}">
                <span>
                    <i class="icon-base ti tabler-database"></i>
                </span>
            </div>
            <div class="pos-header-icon btn btn-danger logout-btn" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="{{ __('Logout') }}">
                <span>
                    <i class="icon-base ti tabler-power"></i>
                </span>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="pos-main-content">
        <!-- Left Panel - Cart -->
        <div class="pos-cart-panel">
            <div class="pos-cart-header">
                <div class="customer-employee-section">
                    <div class="employee-section">
                        @php 
                            $currentUser = Auth::user()->id;
                            $currentUserRole = Auth::user()->role;
                        @endphp
                        <select name="employee_id" id="employee-select" class="form-select select2">
                            @if($currentUserRole == 1)
                                @foreach($employees as $employee)
                                    <option 
                                        value="{{ $employee->id }}" 
                                        {{ $currentUser == $employee->id ? 'selected' : '' }}
                                    >
                                        {{ $employee->name }}
                                    </option>
                                @endforeach
                            @else
                                <option value="{{ $currentUser }}" selected>{{ Auth::user()->name }}</option>
                            @endif
                        </select>
                        <input type="hidden" class="default_employee_id" value="{{ $currentUser }}">
                    </div>
                    <div class="customer-section" id="customer-section">
                        @php 
                            $default_customer = session('company.default_customer');
                        @endphp
                        <select name="customer_id" id="customer-select" class="form-select select2">
                            @foreach($customers as $customer)
                                <option 
                                    value="{{ $customer->id }}" 
                                    {{ $default_customer == $customer->id ? 'selected' : '' }}
                                >
                                    {{ $customer->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="customer-action-buttons">
                    <button class="pos-add-btn" id="pos-edit-customer-btn">
                        <i class="icon-base ti tabler-edit"></i>
                    </button>
                    <button type="button" class="pos-add-btn" id="pos-add-customer-btn" data-bs-toggle="modal" data-bs-target="#modal_pos_customer">
                        <i class="icon-base ti tabler-user-plus"></i>
                    </button>
                </div>
            </div>
            <div class="pos-cart-content">
                <div class="pos-cart-items thin-scroll" id="pos-cart-items">

                    <!-- Cart Items Table -->
                    <table class="pos-cart-table">
                        <thead>
                            <tr>
                                <th class="w-40">{{ __('Product') }}</th>
                                <th class="w-10">{{ __('Quantity') }}</th>
                                <th class="w-15">{{ __('Price') }}</th>
                                <th class="w-20">{{ __('Discount') }}</th>
                                <th class="w-15">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody id="pos-cart-items-tbody">
                            <!-- Cart items will be added dynamically -->
                        </tbody>
                    </table>

                    <!-- Empty Cart State (hidden by default) -->
                    <div class="pos-empty-cart" id="pos-empty-cart" style="display: none;">
                        <div class="pos-empty-cart-icon">
                            <i class="ti tabler-shopping-cart"></i>
                        </div>
                        <h5 class="mb-2">{{ __('Cart is empty') }}</h5>
                        <p class="text-muted">{{ __('Scan products or add them from the list') }}</p>
                    </div>
                </div>
                <div class="pos-cart-summary">
                    <div class="pos-summary-meta" role="group" aria-label="{{ __('Sale details') }}">
                        <label class="pos-summary-icon-field" for="pos-sale-date">
                            <span class="pos-summary-icon pos-summary-icon-calendar" aria-hidden="true"><i class="ti tabler-calendar"></i></span>
                            <input type="date" id="pos-sale-date" class="pos-summary-meta-input" name="pos_sale_date" value="{{ now()->format('Y-m-d') }}">
                        </label>
                        <label class="pos-summary-icon-field" for="pos-sale-note">
                            <span class="pos-summary-icon pos-summary-icon-note" aria-hidden="true"><i class="ti tabler-notes"></i></span>
                            <input type="text" id="pos-sale-note" class="pos-summary-meta-input" name="pos_sale_note" placeholder="{{ __('Note') }}" maxlength="1000" autocomplete="off">
                        </label>
                    </div>
                    <div class="pos-summary-lines">
                        <div class="pos-summary-row">
                            <span class="pos-summary-label">{{ __('Subtotal') }}:</span>
                            <span class="pos-summary-value" id="pos-subtotal">0.00</span>
                        </div>
                        <div class="pos-summary-row tax-summary-row">
                            <div class="pos-summary-label">
                                <span>{{ __('Tax') }}:</span>
                                <span class="tax-view-icon"><i class="ti tabler-eye tax-view-btn"></i></span>
                            </div>
                            <span class="pos-summary-value">
                                <span id="pos-tax">0.00</span>
                                <small class="text-muted ms-1" id="pos-tax-inclusive" style="display:none;"></small>
                            </span>
                        </div>
                        <div class="pos-summary-row">
                            <span class="pos-summary-label">{{ __('Discount') }}:</span>
                            <div class="discount-input-group">
                                <span class="show-total-discount-value"></span>
                                <select class="discount-type-select" id="pos-cart-discount-type">
                                    <option value="fixed">$</option>
                                    <option value="percentage">%</option>
                                </select>
                                <input type="text" class="pos-summary-input" id="pos-cart-discount-input" value="0" min="0">
                            </div>
                        </div>
                        <div class="pos-summary-row">
                            <span class="pos-summary-label">{{ __('Shipping') }}:</span>
                            <div class="pos-summary-input-group">
                                <input type="text" class="number-input pos-summary-input" id="pos-shipping-input" value="0">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="pos-total-amount">
                    <div class="total-payable">
                        <span class="pos-total-label">{{ __('Total Payable') }}:</span>
                        <span class="pos-total-value" id="pos-total-amount">0.00</span>
                    </div>
                    <div class="pos-total-amount-group">
                        <button class="btn btn-primary btn-sm pos-cash-register-btn" id="pos-cash-register-btn">
                            <i class="icon-base ti tabler-cash"></i>
                            <span class="xs-mobile-text">{{ __('Quick Cash') }}</span>
                        </button>
                        <button class="btn btn-primary btn-sm pos-cash-register-btn" id="pos-payment-btn">
                            <i class="icon-base ti tabler-dots"></i>
                            <span class="xs-mobile-text">{{ __('More') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Left Panel - Products -->
        <div class="pos-products-panel">
            <div class="pos-products-header">

                <div class="layout-type">
                    <select class="form-select form-select-sm select2" name="grocery_experience" id="pos-layout-select">
                        <option value="Regular" {{ session('company.grocery_experience') == 'Regular' ? 'selected' : '' }}>{{ __('Regular') }}</option>
                        <option value="Medicine" {{ session('company.grocery_experience') == 'Medicine' ? 'selected' : '' }}>{{ __('Medicine') }}</option>
                        <option value="Grocery" {{ session('company.grocery_experience') == 'Grocery' ? 'selected' : '' }}>{{ __('Grocery') }}</option>
                    </select>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap flex-grow-1">
                    <input type="text" class="pos-search-input form-control flex-grow-1" id="pos-product-search" placeholder="{{ __('Barcode') }} | {{ __('Name') }} | {{ __('Brand') }} | {{ __('Category') }} {{ session('company.grocery_experience') == 'Medicine' ? ' | ' . __('Generic Name') : '' }} | {{ __('IMEI') }}/{{ __('Serial') }}" autocomplete="off" spellcheck="false">
                    <div class="form-check mb-0 flex-shrink-0 d-flex align-items-center">
                        <input class="form-check-input m-0 me-1" type="checkbox" id="pos-search-by-imei-serial" title="{{ __('Search by IMEI or serial (IndexedDB)') }}">
                        <label class="form-check-label small text-nowrap mb-0" data-bs-toggle="tooltip" title="{{ __('Checked to Search by IMEI or Serial Number') }}" for="pos-search-by-imei-serial">{{ __('IMEI') }}/{{ __('Serial') }}</label>
                    </div>
                </div>
            </div>
            <!-- Products Content Area (Categories + Products Grid) -->
            <div class="pos-products-content">
                <!-- Categories -->
                <div class="pos-category-panel">
                    <div class="pos-category-header" data-category-id="all">
                        <i class="icon-base ti tabler-category me-1"></i> {{ __('All Categories') }}
                    </div>
                    <div class="pos-category-list thin-scroll" id="pos-category-list">
                        <!-- Category List -->
                        @foreach($categories as $category)
                            <div class="pos-category-item" data-category-id="{{ $category->id }}">
                                {{ $category->name }}
                            </div>
                        @endforeach
                    </div>
                </div>
                <!-- Products Grid -->
                <div class="pos-products-grid thin-scroll" id="pos-products-grid">
                    <div class="pos-loading-state" style="width: 100%; text-align: center; padding: 40px;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">{{ __('Loading') }}...</span>
                        </div>
                        <p class="mt-3 text-muted">{{ __('Loading products') }}...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Action Bar -->
    <div class="pos-footer-actions d-flex justify-content-between align-items-center">
        <div class="left-part">
            <div class="btn-group" role="group" aria-label="Basic example">
                <button type="button" class="add-hold-btn btn btn-warning waves-effect waves-light">
                    <i class="icon-base ti tabler-player-pause"></i>
                    <span class="mobile-text">{{ __('Add Hold') }}</span>
                </button>
                <button type="button" class="list-hold-btn btn btn-warning waves-effect waves-light">
                    <i class="icon-base ti tabler-droplet-pause"></i>
                    <span class="mobile-text">{{ __('List Hold') }}</span>
                </button>
            </div>
        
            <button class="pos-action-btn btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal_pos_sales">
                <i class="icon-base ti tabler-list"></i>
                <span class="mobile-text">{{ __('Sale List') }}</span>
            </button>
            <button class="pos-action-btn btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal_pos_sale_returns">
                <i class="icon-base ti tabler-arrow-left"></i>
                <span class="mobile-text">{{ __('Return') }}</span>
            </button>
            <button class="pos-action-btn btn btn-primary" id="pos-register-btn">
                <i class="icon-base ti tabler-cash-register"></i>
                <span class="mobile-text">{{ __('Register') }}</span>
            </button>
            <button class="pos-action-btn btn btn-danger clear-cart-btn">
                <i class="icon-base ti tabler-refresh"></i>
                <span class="mobile-text">{{ __('Clear') }}</span>
            </button>
        </div>
        <div class="right-part d-flex align-items-center gap-3">
            <div>
                <p class="text-body fw-medium mb-0">{{ __('Total Products') }}: <span id="pos-total-products-count">{{ number_format($totalProducts ?? 0) }}</span></p>
                <small class="text-danger" style="font-size: 10px;">Max 500 Products will be displayed.</small>
            </div>
            <div>
                <p class="loading-text mb-0">Products Loading...</p>
                <div id="pos-product-load-progress-wrap" class="d-none align-items-center gap-2" style="min-width: 200px;">
                    <div class="progress flex-grow-1" style="height: 15px; min-width: 120px;">
                        <div id="pos-product-load-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <span id="pos-product-load-progress-text" class="text-body small">0%</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('page-js')
@routes
<script>
    window.posOutletStateCode = @json(optional($outlet)->state_code);
    window.posTaxs = @json($taxs ?? []);
    window.posStates = @json($states->map(fn($s) => ['id' => $s->id, 'state_code' => $s->state_code])->values()->toArray());
    window.posCustomersWithState = @json($customers->keyBy('id')->map(fn($c) => ['id' => $c->id, 'state_id' => $c->state_id])->toArray());
</script>
@if(!empty($editSaleId))
<script>window.posEditSaleId = @json($editSaleId);</script>
@endif
<script src="{{ asset('public/pos_assets/js/pos_search.js') }}?v={{ @filemtime(public_path('pos_assets/js/pos_search.js')) ?: time() }}"></script>
@if(config('zatca.enabled', false))
<script src="{{ asset('public/pos_assets/js/pos_zatca.js') }}?v={{ @filemtime(public_path('pos_assets/js/pos_zatca.js')) ?: time() }}"></script>
@endif
@endpush
