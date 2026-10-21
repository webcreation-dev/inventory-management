<?php

namespace Modules\PrintManager\Services;

use Modules\PrintManager\Support\ArabicTextShaper;

/**
 * ESC/POS builder with UTF-8 / Arabic support for QZ Tray raw printing.
 * Arabic is reshaped then rasterized so joined letter forms print correctly.
 */
class ESCPosService
{
    public const ESC = "\x1B";
    public const GS = "\x1D";

    protected int $dotsPerLine = 384;

    /**
     * @param  array<string, mixed>  $structure
     */
    public function buildFromStructure(array $structure): string
    {
        $width = (int) ($structure['characters_per_line'] ?? 42);
        $this->dotsPerLine = $width <= 32 ? 384 : 576;

        $commands = $this->initialize();

        if (! empty($structure['title'])) {
            $commands .= $this->setAlign('center');
            $commands .= $this->bold(true);
            $commands .= $this->text((string) $structure['title']);
            $commands .= $this->bold(false);
            $commands .= $this->feed(1);
        }

        $commands .= $this->setAlign('left');

        foreach ($structure['lines'] as $line) {
            $commands .= match ($line['type']) {
                'divider' => $this->text(str_repeat('-', $width)),
                'item' => $this->itemBlock($line, $width),
                'total' => $this->totalLine($line, $width),
                'barcode' => $this->barcode((string) ($line['value'] ?? '')),
                'qrcode' => $this->qrCode((string) ($line['value'] ?? '')),
                default => $this->text((string) ($line['text'] ?? '')),
            };
        }

        if (! empty($structure['footer'])) {
            $commands .= $this->feed(1);
            $commands .= $this->setAlign('center');
            foreach (preg_split("/\r\n|\n|\r/", (string) $structure['footer']) ?: [] as $footerLine) {
                $commands .= $this->text((string) $footerLine);
            }
        }

        $commands .= $this->feed(2);
        $commands .= $this->cut();

        return $commands;
    }

    public function buildTestPage(string $printerName = 'Test Printer'): string
    {
        return $this->initialize()
            . $this->setAlign('center')
            . $this->bold(true)
            . $this->text('PrintManager Test Page')
            . $this->bold(false)
            . $this->feed(1)
            . $this->setAlign('left')
            . $this->text('Printer: ' . $printerName)
            . $this->text('Date: ' . now()->format('Y-m-d H:i:s'))
            . $this->text('Arabic: مرحبا')
            . $this->text('Status: OK')
            . $this->feed(2)
            . $this->cut();
    }

    public function openCashDrawerCommand(): string
    {
        return self::ESC . 'p' . chr(0) . chr(25) . chr(250);
    }

    protected function itemBlock(array $line, int $width): string
    {
        $qty = $this->formatQtyValue($line['qty'] ?? 0);
        $unit = number_format((float) ($line['price'] ?? 0), 2);
        $total = number_format((float) ($line['total'] ?? 0), 2);

        $out = $this->text((string) ($line['name'] ?? ''));
        $out .= $this->text($this->leftRight('  ' . $qty . ' x ' . $unit, $total, $width));

        foreach (($line['meta']['lines'] ?? []) as $metaLine) {
            $meta = trim((string) $metaLine);
            if ($meta !== '') {
                $out .= $this->text('  ' . $meta);
            }
        }

        return $out;
    }

    protected function totalLine(array $line, int $width): string
    {
        $text = $this->leftRight(
            (string) ($line['label'] ?? 'Total'),
            number_format((float) ($line['amount'] ?? 0), 2),
            $width
        );

        if (! empty($line['emphasis'])) {
            return $this->bold(true) . $this->text($text) . $this->bold(false);
        }

        return $this->text($text);
    }

    protected function formatQtyValue(mixed $qty): string
    {
        $value = (float) $qty;
        if (abs($value - round($value)) < 0.00001) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    protected function initialize(): string
    {
        // ESC @ reset + Epson UTF-8 character encode system (helps mixed UTF-8 text).
        return self::ESC . '@'
            . "\x1C\x28\x43\x02\x00\x30\x02";
    }

    /**
     * Print a text line. Arabic / non-Latin scripts are rasterized so they
     * always appear on ESC/POS printers (QZ Tray raw mode).
     */
    protected function text(string $text): string
    {
        $text = $this->normalizeUtf8($text);
        if ($text === '') {
            return "\n";
        }

        if ($this->needsRaster($text)) {
            $raster = $this->renderTextRaster($text);
            if ($raster !== '') {
                return $raster;
            }
        }

        return $text . "\n";
    }

    protected function needsRaster(string $text): bool
    {
        // Arabic + other scripts outside basic Latin / common punctuation.
        return (bool) preg_match(
            '/[^\x00-\x7E\xA0-\xFF]/u',
            $text
        ) || $this->containsArabic($text);
    }

    protected function containsArabic(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
    }

    protected function normalizeUtf8(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1256');
        }

        return trim(str_replace(["\r\n", "\r"], "\n", $text));
    }

    protected function leftRight(string $left, string $right, int $width): string
    {
        $space = $width - $this->visualLength($left) - $this->visualLength($right);
        if ($space < 1) {
            return $left . ' ' . $right;
        }

        return $left . str_repeat(' ', $space) . $right;
    }

    protected function visualLength(string $text): int
    {
        return function_exists('mb_strlen')
            ? (int) mb_strlen($text, 'UTF-8')
            : strlen($text);
    }

    protected function feed(int $lines = 1): string
    {
        return self::ESC . 'd' . chr($lines);
    }

