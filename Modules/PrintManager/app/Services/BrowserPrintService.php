<?php

namespace Modules\PrintManager\Services;

use Modules\PrintManager\Contracts\PrinterInterface;
use Modules\PrintManager\Models\PrintSetting;
use Modules\PrintManager\Support\ReceiptBuilder;

class BrowserPrintService implements PrinterInterface
{
    public function getDriverName(): string
    {
        return 'browser';
    }

    public function preparePrint(array $payload, array $context = []): array
    {
        $setting = $context['setting'] ?? null;
        $copies = (int) ($context['copies'] ?? 1);

        return [
            'driver' => $this->getDriverName(),
            'printer' => null,
            'copies' => $copies,
            'paper_size' => $setting instanceof PrintSetting ? $setting->paper_size : config('printmanager.default_paper_size'),
            'type' => $payload['type'] ?? 'html',
            'data' => $payload['content'] ?? '',
            'fallback' => false,
            'instructions' => [
                'action' => 'browser_print',
                'open_window' => true,
            ],
        ];
    }

    public function prepareTestPrint(array $context = []): array
    {
        $html = ReceiptBuilder::make()
            ->title('PrintManager Test Page')
            ->line('Browser print driver is working.')
            ->line('Date: ' . now()->format('Y-m-d H:i:s'))
            ->footer('Test successful')
            ->build('html');

        return $this->preparePrint([
            'type' => 'html',
            'content' => $html['content'],
        ], $context);
    }
}
