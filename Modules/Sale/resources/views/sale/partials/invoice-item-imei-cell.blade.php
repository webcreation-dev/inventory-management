@php
    $invConfig = $invConfig ?? [];
    $showImeiSerial = ($invConfig['show_product_imei_serial_number'] ?? 'No') === 'Yes';
    $first = $group['first'];
    $imeiParts = [];
    foreach ($group['details'] as $d) {
        $s = trim((string) ($d->expiry_imei_serial ?? ''));
        if ($s !== '') {
            $imeiParts[] = $s;
        }
    }
    $imeiCombined = implode(', ', $imeiParts);
@endphp
@if (! $showImeiSerial)
    -
@elseif ($imeiCombined !== '')
    @if ($first->item && in_array($first->item->type, ['IMEI_Product', 'Serial_Product']))
        {{ $first->item->type === 'IMEI_Product' ? 'IMEI' : 'Serial' }}: {{ $imeiCombined }}
    @elseif ($first->item && $first->item->type === 'Medicine_Product')
        {{ $imeiCombined }}
    @else
        {{ $imeiCombined }}
    @endif
@else
    -
@endif
