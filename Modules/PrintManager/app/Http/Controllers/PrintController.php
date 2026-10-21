<?php

namespace Modules\PrintManager\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\PrintManager\Http\Requests\PrintHtmlRequest;
use Modules\PrintManager\Http\Requests\PrintRawRequest;
use Modules\PrintManager\Http\Requests\PrintReceiptRequest;
use Modules\PrintManager\Http\Requests\UpdatePrintLogRequest;
use Modules\PrintManager\Models\PrintLog;
use Modules\PrintManager\Services\PrintLogService;
use Modules\PrintManager\Services\PrinterService;
use Modules\PrintManager\Support\PrintManager;

class PrintController extends Controller
{
    public function __construct(
        protected PrinterService $printerService,
        protected PrintLogService $printLogService,
    ) {
    }

    public function printReceipt(PrintReceiptRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = PrintManager::printReceipt($validated['receipt'], [
            'module' => $validated['module'] ?? 'PrintManager',
            'branch_id' => $validated['branch_id'] ?? null,
            'copies' => $validated['copies'] ?? null,
            'open_cash_drawer' => (bool) ($validated['open_cash_drawer'] ?? false),
            'allow_fallback' => array_key_exists('allow_fallback', $validated)
                ? (bool) $validated['allow_fallback']
                : true,
        ]);

        return $this->respond($result);
    }

    public function printHtml(PrintHtmlRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = PrintManager::printHtml($validated['html'], [
            'module' => $validated['module'] ?? 'PrintManager',
            'branch_id' => $validated['branch_id'] ?? null,
            'copies' => $validated['copies'] ?? null,
        ]);

        return $this->respond($result);
    }

    public function printRaw(PrintRawRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $raw = $validated['raw'];

        if (($validated['encoding'] ?? 'plain') === 'base64') {
            $decoded = base64_decode($raw, true);
            if ($decoded === false) {
                return response()->json([
                    'success' => false,
                    'message' => __('Invalid base64 raw payload.'),
                    'error_code' => 'invalid_base64',
                ], 422);
            }
            $raw = $decoded;
        }

        $result = PrintManager::printRaw($raw, [
            'module' => $validated['module'] ?? 'PrintManager',
            'branch_id' => $validated['branch_id'] ?? null,
            'copies' => $validated['copies'] ?? null,
            'open_cash_drawer' => (bool) ($validated['open_cash_drawer'] ?? false),
        ]);

        return $this->respond($result);
    }

    public function printPdf(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'copies' => ['nullable', 'integer', 'min:1', 'max:10'],
            'pdf' => ['required', 'string'],
        ]);

        $result = PrintManager::printPdf($validated['pdf'], [
            'module' => $validated['module'] ?? 'PrintManager',
            'branch_id' => $validated['branch_id'] ?? null,
            'copies' => $validated['copies'] ?? null,
        ]);

        return $this->respond($result);
    }

    public function updateLog(UpdatePrintLogRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $log = PrintLog::query()->findOrFail($validated['log_id']);

        if ($validated['status'] === 'success') {
            $this->printLogService->markSuccess($log, $validated['error_message'] ?? null);
        } else {
            $this->printLogService->markFailed($log, $validated['error_message'] ?? 'Print failed.');
        }

        return response()->json([
            'success' => true,
            'message' => __('Print log updated.'),
        ]);
    }

    protected function respond(\Modules\PrintManager\DTO\PrintJobResult $result): JsonResponse
    {
        $status = $result->success ? 200 : 422;

        return response()->json(array_merge([
            'success' => $result->success,
            'message' => $result->message,
        ], $result->toArray()), $status);
    }
}
