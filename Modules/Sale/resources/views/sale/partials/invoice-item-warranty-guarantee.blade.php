@php
    $compact = $compact ?? false;
    $invConfig = $invConfig ?? [];
    $item = $detail->item ?? null;
    $warrantyLine = null;
    $guaranteeLine = null;
    if ($item) {
        $w = $item->warranty;
        $wd = $item->warranty_date;
        $g = $item->guarantee;
        $gd = $item->guarantee_date;
        $parent = ((int) ($item->parent_id ?? 0) > 0) ? $item->parent : null;
        if ($parent) {
            if (($w === null || trim((string) $w) === '') && $parent->warranty !== null && trim((string) $parent->warranty) !== '') {
                $w = $parent->warranty;
                $wd = $parent->warranty_date;
            }
            if (($g === null || trim((string) $g) === '') && $parent->guarantee !== null && trim((string) $parent->guarantee) !== '') {
                $g = $parent->guarantee;
                $gd = $parent->guarantee_date;
            }
        }
        $unitLabels = ['day' => __('Day'), 'month' => __('Month'), 'year' => __('Year')];
        if ($w !== null && trim((string) $w) !== '') {
            $suffix = isset($unitLabels[$wd]) ? ' ' . $unitLabels[$wd] : ($wd ? ' ' . $wd : '');
            $warrantyLine = trim((string) $w . $suffix);
        }
        if ($g !== null && trim((string) $g) !== '') {
            $suffix = isset($unitLabels[$gd]) ? ' ' . $unitLabels[$gd] : ($gd ? ' ' . $gd : '');
            $guaranteeLine = trim((string) $g . $suffix);
        }
    }
    if (($invConfig['show_warranty_period'] ?? 'Yes') !== 'Yes') {
        $warrantyLine = null;
    }
    if (($invConfig['show_guarantee_period'] ?? 'Yes') !== 'Yes') {
        $guaranteeLine = null;
    }
@endphp
@if($warrantyLine || $guaranteeLine)
<div class="invoice-item-warranty-guarantee">
    @if($compact)
        <span class="invoice-wg-compact">
            @if($warrantyLine){{ __('Warranty') }}: {{ $warrantyLine }}@endif
            @if($warrantyLine && $guaranteeLine) · @endif
            @if($guaranteeLine){{ __('Guarantee') }}: {{ $guaranteeLine }}@endif
        </span>
    @else
        @if($warrantyLine)
            <div>{{ __('Warranty') }}: {{ $warrantyLine }}</div>
        @endif
        @if($guaranteeLine)
            <div>{{ __('Guarantee') }}: {{ $guaranteeLine }}</div>
        @endif
    @endif
</div>
@endif
