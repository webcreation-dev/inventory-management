<?php

namespace Modules\PrintManager\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Modules\Configuration\Models\Outlet;
use Modules\PrintManager\Http\Requests\PrintTestRequest;
use Modules\PrintManager\Services\PrinterService;
use Modules\PrintManager\Support\PrintManager;

class PrinterTestController extends Controller
{
    public function __construct(
        protected PrinterService $printerService,
    ) {
    }

    public function index(): View
    {
        $companyId = (int) session('company.company_id');
        $branchId = session('outlet.outlet_id') ? (int) session('outlet.outlet_id') : null;
        $setting = $this->printerService->resolveSettings($companyId, $branchId);
        $outlets = Outlet::query()
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('outlet_name')
            ->get(['id', 'outlet_name']);

        $printMethod = \Modules\PrintManager\Enums\PrintMethod::tryFrom((string) $setting->print_method);

        return view('printmanager::test.index', [
            'setting' => $setting,
            'outlets' => $outlets,
            'printMethodLabel' => $printMethod?->label() ?? (string) $setting->print_method,
            'printMethodValue' => (string) $setting->print_method,
        ]);
    }

    public function test(PrintTestRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = PrintManager::testPrint([
            'module' => 'PrintManager',
            'branch_id' => $validated['branch_id'] ?? null,
            'method' => $validated['print_method'] ?? null,
            'default_printer' => $validated['default_printer'] ?? null,
        ]);

        return response()->json(array_merge([
            'success' => $result->success,
            'message' => $result->message,
        ], $result->toArray()), $result->success ? 200 : 422);
    }
}
