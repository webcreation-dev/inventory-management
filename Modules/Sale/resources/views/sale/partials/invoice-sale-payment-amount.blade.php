@php
    /** @var \Modules\Sale\Models\SalePayment $payment */
    $payBase = $payment->getBaseCurrencyAmount();
    $isMulti = ($payment->multi_currency ?? 'No') === 'Yes'
        && ($payment->multi_currency_rate ?? 0) > 0
        && ($payment->currency_type ?? '') !== '';
@endphp
{{ formatAmount($payBase) }}@if($isMulti)<br><small class="text-muted">({{ rtrim(rtrim(number_format((float) $payment->amount, 3, '.', ''), '0'), '.') }} {{ $payment->currency_type }})</small>@endif
