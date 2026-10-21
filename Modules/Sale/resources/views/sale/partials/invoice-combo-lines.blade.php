{{--
    Combo component rows. Parent templates pass flags to match their table columns.
    - $showHsn: bool
    - $showImeiSerialCol: bool
    - $showAmountColumns: bool (false for challan — only qty column after name/imei)
    - $showTaxColumns: bool (typically session collect_tax Yes for a4/a5/letterhead)
    - $isIntraState: bool (for tax type column)
    - $showDiscountColumn: bool (true only for full A4 layout)
--}}
@php
    $detailsList = $details ?? [];
    $showHsn = ! empty($showHsn);
    $showImeiSerialCol = ! empty($showImeiSerialCol);
    $showAmountColumns = ($showAmountColumns ?? true);
    $showTaxColumns = ($showTaxColumns ?? false);
    $showDiscountColumn = ($showDiscountColumn ?? false);
@endphp
@foreach($detailsList as $saleDetail)
    @if(! $saleDetail || ! $saleDetail->relationLoaded('comboSales') || $saleDetail->comboSales->isEmpty())
        @continue
    @endif
    @foreach($saleDetail->comboSales as $comboRow)
        @if(($comboRow->del_status ?? '') !== 'Live' || ($comboRow->show_in_invoice ?? 'No') !== 'Yes')
            @continue
        @endif
        @php
            $cItem = $comboRow->comboItem;
            $parentQty = (float) ($saleDetail->qty ?? 0);
            $lineQty = (float) ($comboRow->combo_item_qty ?? 0) * $parentQty;
            $unitPrice = (float) ($comboRow->combo_item_price ?? 0);
            $lineTotal = $lineQty * $unitPrice;
            $fakeDetail = (object) ['item' => $cItem];
        @endphp
        <tr class="invoice-combo-component-row">
            <td class="col-first"></td>
            @if($showHsn)
                <td></td>
            @endif
            <td>
                <span class="text-muted">└ </span>
                @if($cItem)
                    @include('sale::sale.partials.invoice-item-line-name', ['detail' => $fakeDetail, 'invConfig' => $invConfig])
                @else
                    {{ __('Combo item') }} #{{ $comboRow->combo_item_id ?? '' }}
                @endif
            </td>
            @if($showImeiSerialCol)
                <td>-</td>
            @endif
            <td class="col-amount">{{ formatQty($lineQty) }}</td>
            @if($showAmountColumns)
                <td class="col-amount">{{ formatAmount($unitPrice) }}</td>
                @if($showTaxColumns)
                    <td class="col-amount">-</td>
                    @if(! empty($isIntraState))
                        <td>-</td>
                    @endif
                @endif
                @if($showDiscountColumn)
                    <td class="col-amount">-</td>
                @endif
                <td class="col-last">{{ formatAmount($lineTotal) }}</td>
            @endif
        </tr>
    @endforeach
@endforeach
