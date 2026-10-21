@extends('backend.backend_layout')
@section('page-title', __('Online Order') . ' ' . __('Details'))
@section('page-content')
@php
    $orderStatus = $sale->order_status ?: 'Pending';
    $isPending = $orderStatus === 'Pending';
    $isDelivered = $isDelivered ?? ($orderStatus === 'Delivered');
    $canUpdateFulfillment = $canUpdateFulfillment ?? in_array($orderStatus, ['Confirmed', 'On The Way'], true);
    $availableImei = $availableImei ?? [];
    $defaultImage = asset('public/uploads/dummy_images/default-picture.png');
    $needsImei = false;
    foreach ($sale->saleDetails as $d) {
        $t = (string) ($d->item->type ?? $d->item_type ?? '');
        if (in_array($t, ['IMEI_Product', 'Serial_Product'], true)) {
            $needsImei = true;
            break;
        }
    }

    $orderBadge = [
        'Pending' => 'bg-label-warning',
        'Confirmed' => 'bg-label-primary',
        'Rejected' => 'bg-label-danger',
        'On The Way' => 'bg-label-info',
        'Delivered' => 'bg-label-success',
        'Cancelled' => 'bg-label-secondary',
        'Returned' => 'bg-label-secondary',
    ];
    $paymentBadge = [
        'Unpaid' => 'bg-label-danger',
        'Partial' => 'bg-label-warning',
        'Paid' => 'bg-label-success',
        'Refunded' => 'bg-label-secondary',
    ];
