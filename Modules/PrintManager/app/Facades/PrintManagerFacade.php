<?php

namespace Modules\PrintManager\Facades;

use Illuminate\Support\Facades\Facade;
use Modules\PrintManager\DTO\PrintJobResult;
use Modules\PrintManager\Services\PrinterService;

/**
 * @method static PrintJobResult printReceipt(array $receiptData, array $options = [])
 * @method static PrintJobResult printHtml(string $html, array $options = [])
 * @method static PrintJobResult printRaw(string $commands, array $options = [])
 * @method static PrintJobResult printPdf(string $pdfBase64, array $options = [])
 * @method static PrintJobResult printText(string $text, array $options = [])
 * @method static PrintJobResult testPrint(array $options = [])
 *
 * @see PrinterService
 */
class PrintManagerFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PrinterService::class;
    }
}
