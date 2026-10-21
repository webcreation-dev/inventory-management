<?php

namespace App\Services;

class BusinessTypeService
{
    public function usesBengaliLabels(): bool
    {
        return lp_is_bd();
    }

    public function displayLabel(string $slug): ?string
    {
        $type = $this->types()[$slug] ?? null;
        if ($type === null) {
            return null;
        }

        if ($this->usesBengaliLabels()) {
            $bengaliLabel = config("business_types.type_labels_bn.{$slug}");
            if (is_string($bengaliLabel) && $bengaliLabel !== '') {
                return $bengaliLabel;
            }
        }

        return $type['label'] ?? null;
    }

    /**
     * @return array<string, string> slug => label
     */
    public function options(): array
    {
        $options = [];
        foreach ($this->types() as $slug => $type) {
            $options[$slug] = $this->displayLabel($slug) ?? $type['label'];
        }

        return $options;
    }

    /**
     * @return array<string, array{label: string, product_types: array<int, string>, core: array<int, string>, optional: array<int, string>}>
     */
    public function types(): array
    {
        return config('business_types.types', []);
    }

    /**
     * @return array<int, array{label: string, notes: string|null}>
     */
    public function commonModules(): array
    {
        return config('business_types.common_modules', []);
    }

    /**
     * @return array<string, string>
     */
    public function featureLabels(): array
    {
        return config('business_types.feature_labels', []);
    }

    /**
     * @return array{purpose: string, when_needed: string}
     */
    public function featureHelp(string $key): array
    {
        $help = config("business_types.feature_help.{$key}", []);
        if (! is_array($help)) {
            return ['purpose' => '', 'when_needed' => ''];
        }

        if ($this->usesBengaliLabels()) {
            return [
                'purpose' => (string) ($help['purpose_bn'] ?? $help['purpose'] ?? ''),
                'when_needed' => (string) ($help['when_needed_bn'] ?? $help['when_needed'] ?? ''),
            ];
        }

        return [
            'purpose' => (string) ($help['purpose'] ?? ''),
            'when_needed' => (string) ($help['when_needed'] ?? ''),
        ];
    }

    public function label(string $slug): ?string
    {
        return $this->displayLabel($slug);
    }

    public function isValid(string $slug): bool
    {
        return array_key_exists($slug, $this->types());
    }

    /**
     * @return array{
     *     slug: string,
     *     label: string,
     *     product_types: array<int, string>,
     *     common_modules: array<int, array{label: string, notes: string|null}>,
     *     features: array<int, array{key: string, label: string, status: string, checked: bool, help: array{purpose: string, when_needed: string}}>
     * }
     */
    public function featurePayload(string $slug, ?array $selectedModules = null): array
    {
        $type = $this->types()[$slug] ?? null;
        if ($type === null) {
            return [
                'slug' => $slug,
                'label' => '',
                'product_types' => [],
                'common_modules' => $this->commonModules(),
                'features' => [],
            ];
        }

        $defaults = $this->defaultModulesForType($slug);
        $modules = $selectedModules ?? $defaults;
        $features = [];

        foreach ($this->visibleFeatureKeys($slug) as $key) {
            $status = $this->featureStatus($slug, $key);
            $features[] = [
                'key' => $key,
                'label' => $this->featureLabels()[$key] ?? $key,
                'status' => $status,
                'checked' => filter_var($modules[$key] ?? false, FILTER_VALIDATE_BOOLEAN),
                'help' => $this->featureHelp($key),
            ];
        }

        return [
            'slug' => $slug,
            'label' => $this->displayLabel($slug) ?? $type['label'],
            'product_types' => $type['product_types'],
            'common_modules' => $this->commonModules(),
            'features' => $features,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function defaultModulesForType(string $slug): array
    {
        $type = $this->types()[$slug] ?? null;
        if ($type === null) {
            return app(ModuleUsageService::class)->defaults();
        }

        $modules = app(ModuleUsageService::class)->defaults();
        foreach ($modules as $key => $_) {
            $modules[$key] = in_array($key, $type['core'] ?? [], true);
        }

        return $this->applyDerivedModules($modules, $type['product_types'] ?? []);
    }

    /**
     * @param  array<string, bool>  $modules
     * @return array<string, bool>
     */
    public function applyDerivedModules(array $modules, array $productTypes): array
    {
        $hasVariation = $this->productTypesIncludeVariation($productTypes);
        $modules['variation_information'] = $hasVariation;
        $modules['variation_attribute'] = $hasVariation;
        $modules['installment_customer'] = filter_var($modules['installment_sale'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $modules;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, bool>
     */
    public function normalizeSubmittedModules(string $slug, array $input): array
    {
        $modules = app(ModuleUsageService::class)->defaults();
        $type = $this->types()[$slug];
        $visible = $this->visibleFeatureKeys($slug);

        foreach ($modules as $key => $_) {
            if (! in_array($key, $visible, true)) {
                $modules[$key] = false;
                continue;
            }

            if (in_array($key, $type['core'] ?? [], true)) {
                $modules[$key] = true;
                continue;
            }

            $modules[$key] = filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        return $this->applyDerivedModules($modules, $type['product_types'] ?? []);
    }

    /**
     * @return array<int, string>
     */
    public function visibleFeatureKeys(string $slug): array
    {
        $type = $this->types()[$slug] ?? null;
        if ($type === null) {
            return [];
        }

        $keys = array_values(array_unique(array_merge($type['core'] ?? [], $type['optional'] ?? [])));

        if ($this->productTypesIncludeVariation($type['product_types'] ?? [])) {
            $keys[] = 'variation_information';
            $keys[] = 'variation_attribute';
        }

        if (in_array('installment_sale', $keys, true) || in_array('installment_sale', $type['core'] ?? [], true)) {
            $keys[] = 'installment_customer';
        }

        return array_values(array_unique($keys));
    }

    public function featureStatus(string $slug, string $key): string
    {
        $type = $this->types()[$slug] ?? null;
        if ($type === null) {
            return 'remove';
        }

        if (in_array($key, $type['core'] ?? [], true)) {
            return 'core';
        }

        if (in_array($key, $type['optional'] ?? [], true)) {
            return 'optional';
        }

        if (in_array($key, ['variation_information', 'variation_attribute'], true)
            && $this->productTypesIncludeVariation($type['product_types'] ?? [])) {
            return 'core';
        }

        if ($key === 'installment_customer'
            && (in_array('installment_sale', $type['core'] ?? [], true) || in_array('installment_sale', $type['optional'] ?? [], true))) {
            return in_array('installment_sale', $type['core'] ?? [], true) ? 'core' : 'optional';
        }

        return 'remove';
    }

    /**
     * @param  array<int, string>  $productTypes
     */
    public function productTypesIncludeVariation(array $productTypes): bool
    {
        foreach ($productTypes as $type) {
            if (strcasecmp((string) $type, 'Variation') === 0) {
                return true;
            }
        }

        return false;
    }
}
