@php
    $invConfig = session('company.invoice_configuration') ? json_decode(session('company.invoice_configuration'), true) : [];
    $invConfig = is_array($invConfig) ? $invConfig : [];
    $outletStateCode = $sale->outlet && $sale->outlet->state ? ($sale->outlet->state->state_code ?? null) : null;
    $customerStateCode = $sale->customer && $sale->customer->state ? ($sale->customer->state->state_code ?? null) : null;
    $isIntraState = ($customerStateCode === null) || ($outletStateCode === null) || ($customerStateCode === $outletStateCode);
    $vatObjects = $sale->sale_vat_objects ? json_decode($sale->sale_vat_objects, true) : [];
    $vatObjects = is_array($vatObjects) ? $vatObjects : [];
    $summaryTaxes = [];
    foreach ($vatObjects as $v) {
        $taxName = $v['tax_field_type'] ?? $v['tax_field_name'] ?? '';
        if (!$taxName) continue;
        $amt = (float) ($v['tax_field_amount'] ?? 0);
        $pct = (float) ($v['tax_field_percentage'] ?? 0);
        if (!isset($summaryTaxes[$taxName])) {
            $summaryTaxes[$taxName] = ['amount' => 0, 'rate' => $pct];
        }
        $summaryTaxes[$taxName]['amount'] += $amt;
        if ($pct > 0 && $summaryTaxes[$taxName]['rate'] == 0) {
            $summaryTaxes[$taxName]['rate'] = $pct;
        }
    }
    $defaultHsn = $invConfig['default_hsn_code'] ?? '0000';
    $showImeiSerialCol = ($invConfig['show_product_imei_serial_number'] ?? 'No') === 'Yes';
    $totalItemDiscount = 0;
    $groupedInvoiceRows = [];
    if ($sale->saleDetails && $sale->saleDetails->count() > 0) {
        $keyOrder = [];
        $buckets = [];
        foreach ($sale->saleDetails as $detail) {
            $itemId = (int) ($detail->item_id ?? 0);
            $mu = (string) ($detail->menu_unit_price ?? '0');
            $mwd = (string) ($detail->menu_price_with_discount ?? '0');
            $itemType = $detail->item ? $detail->item->type : null;
            if ($itemType === 'Medicine_Product') {
                $key = $itemId . '|' . $mu . '|' . $mwd . '|med|' . md5((string) ($detail->expiry_imei_serial ?? ''));
            } else {
                $key = $itemId . '|' . $mu . '|' . $mwd;
            }
            if (! isset($buckets[$key])) {
                $buckets[$key] = [];
                $keyOrder[] = $key;
            }
            $buckets[$key][] = $detail;
        }
        foreach ($keyOrder as $key) {
            $groupedInvoiceRows[] = [
                'details' => $buckets[$key],
                'first' => $buckets[$key][0],
            ];
        }
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Sale No') }}: {{ $sale->sale_no }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('public/backend_assets/css/invoice-all.css') }}?v={{ @filemtime(public_path('backend_assets/css/invoice-all.css')) ?: time() }}" />
    <style>
        @media print {
            @page {
                size: 80mm auto !important;
                margin: 0;
            }
            body {
                width: 80mm !important;
                margin: 0;
                padding: 0;
            }
            .invoice-80mm {
                width: 80mm !important;
            }
        }
    </style>
</head>
<body class="invoice-56mm invoice-80mm">
    <!-- HEADER -->
    <table class="header-table">
        <tr>
            <td class="company-info">
                @php $invoice_logo = session('company.invoice_logo'); @endphp
                @if($invoice_logo)
                    <img src="{{ asset('public/uploads/site_settings/' . $invoice_logo) }}" alt="Logo" class="logo">
                @endif
                @if(($invConfig['show_business_name'] ?? 'Yes') == 'Yes')
                <h2 class="heading-title">{{ session('company.business_name', '') }}</h2>
                @php $bnAr = trim((string) ($invConfig['business_name_arabic'] ?? '')); @endphp
                @if($bnAr !== '')
                    <p dir="rtl" class="business-name-ar" style="font-size:0.95em;line-height:1.2;margin:2px 0 0;">{{ $bnAr }}</p>
                @endif
                @endif
                @if($sale->outlet)
                    <p>Outlet: {{ $sale->outlet->outlet_name ?? '' }}</p>
                    <p>Phone: {{ $sale->outlet->phone ?? '' }}</p>
                    <p>Email: {{ $sale->outlet->email ?? '' }}</p>
                    <p>Address: {{ $sale->outlet->address ?? '' }}</p>
                    @if(isEnableGST() && session('company.collect_tax') == 'Yes' && ($invConfig['show_business_tax_number'] ?? 'Yes') == 'Yes')
                        @if($sale->outlet->state && ($sale->outlet->state->state_name || $sale->outlet->state->state_code))
                            <p>{{ __('Outlet State') }}: {{ $sale->outlet->state->state_name ?? '' }} ({{ $sale->outlet->state->state_code ?? '' }})</p>
                        @endif
                        @if($sale->outlet->gstin)
                            <p>{{ __('Outlet GSTIN') }}: {{ $sale->outlet->gstin }}</p>
                        @endif
                    @endif
                @endif
            </td>
        </tr>
    </table>

    <!-- CUSTOMER + SALE INFO -->
    <table class="customer-sale-info" style="margin-top:10px">
        <tr>
            <td width="50%" valign="top">
                <div class="heading-title">{!! invoice_bilingual_label($invConfig['customer_label'] ?? __('Customer'), $invConfig['customer_label_arabic'] ?? '') !!}</div>
                <table class="info-table">
                    @if($sale->customer)
                        @if($sale->customer->name)
                        <tr><td>{{ __('Name') }}</td><td>: {{ $sale->customer->name }}</td></tr>
                        @endif
                        @if(($invConfig['show_customer_phone_number'] ?? 'Yes') == 'Yes' && $sale->customer->phone)
                        <tr><td>{{ __('Phone') }}</td><td>: {{ $sale->customer->phone }}</td></tr>
                        @endif
                        @if(($invConfig['show_customer_email'] ?? 'No') == 'Yes' && $sale->customer->email)
                        <tr><td>{{ __('Email') }}</td><td>: {{ $sale->customer->email }}</td></tr>
                        @endif
                        @if(($invConfig['show_customer_address'] ?? 'Yes') == 'Yes' && $sale->customer->address)
                        <tr><td>{{ __('Address') }}</td><td>: {{ $sale->customer->address }}</td></tr>
                        @endif
                        @if(isEnableGST() && session('company.collect_tax') == 'Yes')
                            @if($sale->customer->gst_number)
                            <tr><td>{{ __('GSTIN') }}</td><td>: {{ $sale->customer->gst_number }}</td></tr>
                            @endif
                            @if($sale->customer->state && ($sale->customer->state->state_name || $sale->customer->state->state_code))
                            <tr><td>{{ __('Customer State') }}</td><td>: {{ $sale->customer->state->state_name ?? '' }} ({{ $sale->customer->state->state_code ?? '' }})</td></tr>
                            @endif
                        @endif
                    @else
                        <tr><td>{{ __('Name') }}</td><td>: {{ __('Walk-in Customer') }}</td></tr>
                    @endif
                </table>
            </td>
            <td width="50%" valign="top">
                <div class="heading-title">{!! invoice_bilingual_label($invConfig['invoice_heading'] ?? __('Sale'), $invConfig['invoice_heading_arabic'] ?? '') !!}</div>
                <table class="info-table">
                    @if($sale->sale_no)
                    <tr><td>{!! invoice_bilingual_label($invConfig['invoice_no_label'] ?? __('Sale No'), $invConfig['invoice_no_label_arabic'] ?? '') !!}</td><td>: {{ $sale->sale_no }}@if(!empty($sale->random_code)) | {{ __('Offline Invoice No') }}: {{ $sale->random_code }}@endif</td></tr>
                    @endif
                    @if(!empty($sale->sale_date))
                    <tr><td>{{ __('Sale Date') }}</td><td>: {{ formatDate($sale->sale_date) }}</td></tr>
                    @endif
                    @if(!empty($sale->date_time))
                    <tr><td>{!! invoice_bilingual_label($invConfig['invoice_date_label'] ?? __('Invoice Date'), $invConfig['invoice_date_label_arabic'] ?? '') !!}</td><td>: {{ formatDateTime($sale->date_time) }}</td></tr>
                    @endif
                    @if($sale->employee)
                    <tr><td>{!! invoice_bilingual_label($invConfig['sales_person_label'] ?? __('Sales By'), $invConfig['sales_person_label_arabic'] ?? '') !!}</td><td>: {{ $sale->employee->name }}</td></tr>
                    @endif
                    <tr><td>{{ __('Status') }}</td><td>: {!! invoice_bilingual_label($sale->due_amount > 0 ? ($invConfig['invoice_heading_due'] ?? __('Due')) : ($invConfig['invoice_heading_paid'] ?? __('Paid')), '') !!}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ITEMS TABLE -->
    <table class="items-table" style="margin-top:10px">
        <thead>
            <tr>
                <th width="5%" class="col-first">#</th>
                @if(isEnableGST() && isset($invConfig['show_hsn_code']) && $invConfig['show_hsn_code'] == 'Yes')
                <th width="6%">{!! invoice_bilingual_label(__('HSN'), '') !!}</th>
                @endif
                <th width="{{ isEnableGST() && isset($invConfig['show_hsn_code']) && $invConfig['show_hsn_code'] == 'Yes' ? '40%' : '45%' }}">{!! invoice_bilingual_label($invConfig['item_label'] ?? __('Item'), $invConfig['item_label_arabic'] ?? '') !!}</th>
                <th width="8%" class="col-amount">{!! invoice_bilingual_label($invConfig['quantity_label'] ?? __('Qty'), $invConfig['quantity_label_arabic'] ?? '') !!}</th>
                <th width="12%" class="col-amount">{!! invoice_bilingual_label($invConfig['price_label'] ?? __('Unit Price'), $invConfig['price_label_arabic'] ?? '') !!}</th>
                <th width="12%" class="col-last">{!! invoice_bilingual_label($invConfig['total_label'] ?? __('Total'), $invConfig['total_label_arabic'] ?? '') !!}</th>
            </tr>
        </thead>
        <tbody>
            @php $i = 0; @endphp
            @if (!empty($groupedInvoiceRows))
                @foreach($groupedInvoiceRows as $group)
                    @php $i++; @endphp
                    @php
                        $detail = $group['first'];
                        $details = $group['details'];
                        $qty = 0;
                        $lineTotal = 0;
                        foreach ($details as $d) {
                            $qty += (float) $d->qty;
                            $lineTotal += (float) $d->qty * (float) $d->menu_price_with_discount;
                        }
                        $itemTaxType = $detail->item && $detail->item->tax_type ? $detail->item->tax_type : 'Inclusive';
                        $taxAgg = [];
                        foreach ($details as $d) {
                            $menuTaxes = $d->menu_taxes ? json_decode($d->menu_taxes, true) : [];
                            $menuTaxes = is_array($menuTaxes) ? $menuTaxes : [];
                            foreach ($menuTaxes as $t) {
                                $name = $t['tax_field_name'] ?? $t['tax_field_type'] ?? '';
                                $amt = (float) ($t['tax_field_amount'] ?? 0);
                                $pct = (float) ($t['tax_field_percentage'] ?? 0);
                                if ($name && $amt > 0) {
                                    $tk = $name . '|' . $pct;
                                    if (! isset($taxAgg[$tk])) {
                                        $taxAgg[$tk] = ['name' => $name, 'pct' => $pct, 'amt' => 0];
                                    }
                                    $taxAgg[$tk]['amt'] += $amt;
                                }
                            }
                        }
                        $detailTaxDisplay = '';
                        foreach ($taxAgg as $t) {
                            $detailTaxDisplay .= ($detailTaxDisplay ? ', ' : '') . $t['name'] . ($t['pct'] > 0 ? ' ' . $t['pct'] . '%' : '') . ' - ' . formatAmount($t['amt']);
                        }
                        if ($detailTaxDisplay && ! $isIntraState) {
                            $detailTaxDisplay .= ' (' . __($itemTaxType) . ')';
                        }
                        $hsnCode = ($detail->item && $detail->item->hsn_code) ? $detail->item->hsn_code : ($defaultHsn ?? '0000');
                    @endphp
                    <tr>
                        <td class="col-first">{{ $i }}</td>
                        @if(isEnableGST() && isset($invConfig['show_hsn_code']) && $invConfig['show_hsn_code'] == 'Yes')
                        <td>{{ $hsnCode }}</td>
                        @endif
                        <td>
                            @include('sale::sale.partials.invoice-item-line-name', ['detail' => $detail, 'invConfig' => $invConfig])
                            @if($showImeiSerialCol)
                            @include('sale::sale.partials.invoice-item-imei-expiry-below-name', ['group' => $group, 'invConfig' => $invConfig])
                            @endif
                            @include('sale::sale.partials.invoice-item-warranty-guarantee', ['detail' => $detail, 'invConfig' => $invConfig])
                            @include('sale::sale.partials.invoice-inline-discount-display', ['group' => $group, 'mode' => 'below_name'])
                        </td>
                        <td class="col-amount">{{ formatQty($qty) }}</td>
                        <td class="col-amount">{{ formatAmount($detail->menu_unit_price) }}</td>
                        <td class="col-last">{{ formatAmount($lineTotal) }}</td>
                    </tr>
                    @include('sale::sale.partials.invoice-combo-lines', [
                        'details' => $details,
                        'invConfig' => $invConfig,
                        'showHsn' => isEnableGST() && isset($invConfig['show_hsn_code']) && $invConfig['show_hsn_code'] == 'Yes',
                        'showImeiSerialCol' => false,
                        'showAmountColumns' => true,
                        'showTaxColumns' => false,
                        'showDiscountColumn' => false,
                    ])
                @endforeach
            @endif
        </tbody>
    </table>

    <!-- SUMMARY -->
    <table style="margin-top:10px">
        <tr>
            <td valign="top">
                <table class="summary-table">
                    <tr>
                        <td>{!! invoice_bilingual_label($invConfig['subtotal_label'] ?? __('Subtotal'), $invConfig['subtotal_label_arabic'] ?? '') !!}</td>
                        <td>{{ formatAmount($sale->sub_total) }}</td>
                    </tr>
                    @include('sale::sale.partials.invoice-order-discount-summary-row', ['sale' => $sale, 'invConfig' => $invConfig, 'amountClass' => ''])
                    @if($sale->total_discount_amount > 0 && (float)($sale->sub_total_discount_amount ?? 0) <= 0 && (float)($sale->total_item_discount_amount ?? 0) <= 0)
                    <tr>
                        <td>{!! invoice_bilingual_label($invConfig['discount_label'] ?? __('Discount'), $invConfig['discount_label_arabic'] ?? '') !!}</td>
                        <td>{{ formatAmount($sale->total_discount_amount) }}</td>
                    </tr>
                    @endif
                    @foreach($summaryTaxes ?? [] as $taxName => $taxData)
                        @if(($taxData['amount'] ?? 0) > 0)
                    <tr>
                        <td>{{ $taxName }}{{ ($taxData['rate'] ?? 0) > 0 ? ' (' . $taxData['rate'] . '%)' : '' }}</td>
                        <td>{{ formatAmount($taxData['amount']) }}</td>
                    </tr>
                        @endif
                    @endforeach
                    @if($sale->delivery_charge > 0)
                    <tr>
                        <td>{{ __('Delivery Charge') }}</td>
                        <td>{{ formatAmount($sale->delivery_charge) }}</td>
                    </tr>
                    @endif
                    <tr class="grand-total">
                        <td>{!! invoice_bilingual_label($invConfig['total_payable_label'] ?? __('Grand Total'), $invConfig['total_payable_label_arabic'] ?? '') !!}</td>
                        <td>{{ formatAmount($sale->total_payable ?? $sale->grand_total) }}</td>
                    </tr>
                    @include('sale::sale.partials.invoice-grand-total-words', ['sale' => $sale, 'invConfig' => $invConfig, 'colspan' => 2])
                    @if($sale->previous_due > 0)
                    <tr>
                        <td>{!! invoice_bilingual_label($invConfig['previous_balance_label'] ?? __('Previous Due Amount'), $invConfig['previous_balance_label_arabic'] ?? '') !!}</td>
                        <td>{{ formatAmount($sale->previous_due ?? 0) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td>{!! invoice_bilingual_label($invConfig['paid_amount_label'] ?? __('Paid Amount'), $invConfig['paid_amount_label_arabic'] ?? '') !!}</td>
                        <td>{{ formatAmount($sale->paid_amount) }}</td>
                    </tr>
                    @include('sale::sale.partials.invoice-given-change-rows', ['sale' => $sale, 'invConfig' => $invConfig])
                    @if($sale->due_amount > 0)
                    <tr>
                        <td>{!! invoice_bilingual_label($invConfig['due_amount_label'] ?? __('Due Amount'), $invConfig['due_amount_label_arabic'] ?? '') !!}</td>
                        <td>{{ formatAmount($sale->due_amount) }}</td>
                    </tr>
                    @endif
                    @if(isset($invConfig['show_payment_method']) && $invConfig['show_payment_method'] == 'Yes' && $sale->salePayments && $sale->salePayments->count() > 0)
                    <tr>
                        <td>{!! invoice_bilingual_label($invConfig['payment_method_label'] ?? __('Payment Method'), $invConfig['payment_method_label_arabic'] ?? '') !!}</td>
                        <td></td>
                    </tr>
                    @foreach($sale->salePayments as $payment)
                    <tr>
                        <td>{{ $payment->paymentMethod->name ?? __('Cash') }}</td>
                        <td>@include('sale::sale.partials.invoice-sale-payment-amount', ['payment' => $payment])</td>
                    </tr>
                    @endforeach
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <!-- FOOTER -->
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

    @include('sale::sale.partials.invoice-multi-currency-payment-notes', ['sale' => $sale])

    @include('sale::sale.partials.invoice-qr', [
        'sale' => $sale,
        'invConfig' => $invConfig,
        'businessName' => session('company.business_name', ''),
    ])

    <div style="text-align: center; margin-top: 30px;">
        <!-- add tailwind button class -->
        <button onclick="window.print();" type="button" class="print-btn px-6 py-3 rounded-xl bg-indigo-600 text-white font-medium hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">{{ __('Print') }}</button>
    </div>

    @include('sale::sale.partials.invoice-popup-print-autoclose')
</body>
</html>
