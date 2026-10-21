<?php

namespace Modules\Ecommerce\Support;

class BrandColor
{
    public const DEFAULT = '#0d9488';

    /**
     * Normalize a hex color to #rrggbb.
     */
    public static function normalize(?string $hex, string $fallback = self::DEFAULT): string
    {
        $hex = trim((string) $hex);
        if ($hex === '') {
            return $fallback;
        }

        if ($hex[0] !== '#') {
            $hex = '#' . $hex;
        }

        if (preg_match('/^#([0-9a-fA-F]{3})$/', $hex, $m)) {
            $hex = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
        }

        if (! preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
            return $fallback;
        }

        return strtolower($hex);
    }

    /**
     * Build CSS custom properties for the storefront theme.
     *
     * @return array{hex:string,rgb:string,accent:string,strong:string,soft:string,glow:string,dark:string,css:string}
     */
    public static function palette(?string $hex): array
    {
        $accent = self::normalize($hex);
        [$r, $g, $b] = self::hexToRgb($accent);

        $light = self::rgbToHex(self::mix($r, $g, $b, 255, 255, 255, 0.18));
        $dark = self::rgbToHex(self::mix($r, $g, $b, 0, 0, 0, 0.22));
        $deeper = self::rgbToHex(self::mix($r, $g, $b, 0, 0, 0, 0.38));
        $soft = self::rgbToHex(self::mix($r, $g, $b, 255, 255, 255, 0.86));
        $glow = "rgba({$r}, {$g}, {$b}, 0.22)";

        $css = implode(';', [
            "--accent:{$dark}",
            "--accent-strong:{$accent}",
            "--accent-soft:{$soft}",
            "--accent-glow:{$glow}",
            "--accent-rgb:{$r}, {$g}, {$b}",
            "--accent-deep:{$deeper}",
            "--brand-500:{$light}",
            "--brand-600:{$accent}",
            "--brand-700:{$dark}",
        ]);

        return [
            'hex' => $accent,
            'rgb' => "{$r}, {$g}, {$b}",
            'accent' => $dark,
            'strong' => $accent,
            'soft' => $soft,
            'glow' => $glow,
            'dark' => $dark,
            'css' => $css,
        ];
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    protected static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    protected static function rgbToHex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * Mix color toward another by amount (0-1).
     *
     * @return array{0:int,1:int,2:int}
     */
    protected static function mix(int $r, int $g, int $b, int $tr, int $tg, int $tb, float $amount): array
    {
        return [
            (int) round($r + ($tr - $r) * $amount),
            (int) round($g + ($tg - $g) * $amount),
            (int) round($b + ($tb - $b) * $amount),
        ];
    }
}
