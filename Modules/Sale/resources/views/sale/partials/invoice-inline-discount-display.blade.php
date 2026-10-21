@php
    $mode = $mode ?? 'cell';
    $rows = [];
    $first = null;
    if (!empty($group)) {
        $rows = $group['details'] ?? [];
        $first = $group['first'] ?? null;
    } elseif (isset($detail)) {
        $rows = [$detail];
        $first = $detail;
    }
    $display = null;
    if ($first && count($rows) > 0) {
        $lineDiscountTotal = 0.0;
        foreach ($rows as $d) {
            $lineDiscountTotal += (float) ($d->discount_amount ?? 0);
        }
        $discountType = strtolower((string) ($first->discount_type ?? 'fixed'));
        if (in_array($discountType, ['flat', 'amount'], true)) {
            $discountType = 'fixed';
        }
        $pctVal = (float) ($first->menu_discount_value ?? 0);
        if ($lineDiscountTotal > 0.0001) {
            if ($discountType === 'percentage' && $pctVal > 0) {
                $pctStr = (fmod($pctVal, 1.0) < 0.00001)
                    ? (string) (int) round($pctVal)
                    : rtrim(rtrim(number_format($pctVal, 3, '.', ''), '0'), '.');
                $display = formatAmount($lineDiscountTotal) . ' ' . __('of') . ' ' . $pctStr . '%';
            } else {
                $display = formatAmount($lineDiscountTotal);
            }
        }
    }
@endphp
@if($mode === 'below_name')
    @if(!empty($display))
        <div style="font-size: 10px; color: #444; margin-top: 2px;">{{ __('Discount') }}: {{ $display }}</div>
    @endif
@else
    @if(!empty($display))
        {{ $display }}
    @else
        -
    @endif
@endif
