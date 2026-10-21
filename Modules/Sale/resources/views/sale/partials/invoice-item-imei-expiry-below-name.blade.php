@php
    $invConfig = $invConfig ?? [];
    $showImeiSerial = $invConfig['show_product_imei_serial_number'] ?? 'No';
    $first = $group['first'];
    $parts = [];
    foreach ($group['details'] as $d) {
        $s = trim((string) ($d->expiry_imei_serial ?? ''));
        if ($s !== '') {
            $parts[] = $s;
        }
    }
    $combined = implode(', ', $parts);
    $type = $first->item ? $first->item->type : null;
@endphp
@if ($showImeiSerial === 'Yes' && $combined !== '')
    <div class="invoice-item-sublines" style="font-size: 0.85em; margin-top: 4px; line-height: 1.35;">
        @if ($type === 'IMEI_Product')
            <div>{{ __('IMEI Number') }}: {{ $combined }}</div>
        @elseif ($type === 'Serial_Product')
            <div>{{ __('Serial Number') }}: {{ $combined }}</div>
        @elseif ($type === 'Medicine_Product')
            <div>{{ __('Expiry Date') }}: {{ $combined }}</div>
        @else
            <div>{{ __('IMEI/Serial') }}: {{ $combined }}</div>
        @endif
    </div>
@endif
