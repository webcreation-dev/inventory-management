<?php

namespace Modules\Sale\Support;

/**
 * Renders invoice labels with optional Arabic subtitle from invoice_configuration.
 */
final class InvoiceBilingualLabel
{
    /**
     * HTML fragment: English (and optional second line, RTL Arabic).
     */
    public static function inline(?string $english, ?string $arabic = ''): string
    {
        $en = trim((string) ($english ?? ''));
        $ar = trim((string) ($arabic ?? ''));
        if ($ar === '') {
            return e($en);
        }

        return '<span class="invoice-label-en">'.e($en).'</span><br>'
            .'<span class="invoice-label-ar" lang="ar">'.e($ar).'</span>';
    }
}
