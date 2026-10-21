<?php

namespace Modules\PrintManager\Support;

use Modules\PrintManager\DTO\PrintJobResult;
use Modules\PrintManager\Services\PrinterService;

/**
 * Public API for other modules.
 *
 * Example:
 * PrintManager::printReceipt($receiptData, ['module' => 'Sale', 'branch_id' => 1]);
 */
class PrintManager
{
    public static function printReceipt(array $receiptData, array $options = []): PrintJobResult
    {
        return app(PrinterService::class)->printReceipt($receiptData, $options);
    }

    public static function printHtml(string $html, array $options = []): PrintJobResult
    {
        return app(PrinterService::class)->printHtml($html, $options);
    }

    public static function printRaw(string $commands, array $options = []): PrintJobResult
    {
        return app(PrinterService::class)->printRaw($commands, $options);
    }

    public static function printPdf(string $pdfBase64, array $options = []): PrintJobResult
    {
        return app(PrinterService::class)->printPdf($pdfBase64, $options);
    }

    public static function printText(string $text, array $options = []): PrintJobResult
    {
        return app(PrinterService::class)->printText($text, $options);
    }

    public static function testPrint(array $options = []): PrintJobResult
    {
        return app(PrinterService::class)->testPrint($options);
    }
}
