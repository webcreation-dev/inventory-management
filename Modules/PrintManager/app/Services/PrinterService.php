<?php

namespace Modules\PrintManager\Services;

use Illuminate\Support\Facades\Auth;
use Modules\PrintManager\Contracts\PrinterInterface;
use Modules\PrintManager\Contracts\PrinterSettingRepositoryInterface;
use Modules\PrintManager\DTO\PrintJobResult;
use Modules\PrintManager\Enums\PrintMethod;
use Modules\PrintManager\Models\PrintSetting;

class PrinterService
{
    /** @var array<string, PrinterInterface> */
    protected array $drivers = [];

    public function __construct(
        protected PrinterSettingRepositoryInterface $settings,
        protected PrintLogService $printLogs,
        protected ReceiptService $receipts,
        protected BrowserPrintService $browserPrintService,
        protected QZTrayService $qzTrayService,
        protected LocalAgentPrintService $localAgentPrintService,
    ) {
        $this->drivers = [
            PrintMethod::Browser->value => $this->browserPrintService,
            PrintMethod::QzTray->value => $this->qzTrayService,
            PrintMethod::LocalAgent->value => $this->localAgentPrintService,
        ];
    }

    public function resolveSettings(?int $companyId = null, ?int $branchId = null): PrintSetting
    {
        $companyId ??= (int) session('company.company_id');
        $branchId ??= session('outlet.outlet_id') ? (int) session('outlet.outlet_id') : null;

        $setting = $this->settings->findForScope($companyId, $branchId);

        if ($setting) {
            return $setting;
        }

        return new PrintSetting([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'default_printer' => null,
            'paper_size' => config('printmanager.default_paper_size', '80mm'),
            'print_method' => config('printmanager.default_method', 'qz_tray'),
            'copies' => (int) config('printmanager.default_copies', 1),
            'cash_drawer' => 'OFF',
            'status' => 'active',
            'character_encoding' => config('printmanager.default_character_encoding', 'UTF-8'),
            'margins' => config('printmanager.default_margins', []),
        ]);
    }

