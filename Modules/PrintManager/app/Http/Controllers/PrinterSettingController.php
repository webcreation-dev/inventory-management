<?php

namespace Modules\PrintManager\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Configuration\Models\Outlet;
use Modules\PrintManager\Contracts\PrinterSettingRepositoryInterface;
use Modules\PrintManager\Http\Requests\StorePrintSettingRequest;
use Modules\PrintManager\Services\PrinterService;

class PrinterSettingController extends Controller
{
    public function __construct(
        protected PrinterSettingRepositoryInterface $settings,
        protected PrinterService $printerService,
    ) {
    }

    public function index(Request $request): View
    {
        $companyId = (int) session('company.company_id');
        $branchId = $request->integer('branch_id') ?: (session('outlet.outlet_id') ? (int) session('outlet.outlet_id') : null);
        $setting = $this->printerService->resolveSettings($companyId, $branchId);
        $outlets = Outlet::query()
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->orderBy('outlet_name')
            ->get(['id', 'outlet_name']);

        return view('printmanager::settings.index', [
            'setting' => $setting,
            'outlets' => $outlets,
            'paperSizes' => config('printmanager.paper_sizes', []),
            'printMethods' => config('printmanager.methods', []),
            'selectedBranchId' => $branchId,
        ]);
    }

    public function list(Request $request): View
    {
        $companyId = (int) session('company.company_id');
        $settings = $this->settings->listByCompany($companyId);

        return view('printmanager::printers.index', [
            'settings' => $settings,
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $companyId = (int) session('company.company_id');
        $branchId = $request->integer('branch_id') ?: null;
        $setting = $this->printerService->resolveSettings($companyId, $branchId);

        return response()->json([
            'success' => true,
            'data' => $setting,
        ]);
    }

    public function store(StorePrintSettingRequest $request): JsonResponse
    {
        $companyId = (int) session('company.company_id');
        $validated = $request->validated();
        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;

        $setting = $this->settings->upsertForScope($companyId, $branchId, [
            'default_printer' => $validated['default_printer'] ?? null,
            'paper_size' => $validated['paper_size'],
            'print_method' => $validated['print_method'],
            'copies' => $validated['copies'],
            'cash_drawer' => $validated['cash_drawer'],
            'status' => $validated['status'],
            'character_encoding' => $validated['character_encoding'],
            'margins' => $validated['margins'] ?? config('printmanager.default_margins'),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Print settings saved successfully.'),
            'data' => $setting,
        ]);
    }
}
