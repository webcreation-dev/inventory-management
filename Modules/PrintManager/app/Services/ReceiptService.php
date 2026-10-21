<?php

namespace Modules\PrintManager\Services;

use Modules\PrintManager\Support\ReceiptBuilder;

class ReceiptService
{
    public function __construct(
        protected ESCPosService $escPosService
    ) {
    }

    /**
     * @param  array<string, mixed>  $receiptData
     * @return array<string, mixed>
     */
    public function buildFromArray(array $receiptData, string $preferredFormat = 'escpos'): array
    {
        $builder = ReceiptBuilder::make(
            (int) ($receiptData['characters_per_line'] ?? config('printmanager.receipt.characters_per_line', 42))
        );

        if (!empty($receiptData['title'])) {
            $builder->title((string) $receiptData['title']);
        }

        if (!empty($receiptData['header_lines']) && is_array($receiptData['header_lines'])) {
            foreach ($receiptData['header_lines'] as $line) {
                $builder->line((string) $line);
            }
        }

            if (!empty($receiptData['items']) && is_array($receiptData['items'])) {
            $builder->divider();
            foreach ($receiptData['items'] as $item) {
                $meta = is_array($item['meta'] ?? null) ? $item['meta'] : [];
                if (array_key_exists('total', $item)) {
                    $meta['total'] = $item['total'];
                }

                // alternative_name is already appended beside name by SalePrintBridgeService.
                // Do not duplicate it into meta lines.

                $builder->item(
                    (string) ($item['name'] ?? 'Item'),
                    (float) ($item['qty'] ?? 1),
                    (float) ($item['price'] ?? 0),
                    $meta
                );
            }
        }

        if (!empty($receiptData['totals']) && is_array($receiptData['totals'])) {
            $builder->divider();
            foreach ($receiptData['totals'] as $total) {
                $builder->total(
                    (string) ($total['label'] ?? 'Total'),
                    (float) ($total['amount'] ?? 0),
                    (bool) ($total['emphasis'] ?? false)
                );
            }
        }

        if (!empty($receiptData['barcode'])) {
            $builder->barcode((string) $receiptData['barcode']);
        }

        if (!empty($receiptData['qr_code'])) {
            $builder->qrCode((string) $receiptData['qr_code']);
        }

        $builder->footer(
            (string) ($receiptData['footer'] ?? config('printmanager.receipt.footer', 'Thank you'))
        );

        return $builder->build($preferredFormat);
    }

    /**
     * @param  array<string, mixed>  $receiptData
     */
    public function buildAllFormats(array $receiptData): array
    {
        return [
            'text' => $this->buildFromArray($receiptData, 'text'),
            'html' => $this->buildFromArray($receiptData, 'html'),
            'escpos' => $this->buildFromArray($receiptData, 'escpos'),
        ];
    }
}
