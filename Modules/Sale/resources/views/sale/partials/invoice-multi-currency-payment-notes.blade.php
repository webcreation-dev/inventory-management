{{--
  Thermal / A4 invoice note before QR: foreign amount paid and rate vs company base currency.
  Expects $sale with salePayments; optional $baseCurrency (falls back to session company currency).
--}}
@php
    $baseCurrencyLabel = trim((string) ($baseCurrency ?? session('company.currency') ?? ''));
    if ($baseCurrencyLabel === '') {
        $baseCurrencyLabel = __('Base');
    }
    $multiLines = collect($sale->salePayments ?? [])
        ->filter(static function ($p) {
            return ($p->multi_currency ?? 'No') === 'Yes'
                && trim((string) ($p->currency_type ?? '')) !== ''
                && (float) ($p->multi_currency_rate ?? 0) > 0;
        })
        ->values();
@endphp
@if($multiLines->isNotEmpty())
<div class="invoice-multi-currency-notes" style="margin-top: 12px; margin-bottom: 8px; font-size: 11px; line-height: 1.35; color: #333;">
    @foreach($multiLines as $payment)
        @php
            $foreignAmt = rtrim(rtrim(number_format((float) $payment->amount, 3, '.', ''), '0'), '.') ?: '0';
            $rateFmt = rtrim(rtrim(number_format((float) $payment->multi_currency_rate, 6, '.', ''), '0'), '.') ?: '0';
        @endphp
        <p style="margin: 0 0 6px 0;">
            {{ __('Invoice multi-currency note', [
                'paid_currency' => $payment->currency_type,
                'paid_amount' => $foreignAmt,
                'base_currency' => $baseCurrencyLabel,
                'rate' => $rateFmt,
            ]) }}
        </p>
    @endforeach
</div>
@endif
