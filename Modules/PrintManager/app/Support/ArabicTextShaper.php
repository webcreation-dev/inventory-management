<?php

namespace Modules\PrintManager\Support;

/**
 * Arabic letter reshaping for GD / ESC-POS raster printing.
 * FreeType/imagettftext does not apply Arabic joining, so each letter
 * would otherwise print in its isolated form.
 */
final class ArabicTextShaper
{
    /**
     * Forms: [isolated, final, initial, medial]
     * Null means that form does not exist / not used.
     *
     * @var array<string, array{0:?string,1:?string,2:?string,3:?string}>
     */
    private const FORMS = [
        // [isolated, final, initial, medial] — Presentation Forms-B (FE70–FEFF)
        // Isolated must be FE* codepoints so GD/FreeType does not re-shape logical Arabic.
        'ء' => ['ﺀ', 'ﺀ', null, null],
        'آ' => ['ﺁ', 'ﺂ', null, null],
        'أ' => ['ﺃ', 'ﺄ', null, null],
        'ؤ' => ['ﺅ', 'ﺆ', null, null],
        'إ' => ['ﺇ', 'ﺈ', null, null],
        'ئ' => ['ﺉ', 'ﺊ', 'ﺋ', 'ﺌ'],
        'ا' => ['ﺍ', 'ﺎ', null, null],
        'ب' => ['ﺏ', 'ﺐ', 'ﺑ', 'ﺒ'],
        'ة' => ['ﺓ', 'ﺔ', null, null],
        'ت' => ['ﺕ', 'ﺖ', 'ﺗ', 'ﺘ'],
        'ث' => ['ﺙ', 'ﺚ', 'ﺛ', 'ﺜ'],
        'ج' => ['ﺝ', 'ﺞ', 'ﺟ', 'ﺠ'],
        'ح' => ['ﺡ', 'ﺢ', 'ﺣ', 'ﺤ'],
        'خ' => ['ﺥ', 'ﺦ', 'ﺧ', 'ﺨ'],
        'د' => ['ﺩ', 'ﺪ', null, null],
        'ذ' => ['ﺫ', 'ﺬ', null, null],
        'ر' => ['ﺭ', 'ﺮ', null, null],
        'ز' => ['ﺯ', 'ﺰ', null, null],
        'س' => ['ﺱ', 'ﺲ', 'ﺳ', 'ﺴ'],
        'ش' => ['ﺵ', 'ﺶ', 'ﺷ', 'ﺸ'],
        'ص' => ['ﺹ', 'ﺺ', 'ﺻ', 'ﺼ'],
        'ض' => ['ﺽ', 'ﺾ', 'ﺿ', 'ﻀ'],
        'ط' => ['ﻁ', 'ﻂ', 'ﻃ', 'ﻄ'],
        'ظ' => ['ﻅ', 'ﻆ', 'ﻇ', 'ﻈ'],
        'ع' => ['ﻉ', 'ﻊ', 'ﻋ', 'ﻌ'],
        'غ' => ['ﻍ', 'ﻎ', 'ﻏ', 'ﻐ'],
        'ف' => ['ﻑ', 'ﻒ', 'ﻓ', 'ﻔ'],
        'ق' => ['ﻕ', 'ﻖ', 'ﻗ', 'ﻘ'],
        'ك' => ['ﻙ', 'ﻚ', 'ﻛ', 'ﻜ'],
        'ل' => ['ﻝ', 'ﻞ', 'ﻟ', 'ﻠ'],
        'م' => ['ﻡ', 'ﻢ', 'ﻣ', 'ﻤ'],
        'ن' => ['ﻥ', 'ﻦ', 'ﻧ', 'ﻨ'],
        'ه' => ['ﻩ', 'ﻪ', 'ﻫ', 'ﻬ'],
        'و' => ['ﻭ', 'ﻮ', null, null],
        'ى' => ['ﻯ', 'ﻰ', null, null],
        'ي' => ['ﻱ', 'ﻲ', 'ﻳ', 'ﻴ'],
        'ٱ' => ['ٱ', 'ٱ', null, null],
        'ٹ' => ['ﭦ', 'ﭧ', 'ﭨ', 'ﭩ'],
        'پ' => ['ﭖ', 'ﭗ', 'ﭘ', 'ﭙ'],
        'چ' => ['ﭺ', 'ﭻ', 'ﭼ', 'ﭽ'],
        'ژ' => ['ﮊ', 'ﮋ', null, null],
        'ک' => ['ﮎ', 'ﮏ', 'ﮐ', 'ﮑ'],
        'گ' => ['ﮒ', 'ﮓ', 'ﮔ', 'ﮕ'],
        'ی' => ['ﯼ', 'ﯽ', 'ﯾ', 'ﯿ'],
    ];

