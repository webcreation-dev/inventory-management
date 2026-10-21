@php
    $invConfig = session('company.invoice_configuration') ? json_decode(session('company.invoice_configuration'), true) : [];
    $invConfig = is_array($invConfig) ? $invConfig : [];
    $outletStateCode = $quotation->outlet && $quotation->outlet->state ? ($quotation->outlet->state->state_code ?? null) : null;
    $customerStateCode = $quotation->customer && $quotation->customer->state ? ($quotation->customer->state->state_code ?? null) : null;
    $isIntraState = ($customerStateCode === null) || ($outletStateCode === null) || ($customerStateCode === $outletStateCode);
    $defaultHsn = $invConfig['default_hsn_code'] ?? '0000';
    $showImeiSerialCol = ($invConfig['show_product_imei_serial_number'] ?? 'No') === 'Yes';
    $quotationSubTotal = 0.0;
    if ($quotation->quotationDetails && $quotation->quotationDetails->count() > 0) {
        foreach ($quotation->quotationDetails as $__qd) {
            $quotationSubTotal += (float) ($__qd->total ?? 0);
        }
    }
    $discountRaw = $quotation->discount ?? '0';
    $discountSummaryDisplay = (strpos((string) $discountRaw, '%') !== false) ? (string) $discountRaw : formatAmount((float) $discountRaw);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Quotation No') }}: {{ $quotation->reference_no }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('public/backend_assets/css/invoice-all.css') }}?v={{ @filemtime(public_path('backend_assets/css/invoice-all.css')) ?: time() }}" />
