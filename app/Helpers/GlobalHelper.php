<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

if (!function_exists('requiredField')) {
    function requiredField()
    {
        return '<span class="text-danger">*</span>';
    }
}

if (!function_exists('insertSuccess')) {
    function insertSuccess($message)
    {
        return '<div class="d-flex align-items-center alert alert-success alert-dismissible" role="alert">
                    <i class="ti tabler-check me-2"></i>
                    '. $message .'
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
}

if (!function_exists('insertFailed')) {
    function insertFailed($message)
    {
        return '<div class="d-flex align-items-center  alert alert-danger alert-dismissible" role="alert">
                    <i class="ti tabler-circle-x me-2"></i>
                    '. $message .'
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
}

if (!function_exists('submitIconWithText')) {
    function submitIconWithText($parameter = '')
    {
        return '<i class="ti tabler-checkbox me-1"></i>' . ($parameter ? __('Update') : __('Submit'));
    }
}
if (!function_exists('editIconWithText')) {
    function editIconWithText()
    {
        return '<i class="ti tabler-edit me-1"></i>' . __('Edit');
    }
}

if (!function_exists('closeIconWithText')) {
    function closeIconWithText()
    {
        return '<i class="ti tabler-x me-1"></i>' . __('Close');
    }
}

if (!function_exists('backIconWithText')) {
    function backIconWithText()
    {
        return '<i class="ti tabler-arrow-back-up me-1"></i>' . __('Back');
    }
}
if (!function_exists('printIconWithText')) {
    function printIconWithText()
    {
        return '<i class="ti tabler-printer me-1"></i>' . __('Print');
    }
}

if (!function_exists('createDirectory')) {
    function createDirectory($path, $permissions = 0777, $recursive = true)
    {
        if (!file_exists($path)) {
            try {
                if (!mkdir($path, $permissions, $recursive)) {
                    throw new \Exception("Failed to create directory: " . $path);
                }
                return true;
            } catch (\Exception $e) {
                Log::error("Directory creation failed: " . $e->getMessage());
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('getWhiteLabel')) {
    function getWhiteLabel($param = '')
    {
        $company_id = session()->has('company.company_id') ? session('company.company_id') : 1;
        $company = \Modules\Configuration\Models\Company::select('white_label')->where('id', $company_id)->first();
        if ($company && $company->white_label) {
            $whiteLabel = json_decode($company->white_label, true);
            if ($param) {
                return $whiteLabel[$param] ?? null;
            }
            return $whiteLabel;
        }
        return null;
    }
}

/**
 * When LP=BD, returns the base64 data URI for the LP favicon/logo (same as old bip.js behaviour).
 * Otherwise returns $defaultUrl. Data URI avoids extra request and works like the legacy CodeIgniter script.
 */
if (!function_exists('lpFaviconLogoUrl')) {
    function lpFaviconLogoUrl($defaultUrl)
    {
        if (defined('LP') && LP === 'BD' && defined('LP_BD_FAVICON_LOGO_DATA_URI')) {
            return LP_BD_FAVICON_LOGO_DATA_URI;
        }
        return $defaultUrl;
    }
}

/**
 * Favicon type for <link rel="icon">: image/png when LP=BD (base64 PNG), else image/x-icon.
 */
if (!function_exists('lpFaviconLogoType')) {
    function lpFaviconLogoType()
    {
        return (defined('LP') && LP === 'BD') ? 'image/png' : 'image/x-icon';
    }
}

if (!function_exists('getPwaSettings')) {
    function getPwaSettings()
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('pwa_settings')) {
                return (object) [
                    'theme_color' => '#0f172a',
                    'app_name' => config('app.name', 'Retail POS'),
                ];
            }
            $companyId = session('company.company_id') ?? 1;
            $settings = \App\Models\PwaSetting::where('company_id', $companyId)->first();
            if ($settings) {
                return $settings;
            }
        } catch (\Throwable $e) {
            // Ignore if DB not ready
        }
        return (object) [
            'theme_color' => '#0f172a',
            'app_name' => config('app.name', 'Retail POS'),
        ];
    }
}

if (!function_exists('numberFormatWithCurrency')) {
    function numberFormatWithCurrency($amount) {
        $amount = is_numeric($amount) ? $amount : 0;
        $precision = session('company.precision', 2);
        $decimals_separator = session('company.decimals_separator', '.');
        $thousands_separator = session('company.thousands_separator', ',');
        $currency_position = session('company.currency_position', 'Before Amount');
        $currency = session('company.currency', '$');
        $formattedAmount = number_format((float)$amount, (int)$precision, $decimals_separator, $thousands_separator);
        $isBangladesh = false;
        $currencySpan = $isBangladesh ? '<span class="currency_show">' . $currency . '</span>' : $currency;

        return $currency_position === "Before Amount" 
            ? $currencySpan . $formattedAmount
            : $formattedAmount . $currencySpan;
    }
}
if (!function_exists('formatQty')) {
    function formatQty($qty)
    {
        $precision = session('company.precision', 2);
        $decimals_separator = session('company.decimals_separator', '.');
        $thousands_separator = session('company.thousands_separator', ',');

        if ($qty == floor($qty)) {
            return (string) intval($qty);
        }

        return rtrim(rtrim(number_format($qty, $precision, $decimals_separator, $thousands_separator), '0'), '.');
    }
}

if (!function_exists('formatQty')) {
    function formatQty($qty)
    {
        $precision = session('company.precision', 2);
        $decimal_separator = session('company.decimals_separator', '.');
        $thousands_separator = session('company.thousands_separator', ',');
        if ($qty == floor($qty)) {
            return (string) intval($qty);
        }
        $formatted = number_format($qty, $precision, $decimal_separator, $thousands_separator);
        if (strpos($formatted, $decimal_separator) !== false) {
            [$int, $dec] = explode($decimal_separator, $formatted);
            $dec = rtrim($dec, '0');
            return $dec === '' ? $int : $int . $decimal_separator . $dec;
        }
        return $formatted;
    }
}

if (!function_exists('numberFormatPrecision')) {
    function numberFormatPrecision($amount) {
        $amount = is_numeric($amount) ? $amount : 0;
        $precision = session('company.precision', 2);
        return number_format((float)$amount, (int)$precision);
    }
}

if (!function_exists('companyAmountSettings')) {
    /**
     * Currency formatting settings for the active company.
     * Falls back to ecommerce company / company id 1 when admin session is missing.
     *
     * @return array{currency:string,currency_position:string,precision:int,thousands_separator:string,decimals_separator:string}
     */
    function companyAmountSettings(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        $sessionCompany = Session::get('company');
        if (is_array($sessionCompany) && array_key_exists('currency', $sessionCompany)) {
            return $cached = [
                'currency' => (string) ($sessionCompany['currency'] ?? ''),
                'currency_position' => (string) ($sessionCompany['currency_position'] ?? 'Before Amount'),
                'precision' => max(0, (int) ($sessionCompany['precision'] ?? 2)),
                'thousands_separator' => (string) ($sessionCompany['thousands_separator'] ?? ','),
                'decimals_separator' => (string) ($sessionCompany['decimals_separator'] ?? '.'),
            ];
        }

        $companyId = 1;
        try {
            if (class_exists(\Modules\Ecommerce\Services\EcommerceContentService::class)) {
                $resolved = (int) app(\Modules\Ecommerce\Services\EcommerceContentService::class)->companyId();
                if ($resolved > 0) {
                    $companyId = $resolved;
                }
            }
        } catch (\Throwable) {
            $companyId = 1;
        }

        try {
            $company = \Modules\Configuration\Models\Company::query()
                ->where('id', $companyId)
                ->where('del_status', 'Live')
                ->first(['currency', 'currency_position', 'precision', 'thousands_separator', 'decimals_separator']);

            if (! $company) {
                $company = \Modules\Configuration\Models\Company::query()
                    ->where('del_status', 'Live')
                    ->orderBy('id')
                    ->first(['currency', 'currency_position', 'precision', 'thousands_separator', 'decimals_separator']);
            }

            if ($company) {
                return $cached = [
                    'currency' => (string) ($company->currency ?? ''),
                    'currency_position' => (string) ($company->currency_position ?? 'Before Amount'),
                    'precision' => max(0, (int) ($company->precision ?? 2)),
                    'thousands_separator' => (string) ($company->thousands_separator ?? ','),
                    'decimals_separator' => (string) ($company->decimals_separator ?? '.'),
                ];
            }
        } catch (\Throwable) {
            // Fall through to defaults.
        }

        return $cached = [
            'currency' => '$',
            'currency_position' => 'Before Amount',
            'precision' => 2,
            'thousands_separator' => ',',
            'decimals_separator' => '.',
        ];
    }
}

if (!function_exists('formatAmount')) {
    function formatAmount($amount) {
        $company = companyAmountSettings();

        $precision = max(0, (int) ($company['precision'] ?? 2));
        $currency = (string) ($company['currency'] ?? '');
        $currencyPosition = $company['currency_position'] ?? 'Before Amount';
        $thousandSeparator = (($company['thousands_separator'] ?? null) === 'space')
            ? ' '
            : (string) ($company['thousands_separator'] ?? ',');
        $decimalSeparator = (($company['decimals_separator'] ?? null) === 'space')
            ? ' '
            : (string) ($company['decimals_separator'] ?? '.');

        $formatted = number_format(
            (float) $amount,
            $precision,
            $decimalSeparator,
            $thousandSeparator
        );

        if ($currencyPosition === 'Before Amount') {
            return $currency . $formatted;
        }

        return $formatted . $currency;
    }
}
if (!function_exists('formatDate')) {
    function formatDate($date)
    {
        if ($date === null || $date === '') {
            return '';
        }

        $company = Session::get('company');
        if (!$company) {
            return $date;
        }
        $dateFormat = (string) ($company['date_format'] ?? 'Y/m/d');
        if ($dateFormat === '') {
            $dateFormat = 'Y/m/d';
        }
        try {
            $carbonDate = \Carbon\Carbon::parse($date);
        } catch (\Exception $e) {
            return $date;
        }
        return $carbonDate->format($dateFormat);
    }
}
if (!function_exists('formatDateTime')) {
    function formatDateTime($date)
    {
        if (empty($date)) {
            return $date;
        }
        $company = Session::get('company');
        if (!$company) {
            return $date;
        }
        $dateTimeFormat = $company['date_time_format']
            ?? (($company['date_format'] ?? 'Y-m-d') . ' H:i:s');
        try {
            return \Carbon\Carbon::parse($date)->format($dateTimeFormat);
        } catch (\Exception $e) {
            return $date;
        }
    }
}

if (!function_exists('itemCodeGenerator')) {
    function itemCodeGenerator() {
        $item = \Modules\Stock\Models\Item::select('code')
                ->where('company_id', session('company.company_id'))
                ->where('type', '!=', '0')
                ->where('del_status', 'Live')
                ->orderBy('id', 'desc')
                ->first();
        if (!$item) {
            $start_from = session('company.product_code_start_from') ?? '000001';
            return $start_from;
        }
        $last_code = $item->code;
        $next_number = intval($last_code) + 1;
        $code_length = strlen($last_code);
        $next_code = str_pad($next_number, $code_length, '0', STR_PAD_LEFT);
        return $next_code;
    }
}

if (!function_exists('lp_is_bd')) {
    /**
     * Biponi (Bangladesh) package build when LP=BD.
     */
    function lp_is_bd(): bool
    {
        if (defined('LP') && strtoupper((string) LP) === 'BD') {
            return true;
        }

        return strtoupper((string) env('LP', '')) === 'BD';
    }
}

if (!function_exists('available_ui_locales')) {
    /**
     * Locales shown in the language switcher.
     * Biponi (LP=BD) only supports English and Bangla.
     *
     * @return array<int, string>
     */
    function available_ui_locales(): array
    {
        $locales = config('app.available_locales', ['en']);

        if (lp_is_bd()) {
            return array_values(array_intersect($locales, ['en', 'bn']));
        }

        return array_values($locales);
    }
}

if (!function_exists('isEnableGST')) {
    function isEnableGST() {
        if (lp_is_bd()) {
            return false;
        }

        if (! module_enabled('gst')) {
            return false;
        }

        if (session('company.tax_is_gst') == 'Yes') {
            return true;
        }
        return false;
    }
}

if (!function_exists('module_enabled')) {
    /**
     * Check whether a unique business module/feature is enabled.
     */
    function module_enabled(string $key): bool
    {
        if (lp_is_bd() && in_array($key, [
            'zatca',
            'gst',
            'multiple_currency',
            'denomination',
            'booking',
            'fixed_assets',
        ], true)) {
            return false;
        }

        return app(\App\Services\ModuleUsageService::class)->isEnabled($key);
    }
}

if (!function_exists('product_type_enabled')) {
    /**
     * Check whether a product type is allowed for the selected business type.
     * Empty product_types list means all types are allowed (backward compatible).
     */
    function product_type_enabled(string $type): bool
    {
        $types = session('module_product_types');
        if (! is_array($types)) {
            $types = app(\App\Services\ModuleUsageService::class)->productTypes();
        }

        if ($types === []) {
            return true;
        }

        foreach ($types as $allowed) {
            if (strcasecmp((string) $allowed, $type) === 0) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('isValidGstin')) {
    /**
     * Validate Indian GSTIN (15 chars, format + Luhn mod 36 checksum).
     *
     * @param string|null $gstin
     * @return bool
     */
    function isValidGstin($gstin) {
        return $gstin !== null && $gstin !== '' && \App\Helpers\GstinValidator::isValid($gstin);
    }
}

if (!function_exists('sessionAll')) {
    function sessionAll() {
        return session()->all();
    }
}

if (!function_exists('truncateText')) {
    function truncateText($text, $charLimit = 50, $wordLimit = 20)
    {
        $text = trim($text);
        $words = explode(' ', $text);
        if (count($words) > $wordLimit) {
            $text = implode(' ', array_slice($words, 0, $wordLimit)) . '...';
        }
        if (strlen($text) > $charLimit) {
            $text = substr($text, 0, $charLimit) . '...';
        }
        return $text;
    }
}

if (!function_exists('getParentItemName')) {
    function getParentItemName($id)
    {
        $parentItem = DB::table('items')->where('id', $id)->first();
        if ($parentItem) {
            return $parentItem->name;
        }
        return;
    }
}

if (!function_exists('invoice_bilingual_label')) {
    /**
     * Invoice label with optional Arabic line (HTML; use {!! !!} in Blade).
     */
    function invoice_bilingual_label(?string $english, ?string $arabic = ''): string
    {
        return \Modules\Sale\Support\InvoiceBilingualLabel::inline($english, $arabic);
    }
}