    /** Letters that do not connect to the following letter. */
    private const NON_CONNECTING = [
        'ء', 'آ', 'أ', 'ؤ', 'إ', 'ا', 'ة', 'د', 'ذ', 'ر', 'ز', 'و', 'ى', 'ٱ', 'ژ',
        '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
        '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩',
        ' ', '.', ',', ':', ';', '!', '?', '-', '_', '(', ')', '[', ']', '{', '}', '/', '\\',
        '+', '=', '*', '%', '#', '@', '"', "'", '«', '»', '،', '؛', '؟',
    ];

    /**
     * Prepare mixed text for GD imagettftext (LTR canvas):
     * reshape Arabic into joined presentation forms.
     * Runs are NOT reversed — receipt lines stay LTR (English + Arabic reading order).
     */
    public static function forGd(string $text): string
    {
        if ($text === '' || ! self::containsArabic($text)) {
            return $text;
        }

        $chars = self::mbChars($text);
        $count = count($chars);
        $out = '';
        $arabicBuf = [];

        $flushArabic = static function () use (&$out, &$arabicBuf): void {
            if ($arabicBuf === []) {
                return;
            }
            $segment = implode('', $arabicBuf);
            $arabicBuf = [];
            // Reshape to joined presentation forms, then reverse so the LTR
            // GD bitmap reads correctly right-to-left (GD does not apply BIDI
            // to Presentation Forms-B code points).
            $out .= self::mbReverse(self::reshape($segment));
        };

        for ($i = 0; $i < $count; $i++) {
            $ch = $chars[$i];

            if (self::isArabicChar($ch) || self::isArabicDiacritic($ch)) {
                $arabicBuf[] = $ch;
                continue;
            }

            // Keep spaces/Arabic punctuation inside an Arabic run when neighbors are Arabic.
            if (
                $arabicBuf !== []
                && self::isArabicNeutral($ch)
                && isset($chars[$i + 1])
                && (self::isArabicChar($chars[$i + 1]) || self::isArabicDiacritic($chars[$i + 1]))
            ) {
                $arabicBuf[] = $ch;
                continue;
            }

            $flushArabic();
            $out .= $ch;
        }
        $flushArabic();

        return $out;
    }

    private static function isArabicNeutral(string $ch): bool
    {
        return in_array($ch, [' ', "\u{00A0}", '،', '؛', '؟', 'ـ'], true);
    }

