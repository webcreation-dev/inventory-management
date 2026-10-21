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
    <link rel="stylesheet" href="{{ asset('public/backend_assets/css/invoice-all.css') }}" />
</head>
<body>
    <!-- HEADER -->
    <table class="header-table">
        <tr>
            <td width="50%" class="company-info">
                <h2 class="heading-title">{{ session('company.business_name', '') }}</h2>
                @if($sale->outlet)
                    <p>Outlet: {{ $sale->outlet->outlet_name ?? '' }}</p>
                    <p>Phone: {{ $sale->outlet->phone ?? '' }}</p>
                    <p>Email: {{ $sale->outlet->email ?? '' }}</p>
                    <p>Address: {{ $sale->outlet->address ?? '' }}</p>
                    @if(isEnableGST() && session('company.collect_tax') == 'Yes')
                        @if($sale->outlet->state && ($sale->outlet->state->state_name || $sale->outlet->state->state_code))
                            <p>{{ __('Outlet State') }}: {{ $sale->outlet->state->state_name ?? '' }} ({{ $sale->outlet->state->state_code ?? '' }})</p>
                        @endif
                        @if($sale->outlet->gstin)
                            <p>{{ __('Outlet GSTIN') }}: {{ $sale->outlet->gstin }}</p>
                        @endif
                    @endif
                @endif
            </td>
            <td width="50%" class="invoice-info" valign="center">
                <div>
                    @php $invoice_logo = session('company.invoice_logo'); @endphp
                    @if($invoice_logo)
                        <img src="{{ asset('public/uploads/site_settings/' . $invoice_logo) }}" alt="Logo" class="logo">
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <!-- CUSTOMER + SALE INFO -->
    <table style="margin-top:10px">
        <tr>
            <td width="50%" valign="top">
                <div class="heading-title">{!! invoice_bilingual_label(($invConfig['customer_label'] ?? __('Customer')).' '.__('Information'), $invConfig['customer_label_arabic'] ?? '') !!}</div>
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
                        @if($sale->customer->address)
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
                <div class="heading-title">{!! invoice_bilingual_label(($invConfig['invoice_heading'] ?? __('Sale')).' '.__('Information'), $invConfig['invoice_heading_arabic'] ?? '') !!}</div>
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
                <th width="5%">#</th>
                @if(isEnableGST() && isset($invConfig['show_hsn_code']) && $invConfig['show_hsn_code'] == 'Yes')
                <th width="6%">{!! invoice_bilingual_label(__('HSN'), '') !!}</th>
                @endif
                <th width="{{ isEnableGST() && isset($invConfig['show_hsn_code']) && $invConfig['show_hsn_code'] == 'Yes' ? '22%' : '25%' }}">{!! invoice_bilingual_label($invConfig['item_label'] ?? __('Item'), $invConfig['item_label_arabic'] ?? '') !!}</th>
                @if($showImeiSerialCol)
                <th width="15%">{!! invoice_bilingual_label($invConfig['serial_no_label'] ?? __('IMEI/Serial'), $invConfig['serial_no_label_arabic'] ?? '') !!}</th>
                @endif
                <th width="8%" class="text-center">{!! invoice_bilingual_label($invConfig['quantity_label'] ?? __('Qty'), $invConfig['quantity_label_arabic'] ?? '') !!}</th>
            </tr>
        </thead>
        <tbody>
            @php $i = 0; @endphp
            @if (!empty($groupedInvoiceRows))
                @foreach($groupedInvoiceRows as $group)
                    @php
                        $i++;
                        $detail = $group['first'];
                        $details = $group['details'];
                        $qty = 0;
                        foreach ($details as $d) {
                            $qty += (float) $d->qty;
                        }
                        $hsnCode = ($detail->item && $detail->item->hsn_code) ? $detail->item->hsn_code : ($defaultHsn ?? '0000');
                    @endphp
                    <tr>
                        <td>{{ $i }}</td>
                        @if(isEnableGST() && isset($invConfig['show_hsn_code']) && $invConfig['show_hsn_code'] == 'Yes')
                        <td>{{ $hsnCode }}</td>
                        @endif
                        <td>@include('sale::sale.partials.invoice-item-line-name', ['detail' => $detail, 'invConfig' => $invConfig])</td>
                        @if($showImeiSerialCol)
                        <td>@include('sale::sale.partials.invoice-item-imei-cell', ['group' => $group, 'invConfig' => $invConfig])</td>
                        @endif
                        <td class="num text-center">{{ formatQty($qty) }}</td>
                    </tr>
                    @include('sale::sale.partials.invoice-combo-lines', [
                        'details' => $details,
                        'invConfig' => $invConfig,
                        'showHsn' => isEnableGST() && isset($invConfig['show_hsn_code']) && $invConfig['show_hsn_code'] == 'Yes',
                        'showImeiSerialCol' => $showImeiSerialCol,
                        'showAmountColumns' => false,
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
            <td width="60%" valign="top">
                @if($sale->note)
                <div class="notes">
                    <strong>{{ __('Note') }}:</strong><br>
                    {{ $sale->note }}
                </div>
                @endif
            </td>
            <td width="40%" valign="top">
            </td>
        </tr>
    </table>


    {{-- Zatca QR Code --}}
    @if($sale->zatca_phase1_qr_code)
        @php
            $qrData = base64_decode($sale->zatca_phase1_qr_code);
            $qrDataEncoded = urlencode($qrData);
            $qrCodeImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . $qrDataEncoded;
        @endphp
    <div style="text-align: center; margin-top: 20px;">
        <p style="font-weight: 600; margin-bottom: 10px;">{{ __('ZATCA QR Code') }}</p>
        <img src="{{ $qrCodeImageUrl }}" alt="ZATCA QR Code" style="max-width: 150px; height: auto;">
    </div>
    @endif

    <div style="text-align: center; margin-top: 30px;">
        <!-- add tailwind button class -->
        <button onclick="window.print();" type="button" class="print-btn  px-6 py-3 rounded-xl bg-indigo-600 text-white font-medium hover:bg-indigo-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">{{ __('Print') }}</button>
    </div>

    @include('sale::sale.partials.invoice-popup-print-autoclose')
</body>
</html>
