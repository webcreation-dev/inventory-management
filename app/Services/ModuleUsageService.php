<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Modules\Configuration\Models\Company;

class ModuleUsageService
{
    public const SESSION_KEY = 'module_usages';

    /**
     * @return array<string, array{label: string, type: string, description: string}>
     */
    public function definitions(): array
    {
        $labels = config('business_types.feature_labels', []);

        return [
            'warranty_guarantee' => [
                'label' => $labels['warranty_guarantee'] ?? 'Warranty & Guarantee',
                'type' => 'Feature',
                'description' => 'Warranty & guarantee fields on item add/edit',
            ],
            'pull_low_stock' => [
                'label' => $labels['pull_low_stock'] ?? 'Pull Low Stock',
                'type' => 'Feature',
                'description' => 'Pull low stock during purchase',
            ],
            'booking' => [
                'label' => $labels['booking'] ?? 'Booking',
                'type' => 'Module',
                'description' => 'Booking module',
            ],
            'rack' => [
                'label' => $labels['rack'] ?? 'Rack',
                'type' => 'Menu',
                'description' => 'Rack menu',
            ],
            'pharmacy_information' => [
                'label' => $labels['pharmacy_information'] ?? 'Pharmacy & Expiry',
                'type' => 'Item Add / Edit',
                'description' => 'Expiry date, generic name, rack on item add/edit',
            ],
            'fiscal_printer' => [
                'label' => $labels['fiscal_printer'] ?? 'Fiscal Printer',
                'type' => 'Feature',
                'description' => 'Fiscal printer support',
            ],
            'weight_scale' => [
                'label' => $labels['weight_scale'] ?? 'Weight Scale',
                'type' => 'Feature',
                'description' => 'Weight scale integration',
            ],
            'double_unit' => [
                'label' => $labels['double_unit'] ?? 'Double Unit',
                'type' => 'Feature',
                'description' => 'Double unit support',
            ],
            'loyalty_feature' => [
                'label' => $labels['loyalty_feature'] ?? 'Loyalty Feature',
                'type' => 'Feature',
                'description' => 'Customer loyalty program',
            ],
            'warranty' => [
                'label' => $labels['warranty'] ?? 'Warranty and Servicing',
                'type' => 'Module',
                'description' => 'Warranty and servicing module',
            ],
            'installment_sale' => [
                'label' => $labels['installment_sale'] ?? 'Installment',
                'type' => 'Module',
                'description' => 'Installment sale module',
            ],
            'installment_customer' => [
                'label' => $labels['installment_customer'] ?? 'Installment Customer',
                'type' => 'Menu',
                'description' => 'Installment customer menu',
            ],
            'variation_information' => [
                'label' => $labels['variation_information'] ?? 'Variation Information',
                'type' => 'Item Add / Edit',
                'description' => 'Variation information (e.g. Generate Variation)',
            ],
            'variation_attribute' => [
                'label' => $labels['variation_attribute'] ?? 'Variation Attribute',
                'type' => 'Menu',
                'description' => 'Variation attribute menu',
            ],
            'multiple_currency' => [
                'label' => $labels['multiple_currency'] ?? 'Multiple Currency',
                'type' => 'Feature',
                'description' => 'Multiple currency support',
            ],
            'denomination' => [
                'label' => $labels['denomination'] ?? 'Denomination',
                'type' => 'Feature',
                'description' => 'Cash denomination support',
            ],
            'fixed_assets' => [
                'label' => $labels['fixed_assets'] ?? 'Fixed Assets',
                'type' => 'Feature',
                'description' => 'Fixed assets management',
            ],
            'zatca' => [
                'label' => $labels['zatca'] ?? 'Zatca',
                'type' => 'Feature',
                'description' => 'ZATCA e-invoicing',
            ],
            'gst' => [
                'label' => $labels['gst'] ?? 'GST',
                'type' => 'Feature',
                'description' => 'GST tax support',
            ],
        ];
    }

    public function filePath(): string
    {
        return resource_path('module/module.json');
    }

