<?php

namespace Modules\Sale\Support;

/**
 * Centralizes sale invoice line item title: product code, embedded codes in names (from repositories),
 * and optional brand — driven by company invoice_configuration JSON.
 */
final class InvoiceItemDisplay
{
    /**
     * Single line for an item row (variation: "Parent - Child", general: name), respecting invoice settings.
     *
     * @param  object|null  $item  Sale detail item (expects optional parent, brand relations when needed)
     */
    public static function lineItemName(?object $item, array $invConfig): string
    {
        if (! $item) {
            return '';
        }

        $showCode = ($invConfig['show_product_code'] ?? 'No') === 'Yes';
        $code = trim((string) ($item->code ?? ''));
        $parent = ((int) ($item->parent_id ?? 0) > 0) ? ($item->parent ?? null) : null;

        if ($parent) {
            $pCode = trim((string) ($parent->code ?? ''));
            $pName = self::stripTrailingCodeVariants(trim((string) ($parent->name ?? '')), $pCode);
            $cName = self::stripTrailingCodeVariants(trim((string) ($item->name ?? '')), $code);
            $line = trim($pName.' - '.$cName);
        } else {
            $line = self::stripTrailingCodeVariants(trim((string) ($item->name ?? '')), $code);
        }

        // Remove embedded SKU again after composition (variation/parent concatenation edge cases).
        if (! $showCode) {
            $line = self::stripTrailingCodeVariants($line, $code);
            if ($parent) {
                $pCodeFt = trim((string) ($parent->code ?? ''));
                if ($pCodeFt !== '') {
                    $line = self::stripTrailingCodeVariants($line, $pCodeFt);
                }
            }
        }

        $brand = '';
        if (($invConfig['show_brand'] ?? 'No') === 'Yes') {
            $brandModel = $item->brand ?? null;
            if (! $brandModel && $parent) {
                $brandModel = $parent->brand ?? null;
            }
            if ($brandModel) {
                $brand = trim((string) ($brandModel->name ?? ''));
            }
        }


        if ($showCode && $code !== '') {
            if (! self::labelHasTrailingProductCode($line, $code)) {
                $line = trim($line.' ('.$code.')');
            }
        }

        if ($brand !== '') {
            $line = trim($line.' -'.$brand);
        }

        return $line;
    }

    /**
     * English/primary name with optional alternative_name in parentheses.
     * Example: Olive Oil (شجرة الموز)
     */
    public static function lineItemNameWithAlternative(?object $item, array $invConfig, bool $forceAlternative = false): string
    {
        $name = self::lineItemName($item, $invConfig);
        if ($name === '') {
            return '';
        }

        $showAlt = $forceAlternative || (($invConfig['show_item_alternative_name'] ?? 'Yes') === 'Yes');
        if (! $showAlt) {
            return $name;
        }

        $alt = self::itemAlternativeNameLine($item);
        if ($alt === '') {
            return $name;
        }

        // Avoid duplicating if already embedded.
        if (str_contains($name, $alt)) {
            return $name;
        }

        return trim($name.' ('.$alt.')');
    }

    /**
     * Secondary line for invoices (e.g. Arabic) from Item::alternative_name; variations: parent + child.
     */
    public static function itemAlternativeNameLine(?object $item): string
    {
        if (! $item) {
            return '';
        }

        // Fresh attribute read — avoid stale/missing casts.
        $selfAlt = trim((string) (data_get($item, 'alternative_name') ?? ''));

        $parentId = (int) (data_get($item, 'parent_id') ?? 0);
        $parent = $parentId > 0 ? (data_get($item, 'parent') ?? null) : null;

        if ($parent) {
            $pAlt = trim((string) (data_get($parent, 'alternative_name') ?? ''));
            $cAlt = $selfAlt;
            if ($pAlt === '' && $cAlt === '') {
                return '';
            }
            if ($pAlt !== '' && $cAlt !== '') {
                return $pAlt.' - '.$cAlt;
            }

            return $pAlt !== '' ? $pAlt : $cAlt;
        }

        return $selfAlt;
    }

    /**
     * Remove trailing (code), [code], or " - code" when the same as the given product code (repository duplicates).
     */
    public static function stripTrailingCodeVariants(string $text, string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return trim($text);
        }

        $escaped = preg_quote($code, '/');
        $patterns = [
            '/\s*\('.$escaped.'\)\s*$/u',
            '/\s*\['.$escaped.'\]\s*$/u',
            '/\s*-\s*'.$escaped.'\s*$/u',
        ];

        $prev = null;
        $text = trim($text);
        while ($prev !== $text) {
            $prev = $text;
            foreach ($patterns as $p) {
                $text = preg_replace($p, '', $text) ?? '';
            }
            $text = trim($text);
        }

        return $text;
    }

    private static function labelHasTrailingProductCode(string $line, string $code): bool
    {
        $code = trim($code);
        if ($code === '') {
            return true;
        }

        $escaped = preg_quote($code, '/');

        return (bool) preg_match('/(\(|\\[)'.$escaped.'(\\)|\\])\s*$/u', $line);
    }
}
