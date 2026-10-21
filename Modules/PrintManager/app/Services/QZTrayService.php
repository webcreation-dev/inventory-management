<?php

namespace Modules\PrintManager\Services;

use Modules\PrintManager\Contracts\PrinterInterface;
use Modules\PrintManager\Models\PrintSetting;

class QZTrayService implements PrinterInterface
{
    public function __construct(
        protected ESCPosService $escPosService,
        protected QzSecurityService $qzSecurity,
    ) {
    }

    public function getDriverName(): string
    {
        return 'qz_tray';
    }

    public function preparePrint(array $payload, array $context = []): array
    {
        /** @var PrintSetting|null $setting */
        $setting = $context['setting'] ?? null;
        $printer = $setting?->default_printer;
        $copies = (int) ($context['copies'] ?? $setting?->copies ?? 1);
        $type = $payload['type'] ?? 'raw';

        if (!$printer) {
            throw new \RuntimeException('No default printer configured.', 422);
        }

        $data = $payload['content'] ?? '';
        if ($type === 'raw' && is_string($data)) {
            $data = base64_encode($data);
        }

        $job = [
            'driver' => $this->getDriverName(),
            'printer' => $printer,
            'copies' => max(1, $copies),
            'paper_size' => $setting?->paper_size ?? config('printmanager.default_paper_size'),
            'encoding' => 'UTF-8',
            'margins' => $setting?->margins ?? config('printmanager.default_margins'),
            'type' => $type,
            'data' => $data,
            'fallback' => false,
            'qz' => [
                'env' => config('printmanager.env', 'development'),
                'signature_algorithm' => $this->qzSecurity->signatureAlgorithm(),
                'certificate_url' => route('printmanager.qz.certificate'),
                'sign_url' => route('printmanager.qz.sign'),
                'status_url' => route('printmanager.qz.status'),
                'reconnect_interval_ms' => config('printmanager.qz.reconnect_interval_ms'),
                'connection_timeout_ms' => config('printmanager.qz.connection_timeout_ms'),
                'host' => config('printmanager.qz.host', 'localhost'),
                'security' => $this->qzSecurity->securityStatus(),
            ],
            'instructions' => [
                'action' => 'qz_print',
            ],
        ];

        if (!empty($context['open_cash_drawer'])) {
            $job['cash_drawer'] = base64_encode($this->escPosService->openCashDrawerCommand());
        }

        return $job;
    }

    public function prepareTestPrint(array $context = []): array
    {
        /** @var PrintSetting|null $setting */
        $setting = $context['setting'] ?? null;
        $printerName = $setting?->default_printer ?? 'Default Printer';

        return $this->preparePrint([
            'type' => 'raw',
            'content' => $this->escPosService->buildTestPage($printerName),
        ], $context);
    }
}
