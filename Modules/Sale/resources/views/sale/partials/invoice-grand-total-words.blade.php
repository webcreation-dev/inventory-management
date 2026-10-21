@php
    $words = \Modules\Sale\Support\InvoiceAmountWords::grandTotalPhrase((float) ($sale->total_payable ?? $sale->grand_total ?? 0), $invConfig ?? []);
@endphp
@if($words)
<tr>
    <td colspan="{{ $colspan ?? 2 }}" style="font-size:0.9em;font-style:italic;">{{ __('Amount in words') }}: {{ $words }}</td>
</tr>
@endif
