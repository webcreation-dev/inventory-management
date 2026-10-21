@php
    $invConfig = is_array($invConfig ?? null) ? $invConfig : [];
    $qrCodeOption = $invConfig['qr_code_option'] ?? 'ZATCA QR Code';
@endphp
@if($qrCodeOption === 'ZATCA QR Code' && $sale->zatca_phase1_qr_code)
    {{-- Zatca QR Code --}}
    @php
        $qrData = base64_decode($sale->zatca_phase1_qr_code);
        $qrDataEncoded = urlencode($qrData);
        $qrCodeImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . $qrDataEncoded;
    @endphp
    <div style="text-align: center; margin-top: 20px;">
        <p style="font-weight: 600; margin-bottom: 10px;">{{ __('ZATCA QR Code') }}</p>
        <img src="{{ $qrCodeImageUrl }}" alt="ZATCA QR Code" style="max-width: 150px; height: auto;">
    </div>
@elseif($qrCodeOption === 'Regular QR Code')
    @php
        $regularQrDate = '';
        if (!empty($sale->date_time)) {
            $regularQrDate = formatDateTime($sale->date_time);
        } elseif (!empty($sale->sale_date)) {
            $regularQrDate = formatDate($sale->sale_date);
        }
        $customerName = $sale->customer->name ?? '';
        $outletName = $sale->outlet->outlet_name ?? '';
        $regularQrLines = array_values(array_filter([
            trim((string) ($businessName ?? '')),
            $outletName !== '' ? __('Outlet') . ': ' . $outletName : null,
            ($sale->sale_no ?? '') !== '' ? __('Sale No') . ': ' . $sale->sale_no : null,
            $regularQrDate !== '' ? __('Date') . ': ' . $regularQrDate : null,
            __('Total') . ': ' . formatAmount($sale->total_payable ?? $sale->grand_total),
            $customerName !== '' ? __('Customer') . ': ' . $customerName : null,
        ], static fn ($line) => $line !== null && $line !== ''));
        $regularQrData = implode("\n", $regularQrLines);
        $regularQrEncoded = rawurlencode($regularQrData);
        $regularQrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . $regularQrEncoded;
    @endphp
    <div class="invoice-qr-container">
        <h6>{{ __('Invoice QR') }}</h6>
        <img src="{{ $regularQrImageUrl }}" alt="{{ __('Invoice QR') }}" style="max-width: 100px; height: auto;">
    </div>
@endif
