<?php

namespace Modules\Sale\Support;

/**
 * Grand total in words from invoice_configuration (show_total_in_words, word_format).
 */
final class InvoiceAmountWords
{
    public static function grandTotalPhrase(float $amount, array $invConfig): ?string
    {
        if (!self::isEnabled($invConfig['show_total_in_words'] ?? 'Yes')) {
            return null;
        }

        $format = strtolower(trim((string) ($invConfig['word_format'] ?? 'Indian')));
        $isIndian = in_array($format, ['indian', 'india', 'en_in', 'in'], true);
        $rounded = (int) round($amount);

        // Prefer intl when available, but keep a PHP fallback so words always work.
        if (extension_loaded('intl')) {
            $locale = $isIndian ? 'en_IN' : 'en';
            $fmt = new \NumberFormatter($locale, \NumberFormatter::SPELLOUT);
            $words = $fmt->format($rounded);
            if ($words !== false) {
                return mb_convert_case((string) $words, MB_CASE_TITLE, 'UTF-8');
            }
        }

        $words = self::toWordsFallback($rounded, $isIndian);
        return $words !== '' ? mb_convert_case($words, MB_CASE_TITLE, 'UTF-8') : null;
    }

    private static function isEnabled($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['yes', '1', 'true', 'on'], true);
    }

    private static function toWordsFallback(int $number, bool $isIndian): string
    {
        if ($number === 0) {
            return 'zero';
        }
        if ($number < 0) {
            return 'minus ' . self::toWordsFallback(abs($number), $isIndian);
        }

        $ones = [
            0 => '', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five',
            6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten',
            11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen',
            15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen',
        ];

        $tens = [
            2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty',
            6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety',
        ];

        $belowThousand = function (int $n) use ($ones, $tens): string {
            $parts = [];
            if ($n >= 100) {
                $parts[] = $ones[(int) floor($n / 100)] . ' hundred';
                $n %= 100;
            }
            if ($n >= 20) {
                $parts[] = $tens[(int) floor($n / 10)] . (($n % 10) ? ' ' . $ones[$n % 10] : '');
            } elseif ($n > 0) {
                $parts[] = $ones[$n];
            }
            return trim(implode(' ', array_filter($parts)));
        };

        $parts = [];
        if ($isIndian) {
            $scales = [
                10000000 => 'crore',
                100000 => 'lakh',
                1000 => 'thousand',
            ];
        } else {
            $scales = [
                1000000000 => 'billion',
                1000000 => 'million',
                1000 => 'thousand',
            ];
        }

        foreach ($scales as $value => $label) {
            if ($number >= $value) {
                $chunk = (int) floor($number / $value);
                $number %= $value;
                $chunkWords = $belowThousand($chunk);
                if ($chunkWords !== '') {
                    $parts[] = $chunkWords . ' ' . $label;
                }
            }
        }

        if ($number > 0) {
            $parts[] = $belowThousand($number);
        }

        return trim(implode(' ', array_filter($parts)));
    }
}
