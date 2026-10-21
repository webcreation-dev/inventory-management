<?php

namespace Modules\PrintManager\Support;

class ReceiptBuilder
{
    /** @var array<int, array<string, mixed>> */
    protected array $lines = [];

    protected ?string $title = null;

    protected ?string $footer = null;

    protected int $charactersPerLine;

    public function __construct(?int $charactersPerLine = null)
    {
        $this->charactersPerLine = $charactersPerLine
            ?? (int) config('printmanager.receipt.characters_per_line', 42);
    }

    public static function make(?int $charactersPerLine = null): self
    {
        return new self($charactersPerLine);
    }

    public function title(string $text): self
    {
        $this->title = $text;

        return $this;
    }

    public function line(string $text, string $align = 'left'): self
    {
        $this->lines[] = [
            'type' => 'line',
            'text' => $text,
            'align' => $align,
        ];

        return $this;
    }

    public function divider(): self
    {
        $this->lines[] = ['type' => 'divider'];

        return $this;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function item(string $name, float $qty, float $price, array $meta = []): self
    {
        $total = array_key_exists('total', $meta)
            ? (float) $meta['total']
            : round($qty * $price, 3);

        $this->lines[] = [
            'type' => 'item',
            'name' => $name,
            'qty' => $qty,
            'price' => $price,
            'total' => $total,
            'meta' => $meta,
        ];

        return $this;
    }

    public function total(string $label, float $amount, bool $emphasis = true): self
    {
        $this->lines[] = [
            'type' => 'total',
            'label' => $label,
            'amount' => $amount,
            'emphasis' => $emphasis,
        ];

        return $this;
    }

    public function barcode(string $value, string $type = 'code128'): self
    {
        $this->lines[] = [
            'type' => 'barcode',
            'value' => $value,
            'barcode_type' => $type,
        ];

        return $this;
    }

    public function qrCode(string $value): self
    {
        $this->lines[] = [
            'type' => 'qrcode',
            'value' => $value,
        ];

        return $this;
    }

    public function footer(string $text): self
    {
        $this->footer = $text;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $format = 'text'): array
    {
        $structure = [
            'title' => $this->title,
            'lines' => $this->lines,
            'footer' => $this->footer,
            'characters_per_line' => $this->charactersPerLine,
        ];

        return match ($format) {
            'html' => [
                'format' => 'html',
                'content' => $this->buildHtml($structure),
                'structure' => $structure,
            ],
            'escpos' => [
                'format' => 'escpos',
                'content' => app(\Modules\PrintManager\Services\ESCPosService::class)
                    ->buildFromStructure($structure),
                'structure' => $structure,
            ],
            default => [
                'format' => 'text',
                'content' => $this->buildText($structure),
                'structure' => $structure,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $structure
     */
    protected function buildText(array $structure): string
    {
        $output = [];
        $width = (int) ($structure['characters_per_line'] ?? $this->charactersPerLine);

        if (!empty($structure['title'])) {
            $output[] = $this->center((string) $structure['title'], $width);
        }

        foreach ($structure['lines'] as $line) {
            $output[] = match ($line['type']) {
                'divider' => str_repeat('-', $width),
                'item' => $this->formatItemBlock($line, $width),
                'total' => $this->formatTotalLine($line, $width),
                'barcode' => 'BARCODE: ' . ($line['value'] ?? ''),
                'qrcode' => 'QR: ' . ($line['value'] ?? ''),
                default => (string) ($line['text'] ?? ''),
            };
        }

        if (!empty($structure['footer'])) {
            $output[] = '';
            $output[] = $this->center((string) $structure['footer'], $width);
        }

        return implode("\n", $output);
    }

    /**
     * @param  array<string, mixed>  $structure
     */
    protected function buildHtml(array $structure): string
    {
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">'
            . '<style>body{margin:0;padding:0;} .print-receipt{font-family:Arial,Tahoma,"Segoe UI",sans-serif;font-size:12px;width:100%;color:#000;}</style>'
            . '</head><body><div class="print-receipt">';

        if (!empty($structure['title'])) {
            $html .= '<div style="text-align:center;font-weight:bold;margin-bottom:8px;">'
                . e((string) $structure['title']) . '</div>';
        }

        foreach ($structure['lines'] as $line) {
            $html .= match ($line['type']) {
                'divider' => '<hr style="border:none;border-top:1px dashed #000;margin:6px 0;">',
                'item' => $this->formatItemHtml($line),
                'total' => '<div style="display:flex;justify-content:space-between;font-weight:'
                    . (!empty($line['emphasis']) ? 'bold' : 'normal') . ';">'
                    . '<span>' . e((string) $line['label']) . '</span>'
                    . '<span>' . e(number_format((float) $line['amount'], 2)) . '</span></div>',
                'barcode' => '<div style="text-align:center;margin:8px 0;">BARCODE: '
                    . e((string) ($line['value'] ?? '')) . '</div>',
                'qrcode' => '<div style="text-align:center;margin:8px 0;">QR: '
                    . e((string) ($line['value'] ?? '')) . '</div>',
                default => '<div>' . e((string) ($line['text'] ?? '')) . '</div>',
            };
        }

        if (!empty($structure['footer'])) {
            $html .= '<div style="text-align:center;margin-top:8px;">'
                . e((string) $structure['footer']) . '</div>';
        }

        $html .= '</div></body></html>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    protected function formatItemBlock(array $line, int $width): string
    {
        $qty = $this->formatQtyValue($line['qty'] ?? 0);
        $unit = number_format((float) ($line['price'] ?? 0), 2);
        $total = number_format((float) ($line['total'] ?? 0), 2);
        $rows = [
            (string) ($line['name'] ?? ''),
            $this->leftRight('  ' . $qty . ' x ' . $unit, $total, $width),
        ];

        foreach (($line['meta']['lines'] ?? []) as $metaLine) {
            $meta = trim((string) $metaLine);
            if ($meta !== '') {
                $rows[] = '  ' . $meta;
            }
        }

        return implode("\n", $rows);
    }

    /**
     * @param  array<string, mixed>  $line
     */
    protected function formatItemHtml(array $line): string
    {
        $qty = e($this->formatQtyValue($line['qty'] ?? 0));
        $unit = e(number_format((float) ($line['price'] ?? 0), 2));
        $total = e(number_format((float) ($line['total'] ?? 0), 2));
        $html = '<div style="margin-bottom:4px;">'
            . '<div>' . e((string) ($line['name'] ?? '')) . '</div>'
            . '<div style="display:flex;justify-content:space-between;">'
            . '<span>' . $qty . ' x ' . $unit . '</span>'
            . '<span>' . $total . '</span></div>';

        foreach (($line['meta']['lines'] ?? []) as $metaLine) {
            $meta = trim((string) $metaLine);
            if ($meta !== '') {
                $html .= '<div style="font-size:11px;color:#444;">' . e($meta) . '</div>';
            }
        }

        return $html . '</div>';
    }

    protected function formatQtyValue(mixed $qty): string
    {
        $value = (float) $qty;
        if (abs($value - round($value)) < 0.00001) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    /**
     * @param  array<string, mixed>  $line
     */
    protected function formatTotalLine(array $line, int $width): string
    {
        return $this->leftRight(
            (string) ($line['label'] ?? 'Total'),
            number_format((float) ($line['amount'] ?? 0), 2),
            $width
        );
    }

    protected function center(string $text, int $width): string
    {
        $text = trim($text);
        if (strlen($text) >= $width) {
            return $text;
        }

        $padding = (int) floor(($width - strlen($text)) / 2);

        return str_repeat(' ', max(0, $padding)) . $text;
    }

    protected function leftRight(string $left, string $right, int $width): string
    {
        $space = $width - strlen($left) - strlen($right);

        if ($space < 1) {
            return $left . ' ' . $right;
        }

        return $left . str_repeat(' ', $space) . $right;
    }
}
