<?php

namespace Modules\Sale\Services;

use Modules\Configuration\Models\Company;
use Modules\Sale\Models\Sale;

class SaleNumberService
{
    /**
     * Generate unique sale number (scoped per company, driven by invoice_configuration when set).
     */
    public function generate(int $companyId): string
    {
        $company = Company::query()->find($companyId);
        $config = $this->parseInvoiceConfiguration($company);

        if (! $this->isInvoiceNumberingReady($config)) {
            return $this->generateLegacyYearlySaleNo($companyId);
        }

        $liveSalesCount = Sale::where('company_id', $companyId)->where('del_status', 'Live')->count();

        if ($liveSalesCount === 0) {
            return $this->generateFirstSaleNoFromInvoiceConfig($companyId, $config);
        }

        return $this->generateNextSaleNoFromExistingSales($companyId, $config);
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseInvoiceConfiguration(?Company $company): array
    {
        if (! $company || empty($company->invoice_configuration)) {
            return [];
        }

        $decoded = json_decode($company->invoice_configuration, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function isInvoiceNumberingReady(array $config): bool
    {
        if ($config === []) {
            return false;
        }

        $type = $config['inv_numbering_type'] ?? '';
        $digits = (int) ($config['inv_number_of_digit'] ?? 0);

        return in_array($type, ['Sequential', 'Random'], true)
            && $digits >= 4
            && $digits <= 10;
    }

    protected function generateLegacyYearlySaleNo(int $companyId): string
    {
        $prefix = 'SALE-' . date('Y') . '-';
        $lastSale = Sale::where('sale_no', 'like', $prefix . '%')
            ->where('company_id', $companyId)
            ->orderBy('sale_no', 'desc')
            ->first();

        if ($lastSale) {
            $lastNumber = (int) substr($lastSale->sale_no, strlen($prefix));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad((string) $newNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function generateFirstSaleNoFromInvoiceConfig(int $companyId, array $config): string
    {
        $type = $config['inv_numbering_type'] ?? 'Sequential';
        $digits = (int) ($config['inv_number_of_digit'] ?? 4);

        if ($type === 'Random') {
            return $this->generateRandomUniqueSaleNo($companyId, $config);
        }

        $startRaw = (string) ($config['inv_start_from'] ?? '1');
        $padded = $this->padInvoiceNumericPart($startRaw, $digits);

        return $this->composeInvoiceSaleNo($config, $padded);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function generateNextSaleNoFromExistingSales(int $companyId, array $config): string
    {
        $type = $config['inv_numbering_type'] ?? 'Sequential';

        if ($type === 'Random') {
            return $this->generateRandomUniqueSaleNo($companyId, $config);
        }

        $lastSale = $this->getLastSaleForInvoiceNumbering($companyId, $config);
        if (! $lastSale) {
            return $this->generateFirstSaleNoFromInvoiceConfig($companyId, $config);
        }

        return $this->incrementSequentialSaleNo($lastSale->sale_no, $config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function getLastSaleForInvoiceNumbering(int $companyId, array $config): ?Sale
    {
        $prefix = (string) ($config['inv_prefix'] ?? '');
        $query = Sale::where('company_id', $companyId)
            ->where('del_status', 'Live')
            // Ignore legacy ecommerce ONL-* numbers so POS + online share one sequence.
            ->where('sale_no', 'not like', 'ONL-%');

        if ($prefix !== '') {
            $query->where('sale_no', 'like', $prefix . '%');
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function incrementSequentialSaleNo(string $lastSaleNo, array $config): string
    {
        $prefix = (string) ($config['inv_prefix'] ?? '');
        $digits = (int) ($config['inv_number_of_digit'] ?? 4);
        $schemaType = (string) ($config['schema_type'] ?? 'XXXX');

        $remainder = $lastSaleNo;
        if ($prefix !== '' && str_starts_with($lastSaleNo, $prefix)) {
            $remainder = substr($lastSaleNo, strlen($prefix));
        }

        if (preg_match('/^(\d{4})-(\d+)$/', $remainder, $m)) {
            $yearPart = $m[1];
            $next = (int) $m[2] + 1;
            $padded = str_pad((string) $next, $digits, '0', STR_PAD_LEFT);
            $middle = $yearPart . '-' . $padded;
        } else {
            $next = (int) $remainder + 1;
            $padded = str_pad((string) $next, $digits, '0', STR_PAD_LEFT);
            $middle = $this->applySchemaPlaceholder($schemaType, $padded);
        }

        return $prefix . $middle;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function composeInvoiceSaleNo(array $config, string $paddedNumericPart): string
    {
        $prefix = (string) ($config['inv_prefix'] ?? '');
        $schemaType = (string) ($config['schema_type'] ?? 'XXXX');

        return $prefix . $this->applySchemaPlaceholder($schemaType, $paddedNumericPart);
    }

    protected function applySchemaPlaceholder(string $schemaType, string $paddedNumericPart): string
    {
        if (str_contains($schemaType, 'XXXX')) {
            return str_replace('XXXX', $paddedNumericPart, $schemaType);
        }

        return $paddedNumericPart;
    }

    protected function padInvoiceNumericPart(string $startRaw, int $digits): string
    {
        $digitsOnly = preg_replace('/\D/', '', $startRaw);
        if ($digitsOnly === '') {
            $digitsOnly = '1';
        }

        return str_pad($digitsOnly, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function generateRandomUniqueSaleNo(int $companyId, array $config): string
    {
        $digits = (int) ($config['inv_number_of_digit'] ?? 4);

        for ($attempt = 0; $attempt < 150; $attempt++) {
            $n = $this->randomIntInDigitRange($digits);
            $padded = str_pad((string) $n, $digits, '0', STR_PAD_LEFT);
            $candidate = $this->composeInvoiceSaleNo($config, $padded);

            if (! Sale::where('company_id', $companyId)->where('sale_no', $candidate)->exists()) {
                return $candidate;
            }
        }

        $padded = str_pad((string) random_int(0, (int) pow(10, $digits) - 1), $digits, '0', STR_PAD_LEFT);

        return $this->composeInvoiceSaleNo($config, $padded);
    }

    protected function randomIntInDigitRange(int $digits): int
    {
        $digits = max(1, $digits);
        $min = (int) pow(10, $digits - 1);
        $max = (int) pow(10, $digits) - 1;

        return random_int($min, $max);
    }
}
