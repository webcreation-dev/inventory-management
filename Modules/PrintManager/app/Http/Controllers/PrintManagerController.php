<?php

namespace Modules\PrintManager\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\PrintManager\Models\PrintLog;
use Modules\PrintManager\Models\PrintSetting;
use Modules\PrintManager\Services\PrinterService;

class PrintManagerController extends Controller
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

        $recentLogs = PrintLog::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $settingsCount = PrintSetting::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->count();

        return view('printmanager::index', [
            'setting' => $setting,
            'recentLogs' => $recentLogs,
            'settingsCount' => $settingsCount,
        ]);
    }
}
