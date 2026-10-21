@php
    $invConfig = session('company.invoice_configuration') ? json_decode(session('company.invoice_configuration'), true) : [];
    $invConfig = is_array($invConfig) ? $invConfig : [];
    $defaultHsn = $invConfig['default_hsn_code'] ?? '0000';
    $purchaseSubtotal = $purchase->purchaseDetails && $purchase->purchaseDetails->count() > 0
        ? $purchase->purchaseDetails->sum(fn ($d) => (float) $d->total)
        : 0;
    $discountRaw = $purchase->discount ?? '0';
    $discountDisplay = (strpos((string) $discountRaw, '%') !== false) ? $discountRaw : formatAmount($discountRaw);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Purchase') }}: {{ $purchase->reference_no }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('public/backend_assets/css/invoice-all.css') }}?v={{ @filemtime(public_path('backend_assets/css/invoice-all.css')) ?: time() }}" />
</head>
<body>
    <table class="header-table">
        <tr>
            <td width="50%" class="company-info">
                <h2 class="heading-title"><strong>{{ session('company.business_name', '') }}</strong></h2>
                @if($purchase->outlet)
                    <p>Outlet: {{ $purchase->outlet->outlet_name ?? '' }}</p>
                    <p>Phone: {{ $purchase->outlet->phone ?? '' }}</p>
                    <p>Email: {{ $purchase->outlet->email ?? '' }}</p>
                    <p>Address: {{ $purchase->outlet->address ?? '' }}</p>
                    @if(isEnableGST() && session('company.collect_tax') == 'Yes')
                        @if($purchase->outlet->state && ($purchase->outlet->state->state_name || $purchase->outlet->state->state_code))
                            <p>{{ __('Outlet State') }}: {{ $purchase->outlet->state->state_name ?? '' }} ({{ $purchase->outlet->state->state_code ?? '' }})</p>
                        @endif
                        @if($purchase->outlet->gstin)
                            <p>{{ __('Outlet GSTIN') }}: {{ $purchase->outlet->gstin }}</p>
                        @endif
                    @endif
                @endif
            </td>
            <td width="50%" class="invoice-info" style="text-align:center; vertical-align:middle;">
                <div>
                    @php $invoice_logo = session('company.invoice_logo'); @endphp
                    @if($invoice_logo)
                        <img src="{{ asset('public/uploads/site_settings/' . $invoice_logo) }}" alt="Logo" class="logo">
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <table style="margin-top:10px">
        <tr>
            <td width="50%" valign="top">
                <div class="heading-title"><strong>{{ __('Supplier') }} {{ __('Information') }}</strong></div>
                <table class="info-table">
                    @if($purchase->supplier)
                        @if($purchase->supplier->name)
                        <tr><td>{{ __('Name') }}</td><td>: {{ $purchase->supplier->name }}</td></tr>
                        @endif
                        @if($purchase->supplier->phone)
                        <tr><td>{{ __('Phone') }}</td><td>: {{ $purchase->supplier->phone }}</td></tr>
                        @endif
                        @if($purchase->supplier->email)
                        <tr><td>{{ __('Email') }}</td><td>: {{ $purchase->supplier->email }}</td></tr>
                        @endif
                        @if($purchase->supplier->address)
                        <tr><td>{{ __('Address') }}</td><td>: {{ $purchase->supplier->address }}</td></tr>
                        @endif
                    @endif
                </table>
            </td>
            <td width="50%" valign="top">
                <div class="heading-title"><strong>{{ __('Purchase') }} {{ __('Information') }}</strong></div>
                <table class="info-table">
                    @if($purchase->reference_no)
                    <tr><td>{{ __('Reference No') }}</td><td>: {{ $purchase->reference_no }}</td></tr>
                    @endif
                    @if($purchase->invoice_no)
                    <tr><td>{{ __('Supplier Invoice No') }}</td><td>: {{ $purchase->invoice_no }}</td></tr>
                    @endif
                    @if(!empty($purchase->date))
                    <tr><td>{{ __('Date') }}</td><td>: {{ formatDate($purchase->date) }}</td></tr>
                    @endif
                    @if($purchase->user)
                    <tr><td>{{ __('Purchased By') }}</td><td>: {{ $purchase->user->name }}</td></tr>
                    @endif
                    <tr><td>{{ __('Status') }}</td><td>: {{ $purchase->due_amount > 0 ? __('Due') : __('Paid') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items-table" style="margin-top:10px">
        <thead>
            <tr>
                <th width="5%" class="col-first">#</th>
                @if(isEnableGST() && isset($invConfig['show_hsn_code']) && $invConfig['show_hsn_code'] == 'Yes')
                <th width="6%">{{ __('HSN') }}</th>
                @endif
                <th width="{{ isEnableGST() && isset($invConfig['show_hsn_code']) && $invConfig['show_hsn_code'] == 'Yes' ? '32%' : '37%' }}">{{ __('Item') }}</th>
                <th width="15%">{{ __('IMEI/Serial') }}</th>
                <th width="8%" class="col-amount">{{ __('Qty') }}</th>
                <th width="12%" class="col-amount">{{ __('Unit Price') }}</th>
                <th width="12%" class="col-last">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @php $i = 0; @endphp
            @if($purchase->purchaseDetails && $purchase->purchaseDetails->count() > 0)
                @foreach($purchase->purchaseDetails as $detail)
                    @php
                        $i++;
                        $hsnCode = ($detail->item && $detail->item->hsn_code) ? $detail->item->hsn_code : ($defaultHsn ?? '0000');
                    @endphp
                    <tr>
                        <td class="col-first">{{ $i }}</td>
                        @if(isEnableGST() && isset($invConfig['show_hsn_code']) && $invConfig['show_hsn_code'] == 'Yes')
                        <td>{{ $hsnCode }}</td>
                        @endif
                        <td>
                            @if($detail->item)
                                @if($detail->item->parent_id && $detail->item->parent)
                                    {{ $detail->item->parent->name }} - {{ $detail->item->name }}
                                @else
                                    {{ $detail->item->name }}
                                @endif
                                @if($detail->item->code)
                                    ({{ $detail->item->code }})
                                @endif
                            @endif
                        </td>
                        <td>
                            @if($detail->expiry_imei_serial)
                                @if($detail->item && in_array($detail->item->type, ['IMEI_Product', 'Serial_Product']))
                                    {{ $detail->item->type === 'IMEI_Product' ? 'IMEI' : 'Serial' }}: {{ $detail->expiry_imei_serial }}
                                @elseif($detail->item && $detail->item->type === 'Medicine_Product')
                                    {{ $detail->expiry_imei_serial }}
                                @else
                                    {{ $detail->expiry_imei_serial }}
                                @endif
                            @else
                                -
                            @endif
                        </td>
                        <td class="col-amount">{{ formatQty($detail->quantity_amount ?? $detail->quantity ?? 0) }}</td>
                        <td class="col-amount">{{ formatAmount($detail->unit_price) }}</td>
                        <td class="col-last">{{ formatAmount($detail->total) }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <table style="margin-top:10px">
        <tr>
            <td width="60%" valign="top">
                @if($purchase->note)
                <div class="notes">
                    <strong>{{ __('Note') }}:</strong><br>
                    {{ $purchase->note }}
                </div>
                @endif
            </td>
            <td width="40%" valign="top">
                <table class="summary-table">
                    <tr>
                        <td>{{ __('Subtotal') }}</td>
                        <td class="text-right">{{ formatAmount($purchaseSubtotal) }}</td>
                    </tr>
                    <tr>
                        <td>{{ __('Discount') }}</td>
                        <td class="text-right">{{ $discountDisplay }}</td>
                    </tr>
                    <tr class="grand-total">
                        <td>{{ __('Grand Total') }}</td>
                        <td class="text-right">{{ formatAmount($purchase->grand_total) }}</td>
                    </tr>
                    <tr>
                        <td>{{ __('Paid Amount') }}</td>
                        <td class="text-right">{{ formatAmount($purchase->paid) }}</td>
                    </tr>
                    <tr>
                        <td>{{ __('Due Amount') }}</td>
                        <td class="text-right">{{ formatAmount($purchase->due_amount) }}</td>
                    </tr>
                    @if(isset($invConfig['show_payment_method']) && $invConfig['show_payment_method'] == 'Yes' && $purchase->purchasePayments && $purchase->purchasePayments->count() > 0)
                    <tr>
                        <td>{{ $invConfig['payment_method_label'] ?? __('Payment Method') }}</td>
                        <td></td>
                    </tr>
                    @foreach($purchase->purchasePayments as $payment)
                    <tr>
                        <td>{{ $payment->paymentMethod->name ?? __('N/A') }}</td>
                        <td class="text-right">{{ formatAmount($payment->amount) }}</td>
                    </tr>
                    @endforeach
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @if(session('company.term_conditions'))
    <div class="footer">
        {!! session('company.term_conditions') !!}
    </div>
    @endif

    @if(session('company.invoice_footer'))
    <div class="footer">
        {!! session('company.invoice_footer') !!}
    </div>
    @endif
</body>
</html>
