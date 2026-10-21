<?php

namespace Modules\Sale\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sale\Services\SalePrintBridgeService;

class SalePrintController extends Controller
{
    public function __construct(
        protected SalePrintBridgeService $salePrintBridgeService,
    ) {
    }

    /**
     * Prepare a sale receipt print job via PrintManager (for QZ Tray / browser fallback).
     */
    public function printReceipt(Request $request): JsonResponse
    {
        $request->validate([
            'sale_id' => ['required', 'string'],
        ]);

        $result = $this->salePrintBridgeService->prepareSaleReceiptPrint($request->input('sale_id'));

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => __('Sale not found or printer is not configured.'),
                'error_code' => 'sale_or_printer_not_found',
            ], 422);
        }

        return response()->json(array_merge([
            'success' => $result->success,
            'message' => $result->message,
        ], $result->toArray()), $result->success ? 200 : 422);
    }
}