    /**
     * @return array<string, bool>
     */
    public function defaults(): array
    {
        $defaults = [];
        foreach (array_keys($this->definitions()) as $key) {
            $defaults[$key] = false;
        }

        return $defaults;
    }

    /**
     * @return array{business_type: ?string, business_type_label: ?string, product_types: array<int, string>, modules: array<string, bool>}
     */
    public function defaultPayload(): array
    {
        return [
            'business_type' => null,
            'business_type_label' => null,
            'product_types' => [],
            'modules' => $this->defaults(),
        ];
    }

    public function usesCompanyStorage(?int $companyId = null): bool
    {
        if (! $this->companyStorageAvailable()) {
            return false;
        }

        $resolvedId = $this->resolveCompanyId($companyId);
        if ($resolvedId <= 0) {
            return false;
        }

        if (config('app.is_saas_env') || config('saas.enabled')) {
            return true;
        }

        $company = Company::query()->find($resolvedId);

        return $company !== null && filter_var($company->is_saas ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * True when reading/writing a specific company row (landlord edit, or SaaS tenant session).
     */
    protected function shouldUseCompanyRow(?int $companyId = null): bool
    {
        if (! $this->companyStorageAvailable()) {
            return false;
        }

        if ($companyId !== null && $companyId > 0) {
            return true;
        }

        return $this->usesCompanyStorage(null);
    }

    public function businessType(?int $companyId = null): ?string
    {
        $data = $this->readPayload($companyId);

        return is_string($data['business_type'] ?? null) && $data['business_type'] !== ''
            ? $data['business_type']
            : null;
    }

    public function businessTypeLabel(?int $companyId = null): ?string
    {
        $data = $this->readPayload($companyId);

        return is_string($data['business_type_label'] ?? null) && $data['business_type_label'] !== ''
            ? $data['business_type_label']
            : null;
    }

    /**
     * @return array<int, string>
     */
    public function productTypes(?int $companyId = null): array
    {
        $data = $this->readPayload($companyId);
        $productTypes = $data['product_types'] ?? [];

        return is_array($productTypes) ? array_values($productTypes) : [];
    }

    /**
     * @return array<string, bool>
     */
    public function all(?int $companyId = null): array
    {
        $defaults = $this->defaults();
        $data = $this->readPayload($companyId);
        $stored = is_array($data['modules'] ?? null) ? $data['modules'] : [];

        $result = $defaults;
        foreach ($defaults as $key => $default) {
            $result[$key] = filter_var($stored[$key] ?? $default, FILTER_VALIDATE_BOOLEAN);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, bool>
     */
    public function save(
        array $input,
        ?string $businessType = null,
        ?string $businessTypeLabel = null,
        ?array $productTypes = null,
        ?int $companyId = null
    ): array {
        $modules = $this->defaults();
        foreach ($modules as $key => $_) {
            $modules[$key] = filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        $payload = [
            'business_type' => $businessType ?? $this->businessType($companyId),
            'business_type_label' => $businessTypeLabel ?? $this->businessTypeLabel($companyId),
            'product_types' => $productTypes ?? $this->productTypes($companyId),
            'modules' => $modules,
        ];

        $this->writePayload($payload, $companyId);

        if ($companyId === null || $companyId === $this->resolveCompanyId(null)) {
            $this->syncSession($modules, $payload);
        }

        return $modules;
    }

    /**
     * Seed module usage for a newly created SaaS company.
     *
     * @param  array<string, mixed>|null  $payload
     */
    public function seedForCompany(Company $company, ?array $payload = null): void
    {
        if (! $this->companyStorageAvailable()) {
            return;
        }

        $payload = $this->normalizePayload($payload ?? $this->defaultPayload());
        $company->module_usage = $payload;
        $company->save();
    }

    /**
     * Apply a business-type preset and save for a company.
     *
     * @return array<string, bool>
     */
    public function applyBusinessTypeForCompany(int $companyId, string $businessTypeSlug, ?array $moduleOverrides = null): array
    {
        /** @var BusinessTypeService $businessTypes */
        $businessTypes = app(BusinessTypeService::class);
        if (! $businessTypes->isValid($businessTypeSlug)) {
            return $this->all($companyId);
        }

        $type = $businessTypes->types()[$businessTypeSlug];
        $modules = $moduleOverrides !== null
            ? $businessTypes->normalizeSubmittedModules($businessTypeSlug, $moduleOverrides)
            : $businessTypes->defaultModulesForType($businessTypeSlug);

        return $this->save(
            $modules,
            $businessTypeSlug,
            $type['label'],
            $type['product_types'],
            $companyId
        );
    }

    /**
     * @param  array<string, bool>|null  $modules
     * @param  array<string, mixed>|null  $payload
     */
    public function syncSession(?array $modules = null, ?array $payload = null): void
    {
        $payload = $payload ?? $this->readPayload();
        $modules = $modules ?? $this->modulesFromPayload($payload);

        session()->put(self::SESSION_KEY, $modules);
        session()->put('module_product_types', is_array($payload['product_types'] ?? null) ? array_values($payload['product_types']) : []);
        session()->put('module_business_type', $payload['business_type'] ?? null);
        session()->put('module_business_type_label', $payload['business_type_label'] ?? null);
    }

    public function forgetSession(): void
    {
        session()->forget([
            self::SESSION_KEY,
            'module_product_types',
            'module_business_type',
            'module_business_type_label',
        ]);
    }

    public function isEnabled(string $key): bool
    {
        $modules = session(self::SESSION_KEY);
        if (! is_array($modules)) {
            $modules = $this->all();
        }

        return filter_var($modules[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array{business_type: ?string, business_type_label: ?string, product_types: array<int, string>, modules: array<string, bool>}
     */
    public function readPayload(?int $companyId = null): array
    {
        if ($this->shouldUseCompanyRow($companyId)) {
            $company = Company::query()->find($this->resolveCompanyId($companyId));
            if ($company !== null) {
                $stored = $company->module_usage;
                if (is_string($stored)) {
                    $stored = json_decode($stored, true);
                }
                if (is_array($stored) && $stored !== []) {
                    return $this->normalizePayload($stored);
                }
            }

            return $this->defaultPayload();
        }

        return $this->normalizePayload($this->readFileData());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function writePayload(array $payload, ?int $companyId = null): void
    {
        $payload = $this->normalizePayload($payload);

        if ($this->shouldUseCompanyRow($companyId)) {
            $company = Company::query()->find($this->resolveCompanyId($companyId));
            if ($company === null) {
                return;
            }
            $company->module_usage = $payload;
            $company->save();

            return;
        }

        $this->ensureFile($payload);
    }

    protected function resolveCompanyId(?int $companyId = null): int
    {
        if ($companyId !== null && $companyId > 0) {
            return $companyId;
        }

        return (int) session('company.company_id', 0);
    }

    protected function companyStorageAvailable(): bool
    {
        try {
            return Schema::hasTable('companies') && Schema::hasColumn('companies', 'module_usage');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{business_type: ?string, business_type_label: ?string, product_types: array<int, string>, modules: array<string, bool>}
     */
    public function normalizePayload(array $payload): array
    {
        $modulesSource = is_array($payload['modules'] ?? null) ? $payload['modules'] : $payload;
        $modules = $this->defaults();
        foreach ($modules as $key => $default) {
            $modules[$key] = filter_var($modulesSource[$key] ?? $default, FILTER_VALIDATE_BOOLEAN);
        }

        $productTypes = $payload['product_types'] ?? [];
        if (! is_array($productTypes)) {
            $productTypes = [];
        }

        return [
            'business_type' => is_string($payload['business_type'] ?? null) ? $payload['business_type'] : null,
            'business_type_label' => is_string($payload['business_type_label'] ?? null) ? $payload['business_type_label'] : null,
            'product_types' => array_values($productTypes),
            'modules' => $modules,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, bool>
     */
    protected function modulesFromPayload(array $payload): array
    {
        return $this->normalizePayload($payload)['modules'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function readFileData(): array
    {
        $path = $this->filePath();
        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function ensureFile(array $payload): void
    {
        $directory = dirname($this->filePath());
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put(
            $this->filePath(),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }
}