    /**
     * @param  array<string, mixed>  $receiptData
     * @param  array<string, mixed>  $options
     */
    public function printReceipt(array $receiptData, array $options = []): PrintJobResult
    {
        $formats = $this->receipts->buildAllFormats($receiptData);
        $setting = $this->resolveSettings(
            isset($options['company_id']) ? (int) $options['company_id'] : null,
            isset($options['branch_id']) ? (int) $options['branch_id'] : null
        );

        $method = $options['method'] ?? $setting->print_method ?? PrintMethod::QzTray->value;
        $format = $this->resolveFormatForMethod((string) $method);

        return $this->dispatchPrint([
            'type' => $format === 'escpos' ? 'raw' : $format,
            'content' => $formats[$format]['content'] ?? '',
        ], [
            'module' => $options['module'] ?? 'PrintManager',
            'method' => $method,
            'setting' => $setting,
            'copies' => $options['copies'] ?? $setting->copies,
            'open_cash_drawer' => $options['open_cash_drawer'] ?? $setting->isCashDrawerEnabled(),
            'allow_fallback' => $options['allow_fallback'] ?? true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function printHtml(string $html, array $options = []): PrintJobResult
    {
        return $this->dispatchPrint([
            'type' => 'html',
            'content' => $html,
        ], $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function printRaw(string $rawCommands, array $options = []): PrintJobResult
    {
        return $this->dispatchPrint([
            'type' => 'raw',
            'content' => $rawCommands,
        ], $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function printPdf(string $pdfBase64, array $options = []): PrintJobResult
    {
        return $this->dispatchPrint([
            'type' => 'pdf',
            'content' => $pdfBase64,
        ], $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function printText(string $text, array $options = []): PrintJobResult
    {
        return $this->dispatchPrint([
            'type' => 'text',
            'content' => $text,
        ], $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function testPrint(array $options = []): PrintJobResult
    {
        $setting = $this->resolveSettings(
            isset($options['company_id']) ? (int) $options['company_id'] : null,
            isset($options['branch_id']) ? (int) $options['branch_id'] : null
        );

        $method = $options['method'] ?? $setting->print_method ?? PrintMethod::QzTray->value;

        try {
            $driver = $this->driver((string) $method);
            $selectedPrinter = isset($options['default_printer']) ? trim((string) $options['default_printer']) : '';
            if ($selectedPrinter !== '') {
                $setting->default_printer = $selectedPrinter;
            }
            $job = $driver->prepareTestPrint([
                'setting' => $setting,
                'copies' => 1,
            ]);

            $log = $this->createLog([
                'module' => $options['module'] ?? 'PrintManager',
                'method' => $method,
                'setting' => $setting,
                'payload' => ['action' => 'test_print'],
            ]);

            return new PrintJobResult(
                success: true,
                method: (string) $method,
                payload: $job,
                logId: $log->id,
                message: 'Test print job prepared.',
            );
        } catch (\Throwable $exception) {
            return $this->handleFailure($exception, $options, $setting, ['action' => 'test_print']);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     */
    protected function dispatchPrint(array $payload, array $options = []): PrintJobResult
    {
        $setting = $options['setting'] ?? $this->resolveSettings(
            isset($options['company_id']) ? (int) $options['company_id'] : null,
            isset($options['branch_id']) ? (int) $options['branch_id'] : null
        );

        $method = (string) ($options['method'] ?? $setting->print_method ?? PrintMethod::QzTray->value);

        try {
            $driver = $this->driver($method);
            $job = $driver->preparePrint($payload, [
                'setting' => $setting,
                'copies' => $options['copies'] ?? $setting->copies,
                'open_cash_drawer' => $options['open_cash_drawer'] ?? false,
            ]);

            $log = $this->createLog([
                'module' => $options['module'] ?? 'PrintManager',
                'method' => $method,
                'setting' => $setting,
                'payload' => [
                    'type' => $payload['type'] ?? null,
                    'size' => is_string($payload['content'] ?? null) ? strlen($payload['content']) : null,
                ],
            ]);

            return new PrintJobResult(
                success: true,
                method: $method,
                payload: $job,
                logId: $log->id,
                message: 'Print job prepared successfully.',
            );
        } catch (\Throwable $exception) {
            $allowFallback = (bool) ($options['allow_fallback'] ?? true);

            if ($allowFallback && $method !== PrintMethod::Browser->value) {
                return $this->dispatchPrint($payload, array_merge($options, [
                    'method' => PrintMethod::Browser->value,
                    'allow_fallback' => false,
                ]));
            }

            return $this->handleFailure($exception, $options, $setting, $payload);
        }
    }

    protected function driver(string $method): PrinterInterface
    {
        if (!isset($this->drivers[$method])) {
            throw new \InvalidArgumentException("Unsupported print method [{$method}].", 422);
        }

        return $this->drivers[$method];
    }

    protected function resolveFormatForMethod(string $method): string
    {
        return match ($method) {
            PrintMethod::Browser->value => 'html',
            default => 'escpos',
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function createLog(array $meta): \Modules\PrintManager\Models\PrintLog
    {
        /** @var PrintSetting $setting */
        $setting = $meta['setting'];

        return $this->printLogs->create([
            'company_id' => $setting->company_id ?? session('company.company_id'),
            'branch_id' => $setting->branch_id ?? session('outlet.outlet_id'),
            'user_id' => Auth::id(),
            'module' => $meta['module'] ?? 'PrintManager',
            'printer' => $setting->default_printer,
            'print_method' => $meta['method'] ?? $setting->print_method,
            'status' => 'pending',
            'payload' => $meta['payload'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $payload
     */
    protected function handleFailure(
        \Throwable $exception,
        array $options,
        PrintSetting $setting,
        array $payload
    ): PrintJobResult {
        $log = $this->printLogs->create([
            'company_id' => $setting->company_id ?? session('company.company_id'),
            'branch_id' => $setting->branch_id ?? session('outlet.outlet_id'),
            'user_id' => Auth::id(),
            'module' => $options['module'] ?? 'PrintManager',
            'printer' => $setting->default_printer,
            'print_method' => $options['method'] ?? $setting->print_method,
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'payload' => $payload,
        ]);

        return PrintJobResult::failed(
            message: $exception->getMessage(),
            errorCode: (string) $exception->getCode(),
            logId: $log->id,
        );
    }
}