@endphp
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-6 row-gap-4">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1">{{ __('Online Order') }} {{ __('Details') }}</h4>
        </div>
        @include('backend.components.breadcrumb', [
            'breadcrumbs' => [
                [
                    'label' => '<i class="ti tabler-home"></i>',
                    'link' => route('dashboard')
                ],
                [
                    'label' => __('Online Order'),
                    'link' => route('online-order.index')
                ],
                [
                    'label' => __('Details'),
                    'active' => true
                ]
            ]
        ])
    </div>

    @if(session('success'))
        {!! insertSuccess(session('success')) !!}
    @endif
    @if(session('error'))
        {!! insertFailed(session('error')) !!}
    @endif

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="mb-0">{{ __('Order Details') }}</h5>
            <a href="{{ route('online-order.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="ti tabler-arrow-left me-1"></i>{{ __('Back') }}
            </a>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-4">
                    <h6 class="text-uppercase text-muted small mb-3">{{ __('Order Information') }}</h6>
                    <div class="mb-3">
                        <div class="text-muted small">{{ __('Order No') }}</div>
                        <div class="fw-semibold">{{ $sale->sale_no }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">{{ __('Order Date') }}</div>
                        <div class="fw-semibold">{{ $sale->order_date_time ? formatDateTime($sale->order_date_time) : formatDate($sale->sale_date) }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">{{ __('Fulfillment') }}</div>
                        <div class="fw-semibold">{{ __($sale->charge_type ?: '-') }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">{{ __('Payment Method') }}</div>
                        <div class="fw-semibold">{{ $sale->account_note ?: ($sale->account_type ?: '-') }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small mb-1">{{ __('Order Status') }}</div>
                        <span class="badge {{ $orderBadge[$orderStatus] ?? 'bg-label-secondary' }}">{{ __($orderStatus) }}</span>
                    </div>
                    <div class="mb-0">
                        <div class="text-muted small mb-1">{{ __('Payment Status') }}</div>
                        <span class="badge {{ $paymentBadge[$paymentStatus] ?? 'bg-label-secondary' }}">{{ __($paymentStatus) }}</span>
                    </div>
                </div>

                <div class="col-md-4 border-start-md">
                    <h6 class="text-uppercase text-muted small mb-3">{{ __('Customer Information') }}</h6>
                    <div class="mb-3">
                        <div class="text-muted small">{{ __('Customer') }}</div>
                        <div class="fw-semibold">{{ $sale->customer?->name ?: __('Walk-in Customer') }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">{{ __('Phone') }}</div>
                        <div class="fw-semibold">{{ $sale->customer?->phone ?: '-' }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">{{ __('Email') }}</div>
                        <div class="fw-semibold">{{ $sale->customer?->email ?: '-' }}</div>
                    </div>
                    @if($sale->note)
                    <div class="mb-0">
                        <div class="text-muted small">{{ __('Note') }} / {{ __('Address') }}</div>
                        <div>{{ $sale->note }}</div>
                    </div>
                    @endif
                </div>

                <div class="col-md-4 border-start-md">
                    <h6 class="text-uppercase text-muted small mb-3">{{ __('Actions') }}</h6>

                    @if($isDelivered)
                        <div class="alert alert-success mb-0 py-2">
                            {{ __('This order is delivered. Status cannot be changed.') }}
                        </div>
                    @elseif($isPending)
                        <p class="text-muted small mb-3">{{ __('Accept or reject this pending order.') }}</p>
                        <div class="d-grid gap-2">
                            <form method="POST" action="{{ route('online-order.accept', $sale->encrypted_id) }}">
                                @csrf
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="ti tabler-check me-1"></i>{{ __('Accept') }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('online-order.reject', $sale->encrypted_id) }}" onsubmit="return confirm('{{ __('Are you sure you want to reject this order?') }}')">
                                @csrf
                                <button type="submit" class="btn btn-danger w-100">
                                    <i class="ti tabler-x me-1"></i>{{ __('Reject') }}
                                </button>
                            </form>
                        </div>
                    @elseif($canUpdateFulfillment)
                        <form method="POST" action="{{ route('online-order.update-status', $sale->encrypted_id) }}">
                            @csrf
                            @method('PUT')
                            <label class="form-label" for="order_status">{{ __('Change Status') }}</label>
                            <select name="order_status" id="order_status" class="form-select mb-3">
                                @foreach($fulfillmentStatuses as $status)
                                    <option value="{{ $status }}" @selected($orderStatus === $status)>{{ __($status) }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="ti tabler-device-floppy me-1"></i>{{ __('Update Status') }}
                            </button>
                        </form>
                    @elseif($orderStatus === 'Rejected')
                        <div class="alert alert-danger mb-0 py-2">
                            {{ __('This order was rejected.') }}
                        </div>
                    @elseif($orderStatus === 'Cancelled')
                        <div class="alert alert-secondary mb-0 py-2">
                            {{ __('This order was cancelled by the customer.') }}
                        </div>
                    @else
                        <div class="alert alert-secondary mb-0 py-2">
                            {{ __('No further actions available for this order.') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="mb-0">{{ __('Products') }}</h5>
                    @if($needsImei && ! $isDelivered)
                        <span class="text-muted small">{{ __('Select IMEI/Serial for IMEI or Serial products.') }}</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($needsImei && ! $isDelivered)
                        <form method="POST" action="{{ route('online-order.update-imei', $sale->encrypted_id) }}">
                            @csrf
                            @method('PUT')
                    @endif

                    @forelse($sale->saleDetails as $detail)
                        @php
                            $item = $detail->item;
                            $name = $detail->menu_note
                                ?: ($item?->name ?? __('Item'));
                            $photo = $item && $item->photo
                                ? asset('public/uploads/items/' . $item->photo)
                                : $defaultImage;
                            $lineTotal = (float) $detail->qty * (float) $detail->menu_price_with_discount;
                            $itemType = (string) ($item->type ?? $detail->item_type ?? '');
                            $isImeiLine = in_array($itemType, ['IMEI_Product', 'Serial_Product'], true);
                            $imeiOptions = $availableImei[$detail->id] ?? [];
                            $label = $itemType === 'Serial_Product' ? __('Serial Number') : __('IMEI Number');
                        @endphp
                        <div class="d-flex gap-3 align-items-start {{ !$loop->last ? 'border-bottom pb-3 mb-3' : '' }}">
                            <img src="{{ $photo }}" alt="{{ $name }}" class="rounded border" width="72" height="72" style="object-fit: cover;">
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $name }}</div>
                                @if($item && $item->code)
                                    <div class="text-muted small">{{ __('Code') }}: {{ $item->code }}</div>
                                @endif
                                <div class="text-muted small mt-1">
                                    {{ formatAmount($detail->menu_unit_price) }} × {{ rtrim(rtrim(number_format((float) $detail->qty, 3, '.', ''), '0'), '.') }}
                                </div>
                                @if($isImeiLine)
                                    <div class="mt-2" style="max-width: 320px;">
                                        <label class="form-label small mb-1">{{ $label }}</label>
                                        @if($isDelivered)
                                            <div class="fw-semibold">{{ $detail->expiry_imei_serial ?: '-' }}</div>
                                        @else
                                            <select name="imei[{{ $detail->id }}]" class="form-select form-select-sm">
                                                <option value="">{{ __('Select') }} {{ $label }}</option>
                                                @foreach($imeiOptions as $serial)
                                                    <option value="{{ $serial }}" @selected((string) $detail->expiry_imei_serial === (string) $serial)>{{ $serial }}</option>
                                                @endforeach
                                            </select>
                                            @if(empty($imeiOptions))
                                                <div class="text-danger small mt-1">{{ __('No available IMEI/Serial found for this outlet.') }}</div>
                                            @endif
                                        @endif
                                    </div>
                                @elseif($detail->expiry_imei_serial)
                                    <div class="text-muted small mt-1">{{ __('IMEI/Serial') }}: {{ $detail->expiry_imei_serial }}</div>
                                @endif
                            </div>
                            <div class="fw-semibold text-nowrap">{{ formatAmount($lineTotal) }}</div>
                        </div>
                    @empty
                        <div class="text-muted">{{ __('No products found.') }}</div>
                    @endforelse

                    @if($needsImei && ! $isDelivered)
                            <div class="pt-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti tabler-device-floppy me-1"></i>{{ __('Save IMEI / Serial') }}
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Summary') }}</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>{{ __('Subtotal') }}</span>
                        <span>{{ formatAmount($sale->sub_total) }}</span>
                    </div>
                    @if((float) $sale->total_discount_amount > 0)
                    <div class="d-flex justify-content-between mb-2">
                        <span>{{ __('Discount') }}</span>
                        <span>- {{ formatAmount($sale->total_discount_amount) }}</span>
                    </div>
                    @endif
                    @if((float) $sale->vat > 0)
                    <div class="d-flex justify-content-between mb-2">
                        <span>{{ __('Tax') }}</span>
                        <span>{{ formatAmount($sale->vat) }}</span>
                    </div>
                    @endif
                    @if((float) $sale->delivery_charge > 0)
                    <div class="d-flex justify-content-between mb-2">
                        <span>{{ __('Delivery Charge') }}</span>
                        <span>{{ formatAmount($sale->delivery_charge) }}</span>
                    </div>
                    @endif
                    <hr>
                    <div class="d-flex justify-content-between mb-2 fw-semibold">
                        <span>{{ __('Grand Total') }}</span>
                        <span>{{ formatAmount($sale->grand_total) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>{{ __('Paid Amount') }}</span>
                        <span>{{ formatAmount($sale->paid_amount) }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>{{ __('Due') }}</span>
                        <span>{{ formatAmount($sale->due_amount) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
