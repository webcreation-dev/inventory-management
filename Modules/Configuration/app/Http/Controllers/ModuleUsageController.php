<?php

namespace Modules\Configuration\Http\Controllers;

use App\Services\ModuleUsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ModuleUsageController extends Controller
{
    public function __construct(
        protected ModuleUsageService $moduleUsageService
    ) {
    }

    public function index(): View
    {
        $definitions = $this->moduleUsageService->definitions();
        $modules = $this->moduleUsageService->all();

        return view('configuration::module-usage.index', [
            'definitions' => $definitions,
            'modules' => $modules,
            'businessTypeLabel' => $this->moduleUsageService->businessTypeLabel(),
            'businessType' => $this->moduleUsageService->businessType(),
            'productTypes' => $this->moduleUsageService->productTypes(),
            'isCompanyScoped' => $this->moduleUsageService->usesCompanyStorage(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $allowedKeys = array_keys($this->moduleUsageService->definitions());
        $payload = [];
        foreach ($allowedKeys as $key) {
            $payload[$key] = $request->boolean($key);
        }

        $this->moduleUsageService->save(
            $payload,
            $this->moduleUsageService->businessType(),
            $this->moduleUsageService->businessTypeLabel(),
            $this->moduleUsageService->productTypes()
        );

        return redirect()
            ->route('module-usage.index')
            ->with('success', __('Module usages updated successfully.'));
    }
}