    protected function bold(bool $enabled): string
    {
        return self::ESC . 'E' . chr($enabled ? 1 : 0);
    }

    protected function setAlign(string $align): string
    {
        $value = match ($align) {
            'center' => 1,
            'right' => 2,
            default => 0,
        };

        return self::ESC . 'a' . chr($value);
    }

    protected function cut(): string
    {
        return self::GS . 'V' . chr(66) . chr(0);
    }

    protected function barcode(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $length = strlen($value);

        return self::GS . 'h' . chr(80)
            . self::GS . 'w' . chr(2)
            . self::GS . 'k' . chr(73) . chr($length) . $value . "\n";
    }

    protected function qrCode(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $storeLen = strlen($value) + 3;
        $pL = $storeLen % 256;
        $pH = intdiv($storeLen, 256);

        return self::GS . '(k' . chr($pL) . chr($pH) . chr(49) . chr(80) . chr(48) . $value
            . self::GS . '(k' . chr(3) . chr(0) . chr(49) . chr(81) . chr(48)
            . self::GS . '(k' . chr(3) . chr(0) . chr(49) . chr(82) . chr(48);
    }

    /**
     * Render a UTF-8 text line as ESC/POS raster (GS v 0).
     */
    protected function renderTextRaster(string $text): string
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagettftext')) {
            return '';
        }

        $font = $this->resolveFontPath();
        if ($font === null) {
            return '';
        }

        // Match the printer's native Font A cell (~24 dots tall, normal weight)
        // so rasterized lines blend with plain ESC/POS text lines.
        $fontSize = 16;
        $paddingX = 2;
        $paddingY = 2;
        $lineHeight = (int) ceil($fontSize * 1.4);
        $maxWidth = max(192, $this->dotsPerLine);

        // Wrap logical text first, then reshape each line for GD (LTR bitmap).
        $lines = $this->wrapText($text, $font, $fontSize, $maxWidth - ($paddingX * 2));
        if ($lines === []) {
            $lines = [$text];
        }
        $lines = array_map(
            static fn (string $line): string => ArabicTextShaper::forGd($line),
            $lines
        );

        $height = ($lineHeight * count($lines)) + ($paddingY * 2);
        $width = $maxWidth;

        $img = imagecreatetruecolor($width, $height);
        if ($img === false) {
            return '';
        }

        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $width, $height, $white);
        imagesavealpha($img, false);

        $y = $paddingY + $fontSize;
        foreach ($lines as $line) {
            // Single pass = normal weight so it matches native printer text (no fake bold).
            imagettftext($img, $fontSize, 0, $paddingX, $y, $black, $font, $line);
            $y += $lineHeight;
        }

        $raster = $this->imageToRaster($img);
        imagedestroy($img);

        return $raster;
    }

    /**
     * @return list<string>
     */
    protected function wrapText(string $text, string $font, int $fontSize, int $maxWidthPx): array
    {
        $text = trim(preg_replace("/\s+/u", ' ', $text) ?? $text);
        if ($text === '') {
            return [];
        }

        $words = preg_split('/\s+/u', $text) ?: [$text];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $trial = $current === '' ? $word : ($current . ' ' . $word);
            $box = @imagettfbbox($fontSize, 0, $font, $trial);
            $trialWidth = is_array($box) ? (int) abs($box[2] - $box[0]) : 0;

            if ($current !== '' && $trialWidth > $maxWidthPx) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $trial;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * @param  \GdImage  $img
     */
    protected function imageToRaster($img): string
    {
        $width = imagesx($img);
        $height = imagesy($img);
        $widthBytes = (int) ceil($width / 8);

        // Pad width to multiple of 8 for ESC/POS.
        $paddedWidth = $widthBytes * 8;
        $data = '';

        for ($y = 0; $y < $height; $y++) {
            for ($xByte = 0; $xByte < $widthBytes; $xByte++) {
                $byte = 0;
                for ($bit = 0; $bit < 8; $bit++) {
                    $x = ($xByte * 8) + $bit;
                    if ($x >= $width) {
                        continue;
                    }
                    $rgb = imagecolorat($img, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    // Keep enough antialias so Arabic joins survive, but not so much
                    // that strokes fatten into a bold look.
                    if ((($r + $g + $b) / 3) < 190) {
                        $byte |= (0x80 >> $bit);
                    }
                }
                $data .= chr($byte);
            }
        }

        unset($paddedWidth);

        $xL = $widthBytes % 256;
        $xH = intdiv($widthBytes, 256);
        $yL = $height % 256;
        $yH = intdiv($height, 256);

        // GS v 0 m xL xH yL yH d1...dk  (m=0 normal density)
        return self::GS . 'v0' . chr(0) . chr($xL) . chr($xH) . chr($yL) . chr($yH) . $data;
    }

    protected function resolveFontPath(): ?string
    {
        // Prefer fonts with solid Arabic Presentation Forms coverage.
        $candidates = [
            'C:/Windows/Fonts/tahoma.ttf',
            'C:/Windows/Fonts/arial.ttf',
            'C:/Windows/Fonts/segoeui.ttf',
            '/usr/share/fonts/truetype/noto/NotoNaskhArabic-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        ];

        try {
            array_unshift(
                $candidates,
                public_path('fonts/NotoNaskhArabic-Regular.ttf'),
                public_path('fonts/Tahoma.ttf'),
                public_path('fonts/arial.ttf'),
                public_path('backend_assets/fonts/arial.ttf')
            );
        } catch (\Throwable) {
            // public_path may be unavailable outside full app bootstrap.
        }

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }
}
