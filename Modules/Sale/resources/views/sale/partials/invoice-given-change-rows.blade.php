{{--
    Shows "Given Amount" and "Change Amount" rows on an invoice — but ONLY when
    the customer handed over more than what was applied to the invoice (i.e. a
    cash overpayment where change was returned).

    Expected variables:
      - $sale          The Sale model (requires given_amount, paid_amount, change_amount columns)
      - $invConfig     (optional) Invoice configuration array for label overrides
      - $amountClass   (optional) Extra class(es) to apply to the amount <td> (e.g. "text-right" for PDF templates)
--}}
@php
    $invConfig = isset($invConfig) && is_array($invConfig) ? $invConfig : [];
    $amountClass = isset($amountClass) ? $amountClass : '';
    $givenAmountVal = (float) ($sale->given_amount ?? 0);
    $paidAmountVal = (float) ($sale->paid_amount ?? 0);
    $changeAmountVal = (float) ($sale->change_amount ?? 0);
    // Only show these rows when there was actual change returned to the customer.
    $showGivenChange = $changeAmountVal > 0.001 && $givenAmountVal > $paidAmountVal + 0.001;
@endphp
@if($showGivenChange)
<tr>
    <td>{!! invoice_bilingual_label($invConfig['given_amount_label'] ?? __('Given Amount'), $invConfig['given_amount_label_arabic'] ?? '') !!}</td>
    <td @if($amountClass) class="{{ $amountClass }}" @endif>{{ formatAmount($givenAmountVal) }}</td>
</tr>
<tr>
    <td>{!! invoice_bilingual_label($invConfig['change_amount_label'] ?? __('Change Amount'), $invConfig['change_amount_label_arabic'] ?? '') !!}</td>
    <td @if($amountClass) class="{{ $amountClass }}" @endif>{{ formatAmount($changeAmountVal) }}</td>
</tr>
@endif