    public static function containsArabic(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]/u', $text);
    }

    /**
     * Convert logical Arabic to presentation forms (joined letters).
     */
    public static function reshape(string $text): string
    {
        $chars = self::mbChars($text);
        $count = count($chars);
        if ($count === 0) {
            return '';
        }

        // Resolve lam-alef combinations into single presentation chars.
        $normalized = [];
        for ($i = 0; $i < $count; $i++) {
            $ch = $chars[$i];
            $next = $chars[$i + 1] ?? null;
            if ($ch === 'ل' && $next !== null) {
                $liga = match ($next) {
                    'ا' => 'ﻻ',
                    'أ' => 'ﻷ',
                    'إ' => 'ﻹ',
                    'آ' => 'ﻵ',
                    default => null,
                };
                if ($liga !== null) {
                    $normalized[] = $liga;
                    $i++;
                    continue;
                }
            }
            $normalized[] = $ch;
        }

        $chars = $normalized;
        $count = count($chars);
        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $ch = $chars[$i];

            if (in_array($ch, ['ﻻ', 'ﻷ', 'ﻹ', 'ﻵ'], true)) {
                $prev = self::previousNonDiacritic($chars, $i);
                $prevConnects = $prev !== null && self::connectsToNext($prev);
                $result[] = $prevConnects
                    ? match ($ch) {
                        'ﻻ' => 'ﻼ',
                        'ﻷ' => 'ﻸ',
                        'ﻹ' => 'ﻺ',
                        'ﻵ' => 'ﻶ',
                        default => $ch,
                    }
                    : $ch;
                continue;
            }

            if (self::isArabicDiacritic($ch) || ! isset(self::FORMS[$ch])) {
                $result[] = $ch;
                continue;
            }

            $forms = self::FORMS[$ch];
            $prev = self::previousNonDiacritic($chars, $i);
            $next = self::nextNonDiacritic($chars, $i);

            $connectPrev = $prev !== null
                && self::isArabicLetter($prev)
                && self::connectsToNext($prev);
            // Only join forward when this letter can connect and the next letter is Arabic.
            $connectNext = self::connectsToNext($ch)
                && $next !== null
                && self::isArabicLetter($next)
                && isset(self::FORMS[$next]);

            if ($connectPrev && $connectNext && $forms[3] !== null) {
                $result[] = $forms[3]; // medial
            } elseif ($connectPrev && $forms[1] !== null) {
                $result[] = $forms[1]; // final
            } elseif ($connectNext && $forms[2] !== null) {
                $result[] = $forms[2]; // initial
            } else {
                $result[] = $forms[0]; // isolated
            }
        }

        return implode('', $result);
    }

    /**
     * @param  list<string>  $chars
     */
    private static function previousNonDiacritic(array $chars, int $index): ?string
    {
        for ($p = $index - 1; $p >= 0; $p--) {
            if (! self::isArabicDiacritic($chars[$p])) {
                return $chars[$p];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $chars
     */
    private static function nextNonDiacritic(array $chars, int $index): ?string
    {
        $count = count($chars);
        for ($n = $index + 1; $n < $count; $n++) {
            if (! self::isArabicDiacritic($chars[$n])) {
                return $chars[$n];
            }
        }

        return null;
    }

    private static function connectsToNext(string $ch): bool
    {
        if (in_array($ch, self::NON_CONNECTING, true)) {
            return false;
        }

        if (! isset(self::FORMS[$ch])) {
            return false;
        }

        // Must have an initial or medial form to connect forward.
        return self::FORMS[$ch][2] !== null || self::FORMS[$ch][3] !== null;
    }

    private static function isArabicLetter(string $ch): bool
    {
        return isset(self::FORMS[$ch])
            || in_array($ch, ['ﻻ', 'ﻷ', 'ﻹ', 'ﻵ', 'ﻼ', 'ﻸ', 'ﻺ', 'ﻶ'], true);
    }

    private static function isArabicChar(string $ch): bool
    {
        return self::isArabicLetter($ch)
            || (bool) preg_match('/^[\x{0600}-\x{06FF}]$/u', $ch);
    }

    private static function isArabicDiacritic(string $ch): bool
    {
        return (bool) preg_match('/^[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]$/u', $ch);
    }

    /**
     * @return list<string>
     */
    private static function mbChars(string $text): array
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($chars) ? $chars : [];
    }

    private static function mbReverse(string $text): string
    {
        $chars = self::mbChars($text);

        return implode('', array_reverse($chars));
    }
}
