@php
    /** @var \Illuminate\Database\Eloquent\Model|null $detail */
    $item = $detail->item ?? null;
@endphp
@if($item)
    @if(($invConfig['show_product_image'] ?? 'No') === 'Yes' && !empty($item->photo))
        <img src="{{ asset('public/uploads/items/' . $item->photo) }}" alt="" width="36" height="36" style="vertical-align:middle;margin-right:6px;object-fit:contain;border-radius:4px;">
    @endif
    {{ \Modules\Sale\Support\InvoiceItemDisplay::lineItemNameWithAlternative($item, $invConfig) }}
@elseif(!empty($detail->custom_item_name))
    {{ $detail->custom_item_name }}
@endif
