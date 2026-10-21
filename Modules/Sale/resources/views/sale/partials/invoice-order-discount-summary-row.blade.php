@php
    $invConfig = isset($invConfig) && is_array($invConfig) ? $invConfig : [];
    $orderDisc = (float) ($sale->sub_total_discount_amount ?? 0);
    $orderDiscType = strtolower((string) ($sale->sub_total_discount_type ?? 'fixed'));
    if (in_array($orderDiscType, ['flat', 'amount'], true)) {
        $orderDiscType = 'fixed';
    }
    $orderDiscVal = (float) ($sale->sub_total_discount_value ?? 0);
    $pctStr = null;
    if ($orderDiscType === 'percentage' && $orderDiscVal > 0) {
        $pctStr = (fmod($orderDiscVal, 1.0) < 0.00001)
            ? (string) (int) round($orderDiscVal)
            : rtrim(rtrim(number_format($orderDiscVal, 3, '.', ''), '0'), '.');
    }
@endphp
@if($orderDisc > 0.0001)
<tr>
    <td>{!! invoice_bilingual_label($invConfig['discount_label'] ?? __('Order Discount'), $invConfig['discount_label_arabic'] ?? '') !!}</td>
    <td @if(!empty($amountClass)) class="{{ $amountClass }}" @endif>
        @if($pctStr !== null)
            {{ formatAmount($orderDisc) }} {{ __('of') }} {{ $pctStr }}%
        @else
            {{ formatAmount($orderDisc) }}
        @endif
    </td>
</tr>
@endif