</head>
<body>
    <!-- HEADER (mirror sale PDF: company left, logo right) -->
    <table class="header-table">
        <tr>
            <td width="50%" class="company-info">
                @if(($invConfig['show_business_name'] ?? 'Yes') == 'Yes')
                <h2 class="heading-title"><strong>{{ session('company.business_name', '') }}</strong></h2>
                @php $bnAr = trim((string) ($invConfig['business_name_arabic'] ?? '')); @endphp
                @if($bnAr !== '')
                    <p dir="rtl" class="business-name-ar" style="font-size:0.95em;line-height:1.2;margin:4px 0 0;"><strong>{{ $bnAr }}</strong></p>
                @endif
                @endif
                @if($quotation->outlet)
                    <p>Outlet: {{ $quotation->outlet->outlet_name ?? '' }}</p>
                    <p>Phone: {{ $quotation->outlet->phone ?? '' }}</p>
                    <p>Email: {{ $quotation->outlet->email ?? '' }}</p>
                    <p>Address: {{ $quotation->outlet->address ?? '' }}</p>
                    @if(isEnableGST() && session('company.collect_tax') == 'Yes' && ($invConfig['show_business_tax_number'] ?? 'Yes') == 'Yes')
                        @if($quotation->outlet->state && ($quotation->outlet->state->state_name || $quotation->outlet->state->state_code))
                            <p>{{ __('Outlet State') }}: {{ $quotation->outlet->state->state_name ?? '' }} ({{ $quotation->outlet->state->state_code ?? '' }})</p>
                        @endif
                        @if($quotation->outlet->gstin)
                            <p>{{ __('Outlet GSTIN') }}: {{ $quotation->outlet->gstin }}</p>
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

    <!-- CUSTOMER + QUOTATION INFO -->
    <table style="margin-top:10px">
        <tr>
            <td width="50%" valign="top">
                <div class="heading-title"><strong>{!! invoice_bilingual_label(($invConfig['customer_label'] ?? __('Customer')).' '.__('Information'), $invConfig['customer_label_arabic'] ?? '') !!}</strong></div>
                <table class="info-table">
                    @if($quotation->customer)
                        @if($quotation->customer->name)
                        <tr><td>{{ __('Name') }}</td><td>: {{ $quotation->customer->name }}</td></tr>
                        @endif
                        @if(($invConfig['show_customer_phone_number'] ?? 'Yes') == 'Yes' && $quotation->customer->phone)
                        <tr><td>{{ __('Phone') }}</td><td>: {{ $quotation->customer->phone }}</td></tr>
                        @endif
                        @if(($invConfig['show_customer_email'] ?? 'No') == 'Yes' && $quotation->customer->email)
                        <tr><td>{{ __('Email') }}</td><td>: {{ $quotation->customer->email }}</td></tr>
                        @endif
                        @if(($invConfig['show_customer_address'] ?? 'Yes') == 'Yes' && $quotation->customer->address)
                        <tr><td>{{ __('Address') }}</td><td>: {{ $quotation->customer->address }}</td></tr>
                        @endif
                        @if(isEnableGST() && session('company.collect_tax') == 'Yes')
                            @if($quotation->customer->gst_number)
                            <tr><td>{{ __('GSTIN') }}</td><td>: {{ $quotation->customer->gst_number }}</td></tr>
                            @endif
                            @if($quotation->customer->state && ($quotation->customer->state->state_name || $quotation->customer->state->state_code))
                            <tr><td>{{ __('Customer State') }}</td><td>: {{ $quotation->customer->state->state_name ?? '' }} ({{ $quotation->customer->state->state_code ?? '' }})</td></tr>
                            @endif
                        @endif
                    @endif
                </table>
            </td>
            <td width="50%" valign="top">
                <div class="heading-title"><strong>{!! invoice_bilingual_label(__('Quotation').' '.__('Information'), '') !!}</strong></div>
                <table class="info-table">
                    @if($quotation->reference_no)
                    <tr><td>{{ __('Reference No') }}</td><td>: {{ $quotation->reference_no }}</td></tr>
                    @endif
                    @if(!empty($quotation->date))
                    <tr><td>{{ __('Date') }}</td><td>: {{ formatDate($quotation->date) }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <!-- ITEMS TABLE (mirror sale PDF: line items only, no embedded summary / tfoot) -->
    <table class="items-table" style="margin-top:10px">
        <thead>
            <tr>
                <th width="5%" class="col-first">#</th>
                @if(isEnableGST() && isset($invConfig['show_hsn_code']) && $invConfig['show_hsn_code'] == 'Yes')
                <th width="6%">{!! invoice_bilingual_label(__('HSN'), '') !!}</th>
                @endif
                <th width="{{ isEnableGST() && isset($invConfig['show_hsn_code']) && $invConfig['show_hsn_code'] == 'Yes' ? '22%' : '25%' }}">{!! invoice_bilingual_label($invConfig['item_label'] ?? __('Item'), $invConfig['item_label_arabic'] ?? '') !!}</th>
                @if($showImeiSerialCol)
                <th width="15%">{!! invoice_bilingual_label($invConfig['serial_no_label'] ?? __('IMEI/Serial'), $invConfig['serial_no_label_arabic'] ?? '') !!}</th>
                @endif
                <th width="8%" class="col-amount">{!! invoice_bilingual_label($invConfig['quantity_label'] ?? __('Qty'), $invConfig['quantity_label_arabic'] ?? '') !!}</th>
                <th width="12%" class="col-amount">{!! invoice_bilingual_label($invConfig['price_label'] ?? __('Unit Price'), $invConfig['price_label_arabic'] ?? '') !!}</th>
                @if(session('company.collect_tax') == 'Yes')
                <th width="12%" class="col-amount">{!! invoice_bilingual_label($invConfig['tax_label'] ?? __('Tax'), $invConfig['tax_label_arabic'] ?? '') !!}</th>
                @if($isIntraState)
                <th width="8%">{{ __('Tax Type') }}</th>
                @endif
                @endif
                <th width="12%" class="col-amount">{!! invoice_bilingual_label($invConfig['item_discount_label'] ?? __('Discount'), $invConfig['item_discount_label_arabic'] ?? '') !!}</th>
                <th width="12%" class="col-last">{!! invoice_bilingual_label($invConfig['total_label'] ?? __('Total'), $invConfig['total_label_arabic'] ?? '') !!}</th>
            </tr>
        </thead>
        <tbody>
            @php $i = 0; @endphp
            @if($quotation->quotationDetails && $quotation->quotationDetails->count() > 0)
                @foreach($quotation->quotationDetails as $detail)
                    @php
                        $i++;
                        $group = ['details' => [$detail], 'first' => $detail];
                        $hsnCode = ($detail->item && $detail->item->hsn_code) ? $detail->item->hsn_code : ($defaultHsn ?? '0000');
                        $itemTaxType = $detail->item && $detail->item->tax_type ? $detail->item->tax_type : 'Inclusive';
                    @endphp
                    <tr>
                        <td class="col-first">{{ $i }}</td>
                        @if(isEnableGST() && isset($invConfig['show_hsn_code']) && $invConfig['show_hsn_code'] == 'Yes')
                        <td>{{ $hsnCode }}</td>
                        @endif
                        <td>
                            @include('sale::sale.partials.invoice-item-line-name', ['detail' => $detail, 'invConfig' => $invConfig])
                            @include('sale::sale.partials.invoice-item-warranty-guarantee', ['detail' => $detail, 'invConfig' => $invConfig])
                        </td>
                        @if($showImeiSerialCol)
                        <td>
                            @include('sale::sale.partials.invoice-item-imei-cell', ['group' => $group, 'invConfig' => $invConfig])
                        </td>
                        @endif
                        <td class="col-amount">{{ formatQty((float) $detail->quantity) }}</td>
                        <td class="col-amount">{{ formatAmount($detail->unit_price) }}</td>
                        @if(session('company.collect_tax') == 'Yes')
                        <td class="col-amount">-</td>
                        @if($isIntraState)
                        <td>{{ __($itemTaxType) }}</td>
                        @endif
                        @endif
                        <td class="col-amount">@include('sale::sale.partials.invoice-inline-discount-display', ['group' => $group])</td>
                        <td class="col-last">{{ formatAmount($detail->total) }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <!-- SUMMARY (separate block like sale PDF) -->
    <table style="margin-top:10px">
        <tr>
            <td width="60%" valign="top">
                @if($quotation->note)
                <div class="notes">
                    <strong>{{ __('Note') }}:</strong><br>
                    {{ $quotation->note }}
                </div>
                @endif
            </td>
            <td width="40%" valign="top">
                <table class="summary-table">
                    <tr>
                        <td>{!! invoice_bilingual_label($invConfig['subtotal_label'] ?? __('Subtotal'), $invConfig['subtotal_label_arabic'] ?? '') !!}</td>
                        <td class="text-right">{{ formatAmount($quotationSubTotal) }}</td>
                    </tr>
                    <tr>
                        <td>{!! invoice_bilingual_label($invConfig['discount_label'] ?? __('Discount'), $invConfig['discount_label_arabic'] ?? '') !!}</td>
                        <td class="text-right">{{ $discountSummaryDisplay }}</td>
                    </tr>
                    <tr class="grand-total">
                        <td>{!! invoice_bilingual_label($invConfig['total_payable_label'] ?? __('Grand Total'), $invConfig['total_payable_label_arabic'] ?? '') !!}</td>
                        <td class="text-right">{{ formatAmount($quotation->grand_total) }}</td>
                    </tr>
                    @include('sale::sale.partials.invoice-grand-total-words', ['sale' => $quotation, 'invConfig' => $invConfig, 'colspan' => 2])
                </table>
            </td>
        </tr>
    </table>

    <!-- Signatures below summary (not inside items tbody — stable in mPDF) -->
    <table style="margin-top:30px;width:100%;">
        <tr>
            <td width="45%" valign="bottom" style="padding-top:50px;">
                <div style="border-top:1px solid #333;padding-top:10px;text-align:center;max-width:220px;">
                    {{ __('Company Signature') }}
                </div>
            </td>
            <td width="10%">&nbsp;</td>
            <td width="45%" valign="bottom" style="padding-top:50px;">
                <div style="border-top:1px solid #333;padding-top:10px;text-align:center;max-width:220px;margin-left:auto;">
                    {{ __('Authorized Signature') }}
                </div>
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
