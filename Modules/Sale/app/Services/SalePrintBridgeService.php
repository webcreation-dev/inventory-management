<?php

namespace Modules\Sale\Services;

use Modules\PrintManager\DTO\PrintJobResult;
use Modules\PrintManager\Support\PrintManager;
use Modules\Sale\Models\Sale;
use Modules\Sale\Repositories\SaleRepository;
use Modules\Sale\Support\InvoiceAmountWords;
use Modules\Sale\Support\InvoiceItemDisplay;

/**
 * Thin bridge: Sale data -> PrintManager receipt print job.
 * Receipt structure mirrors 56mm-invoice (text/ESC-POS only; no logo/images).
 */
class SalePrintBridgeService
{
    public function __construct(
        protected SaleRepository $saleRepository,
    ) {
    }

    public function prepareSaleReceiptPrint(string $saleIdInput): ?PrintJobResult
    {
        $sale = $this->resolveSale($saleIdInput);
        if (!$sale) {
            return null;
        }

        $receipt = $this->buildReceiptArray($sale);

        return PrintManager::printReceipt($receipt, [
            'module' => 'Sale',
            'branch_id' => $sale->outlet_id,
            'open_cash_drawer' => true,
            // Do not fall back to browser popup for POS receipts.
            'allow_fallback' => false,
        ]);
    }

    protected function buildReceiptArray(Sale $sale): array
    {
        $sale->load([
            'saleDetails' => fn ($q) => $q->where('del_status', 'Live'),
            'saleDetails.item.parent',
            'saleDetails.item.brand',
            'saleDetails.comboSales' => fn ($q) => $q->where('del_status', 'Live'),
            'saleDetails.comboSales.comboItem.parent',
            'saleDetails.comboSales.comboItem.brand',
            'customer.state',
            'employee',
            'outlet.state',
            'salePayments.paymentMethod',
        ]);

        $invConfig = $this->invoiceConfig();
        $charsPerLine = $this->resolveCharactersPerLine();

        $headerLines = [];
        $items = [];
        $totals = [];
        $footerParts = [];

        // --- Header (no logo) ---
        if (($invConfig['show_business_name'] ?? 'Yes') === 'Yes') {
            $businessName = trim((string) (session('company.business_name') ?: config('app.name')));
            $businessNameAr = $this->plainLabel($invConfig['business_name_arabic'] ?? '');
            if ($businessName !== '') {
                $headerLines[] = $businessName;
            }
            if ($businessNameAr !== '' && $businessNameAr !== $businessName) {
                $headerLines[] = $businessNameAr;
            }
        }

        if ($sale->outlet) {
            $headerLines[] = __('Outlet') . ': ' . ($sale->outlet->outlet_name ?? '');
            if (!empty($sale->outlet->phone)) {
                $headerLines[] = __('Phone') . ': ' . $sale->outlet->phone;
            }
            if (!empty($sale->outlet->email)) {
                $headerLines[] = __('Email') . ': ' . $sale->outlet->email;
            }
            if (!empty($sale->outlet->address)) {
                $headerLines[] = __('Address') . ': ' . $sale->outlet->address;
            }

            if ($this->shouldShowGst($invConfig) && ($invConfig['show_business_tax_number'] ?? 'Yes') === 'Yes') {
                if ($sale->outlet->state && ($sale->outlet->state->state_name || $sale->outlet->state->state_code)) {
                    $headerLines[] = __('Outlet State') . ': '
                        . ($sale->outlet->state->state_name ?? '')
                        . ' (' . ($sale->outlet->state->state_code ?? '') . ')';
                }
                if (!empty($sale->outlet->gstin)) {
                    $headerLines[] = __('Outlet GSTIN') . ': ' . $sale->outlet->gstin;
                }
            }
        }

        $headerLines[] = '';

        // --- Customer ---
        $headerLines[] = $this->bilingualLabel(
            $invConfig['customer_label'] ?? __('Customer'),
            $invConfig['customer_label_arabic'] ?? ''
        );
        if ($sale->customer) {
            if (!empty($sale->customer->name)) {
                $headerLines[] = __('Name') . ': ' . $sale->customer->name;
            }
            if (($invConfig['show_customer_phone_number'] ?? 'Yes') === 'Yes' && !empty($sale->customer->phone)) {
                $headerLines[] = __('Phone') . ': ' . $sale->customer->phone;
            }
            if (($invConfig['show_customer_email'] ?? 'No') === 'Yes' && !empty($sale->customer->email)) {
                $headerLines[] = __('Email') . ': ' . $sale->customer->email;
            }
            if (($invConfig['show_customer_address'] ?? 'Yes') === 'Yes' && !empty($sale->customer->address)) {
                $headerLines[] = __('Address') . ': ' . $sale->customer->address;
            }
            if ($this->shouldShowGst($invConfig)) {
                if (!empty($sale->customer->gst_number)) {
                    $headerLines[] = __('GSTIN') . ': ' . $sale->customer->gst_number;
                }
                if ($sale->customer->state && ($sale->customer->state->state_name || $sale->customer->state->state_code)) {
                    $headerLines[] = __('Customer State') . ': '
                        . ($sale->customer->state->state_name ?? '')
                        . ' (' . ($sale->customer->state->state_code ?? '') . ')';
                }
            }
        } else {
            $headerLines[] = __('Name') . ': ' . __('Walk-in Customer');
        }

        $headerLines[] = '';

        // --- Sale info ---
        $headerLines[] = $this->bilingualLabel(
            $invConfig['invoice_heading'] ?? __('Sale'),
            $invConfig['invoice_heading_arabic'] ?? ''
        );
        if (!empty($sale->sale_no)) {
            $saleNoLine = $this->bilingualLabel(
                $invConfig['invoice_no_label'] ?? __('Sale No'),
                $invConfig['invoice_no_label_arabic'] ?? ''
            ) . ': ' . $sale->sale_no;
            if (!empty($sale->random_code)) {
                $saleNoLine .= ' | ' . __('Offline Invoice No') . ': ' . $sale->random_code;
            }
            $headerLines[] = $saleNoLine;
        }
        if (!empty($sale->sale_date)) {
            $headerLines[] = __('Sale Date') . ': ' . (function_exists('formatDate') ? formatDate($sale->sale_date) : (string) $sale->sale_date);
        }
        if (!empty($sale->date_time)) {
            $headerLines[] = $this->bilingualLabel(
                $invConfig['invoice_date_label'] ?? __('Invoice Date'),
                $invConfig['invoice_date_label_arabic'] ?? ''
            ) . ': ' . (function_exists('formatDateTime') ? formatDateTime($sale->date_time) : (string) $sale->date_time);
        }
        if ($sale->employee) {
            $headerLines[] = $this->bilingualLabel(
                $invConfig['sales_person_label'] ?? __('Sales By'),
                $invConfig['sales_person_label_arabic'] ?? ''
            ) . ': ' . $sale->employee->name;
        }
        $statusLabel = ((float) ($sale->due_amount ?? 0) > 0)
            ? ($invConfig['invoice_heading_due'] ?? __('Due'))
            : ($invConfig['invoice_heading_paid'] ?? __('Paid'));
        $headerLines[] = __('Status') . ': ' . $this->plainLabel($statusLabel);

        // --- Items (grouped like 56mm invoice) ---
        $showHsn = $this->shouldShowGst($invConfig) && ($invConfig['show_hsn_code'] ?? 'No') === 'Yes';
        $showImei = ($invConfig['show_product_imei_serial_number'] ?? 'No') === 'Yes';
        $defaultHsn = $invConfig['default_hsn_code'] ?? '0000';
        $index = 0;

        foreach ($this->groupedInvoiceRows($sale) as $group) {
            $index++;
            $detail = $group['first'];
            $details = $group['details'];

            $qty = 0.0;
            $lineTotal = 0.0;
            $lineDiscountTotal = 0.0;
            foreach ($details as $d) {
                $qty += (float) ($d->qty ?? 0);
                $lineTotal += (float) ($d->qty ?? 0) * (float) ($d->menu_price_with_discount ?? 0);
                $lineDiscountTotal += (float) ($d->discount_amount ?? 0);
            }

            $alternativeName = '';
            if ($detail->item) {
                // Always include items.alternative_name on direct print when present.
                $alternativeName = InvoiceItemDisplay::itemAlternativeNameLine($detail->item);
            }

            $itemName = $detail->item
                ? InvoiceItemDisplay::lineItemNameWithAlternative($detail->item, $invConfig, true)
                : (string) ($detail->custom_item_name ?? __('Item'));

            $metaLines = [];

            if ($showHsn) {
                $hsn = ($detail->item && $detail->item->hsn_code) ? $detail->item->hsn_code : $defaultHsn;
                $metaLines[] = __('HSN') . ': ' . $hsn;
            }

            if ($showImei) {
                $imeiLine = $this->imeiExpiryLine($group);
                if ($imeiLine !== '') {
                    $metaLines[] = $imeiLine;
                }
            }

            $wgLine = $this->warrantyGuaranteeLine($detail, $invConfig);
            if ($wgLine !== '') {
                $metaLines[] = $wgLine;
            }

            if ($lineDiscountTotal > 0.0001) {
                $metaLines[] = __('Discount') . ': ' . $this->formatMoney($lineDiscountTotal)
                    . $this->itemDiscountSuffix($detail);
            }

            $unitPrice = (float) ($detail->menu_unit_price ?? 0);

            $items[] = [
                'name' => $index . '. ' . $itemName,
                'alternative_name' => $alternativeName,
                'qty' => $qty,
                'price' => $unitPrice,
                'total' => $lineTotal,
                'meta' => ['lines' => $metaLines],
            ];

            // Combo component rows (plain text only)
            foreach ($details as $saleDetail) {
                if (!$saleDetail->relationLoaded('comboSales') || $saleDetail->comboSales->isEmpty()) {
                    continue;
                }
                foreach ($saleDetail->comboSales as $comboRow) {
                    if (($comboRow->del_status ?? '') !== 'Live' || ($comboRow->show_in_invoice ?? 'No') !== 'Yes') {
                        continue;
                    }
                    $cItem = $comboRow->comboItem;
                    $parentQty = (float) ($saleDetail->qty ?? 0);
                    $lineQty = (float) ($comboRow->combo_item_qty ?? 0) * $parentQty;
                    $cUnit = (float) ($comboRow->combo_item_price ?? 0);
                    $cTotal = $lineQty * $cUnit;
                    $comboAlt = $cItem ? InvoiceItemDisplay::itemAlternativeNameLine($cItem) : '';
                    $cName = $cItem
                        ? ('└ ' . InvoiceItemDisplay::lineItemNameWithAlternative($cItem, $invConfig, true))
                        : ('└ ' . __('Combo item') . ' #' . ($comboRow->combo_item_id ?? ''));

                    $items[] = [
                        'name' => $cName,
                        'alternative_name' => $comboAlt,
                        'qty' => $lineQty,
                        'price' => $cUnit,
                        'total' => $cTotal,
                        'meta' => ['lines' => []],
                    ];
                }
            }
        }

        // --- Summary ---
        $totals[] = [
            'label' => $this->bilingualLabel(
                $invConfig['subtotal_label'] ?? __('Subtotal'),
                $invConfig['subtotal_label_arabic'] ?? ''
            ),
            'amount' => (float) ($sale->sub_total ?? 0),
            'emphasis' => false,
        ];

        $orderDisc = (float) ($sale->sub_total_discount_amount ?? 0);
        if ($orderDisc > 0.0001) {
            $totals[] = [
                'label' => $this->bilingualLabel(
                    $invConfig['discount_label'] ?? __('Order Discount'),
                    $invConfig['discount_label_arabic'] ?? ''
                ) . $this->orderDiscountSuffix($sale),
                'amount' => $orderDisc,
                'emphasis' => false,
            ];
        } elseif ((float) ($sale->total_discount_amount ?? 0) > 0
            && (float) ($sale->sub_total_discount_amount ?? 0) <= 0
            && (float) ($sale->total_item_discount_amount ?? 0) <= 0) {
            $totals[] = [
                'label' => $this->bilingualLabel(
                    $invConfig['discount_label'] ?? __('Discount'),
                    $invConfig['discount_label_arabic'] ?? ''
                ),
                'amount' => (float) $sale->total_discount_amount,
                'emphasis' => false,
            ];
        }

        foreach ($this->summaryTaxes($sale) as $taxName => $taxData) {
            if (($taxData['amount'] ?? 0) <= 0) {
                continue;
            }
            $label = $taxName;
            if (($taxData['rate'] ?? 0) > 0) {
                $label .= ' (' . $taxData['rate'] . '%)';
            }
            $totals[] = [
                'label' => $label,
                'amount' => (float) $taxData['amount'],
                'emphasis' => false,
            ];
        }

        if ((float) ($sale->delivery_charge ?? 0) > 0) {
            $totals[] = [
                'label' => __('Delivery Charge'),
                'amount' => (float) $sale->delivery_charge,
                'emphasis' => false,
            ];
        }

        $totals[] = [
            'label' => $this->bilingualLabel(
                $invConfig['total_payable_label'] ?? __('Grand Total'),
                $invConfig['total_payable_label_arabic'] ?? ''
            ),
            'amount' => (float) ($sale->total_payable ?? $sale->grand_total ?? 0),
            'emphasis' => true,
        ];

        // Amount in words (plain text — supported)
        $words = InvoiceAmountWords::grandTotalPhrase(
            (float) ($sale->total_payable ?? $sale->grand_total ?? 0),
            $invConfig
        );
        if ($words) {
            $footerParts[] = __('Amount in words') . ': ' . $words;
        }

        if ((float) ($sale->previous_due ?? 0) > 0) {
            $totals[] = [
                'label' => $this->bilingualLabel(
                    $invConfig['previous_balance_label'] ?? __('Previous Due Amount'),
                    $invConfig['previous_balance_label_arabic'] ?? ''
                ),
                'amount' => (float) $sale->previous_due,
                'emphasis' => false,
            ];
        }

        $totals[] = [
            'label' => $this->bilingualLabel(
                $invConfig['paid_amount_label'] ?? __('Paid Amount'),
                $invConfig['paid_amount_label_arabic'] ?? ''
            ),
            'amount' => (float) ($sale->paid_amount ?? 0),
            'emphasis' => false,
        ];

        $givenAmount = (float) ($sale->given_amount ?? 0);
        $paidAmount = (float) ($sale->paid_amount ?? 0);
        $changeAmount = (float) ($sale->change_amount ?? 0);
        if ($changeAmount > 0.001 && $givenAmount > $paidAmount + 0.001) {
            $totals[] = [
                'label' => $this->bilingualLabel(
                    $invConfig['given_amount_label'] ?? __('Given Amount'),
                    $invConfig['given_amount_label_arabic'] ?? ''
                ),
                'amount' => $givenAmount,
                'emphasis' => false,
            ];
            $totals[] = [
                'label' => $this->bilingualLabel(
                    $invConfig['change_amount_label'] ?? __('Change Amount'),
                    $invConfig['change_amount_label_arabic'] ?? ''
                ),
                'amount' => $changeAmount,
                'emphasis' => false,
            ];
        }

        if ((float) ($sale->due_amount ?? 0) > 0) {
            $totals[] = [
                'label' => $this->bilingualLabel(
                    $invConfig['due_amount_label'] ?? __('Due Amount'),
                    $invConfig['due_amount_label_arabic'] ?? ''
                ),
                'amount' => (float) $sale->due_amount,
                'emphasis' => false,
            ];
        }

        if (($invConfig['show_payment_method'] ?? 'No') === 'Yes' && $sale->salePayments && $sale->salePayments->count() > 0) {
            foreach ($sale->salePayments as $payment) {
                $payName = $payment->paymentMethod->name ?? __('Cash');
                $payBase = method_exists($payment, 'getBaseCurrencyAmount')
                    ? $payment->getBaseCurrencyAmount()
                    : (float) ($payment->amount ?? 0);
                $label = $payName;
                if (($payment->multi_currency ?? 'No') === 'Yes'
                    && (float) ($payment->multi_currency_rate ?? 0) > 0
                    && ($payment->currency_type ?? '') !== '') {
                    $foreign = rtrim(rtrim(number_format((float) $payment->amount, 3, '.', ''), '0'), '.') ?: '0';
                    $label .= ' (' . $foreign . ' ' . $payment->currency_type . ')';
                }
                $totals[] = [
                    'label' => $label,
                    'amount' => (float) $payBase,
                    'emphasis' => false,
                ];
            }
        }

        // Multi-currency notes (text only)
        foreach ($sale->salePayments ?? [] as $payment) {
            if (($payment->multi_currency ?? 'No') !== 'Yes'
                || trim((string) ($payment->currency_type ?? '')) === ''
                || (float) ($payment->multi_currency_rate ?? 0) <= 0) {
                continue;
            }
            $baseCurrency = trim((string) (session('company.currency') ?? '')) ?: __('Base');
            $foreignAmt = rtrim(rtrim(number_format((float) $payment->amount, 3, '.', ''), '0'), '.') ?: '0';
            $rateFmt = rtrim(rtrim(number_format((float) $payment->multi_currency_rate, 6, '.', ''), '0'), '.') ?: '0';
            $footerParts[] = __('Invoice multi-currency note', [
                'paid_currency' => $payment->currency_type,
                'paid_amount' => $foreignAmt,
                'base_currency' => $baseCurrency,
                'rate' => $rateFmt,
            ]);
        }

        // Terms / footer — strip HTML (unsupported in ESC/POS)
        $terms = $this->plainText(session('company.term_conditions'));
        if ($terms !== '') {
            $footerParts[] = $terms;
        }
        $invoiceFooter = $this->plainText(session('company.invoice_footer'));
        if ($invoiceFooter !== '') {
            $footerParts[] = $invoiceFooter;
        }

        $title = '';
        if (($invConfig['show_business_name'] ?? 'Yes') === 'Yes') {
            $title = (string) (session('company.business_name') ?: config('app.name'));
            $titleAr = $this->plainLabel($invConfig['business_name_arabic'] ?? '');
            // Prefer English title for header block; Arabic already in header_lines.
            if ($title === '' && $titleAr !== '') {
                $title = $titleAr;
            }
        }

        return [
            'title' => $title,
            'header_lines' => array_values(array_filter($headerLines, static fn ($line) => $line !== null)),
            'items' => $items,
            'totals' => $totals,
            'footer' => implode("\n", array_filter($footerParts)),
            'qr_code' => $this->resolveQrPayload($sale, $invConfig, $title),
            'characters_per_line' => $charsPerLine,
        ];
    }

    /**
     * @return array{details: array<int, mixed>, first: mixed}[]
     */
    protected function groupedInvoiceRows(Sale $sale): array
    {
        if (!$sale->saleDetails || $sale->saleDetails->count() === 0) {
            return [];
        }

        $keyOrder = [];
        $buckets = [];

        foreach ($sale->saleDetails as $detail) {
            $itemId = (int) ($detail->item_id ?? 0);
            $mu = (string) ($detail->menu_unit_price ?? '0');
            $mwd = (string) ($detail->menu_price_with_discount ?? '0');
            $itemType = $detail->item ? $detail->item->type : null;

            if ($itemType === 'Medicine_Product') {
                $key = $itemId . '|' . $mu . '|' . $mwd . '|med|' . md5((string) ($detail->expiry_imei_serial ?? ''));
            } else {
                $key = $itemId . '|' . $mu . '|' . $mwd;
            }

            if (!isset($buckets[$key])) {
                $buckets[$key] = [];
                $keyOrder[] = $key;
            }
            $buckets[$key][] = $detail;
        }

        $rows = [];
        foreach ($keyOrder as $key) {
            $rows[] = [
                'details' => $buckets[$key],
                'first' => $buckets[$key][0],
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, array{amount: float, rate: float}>
     */
    protected function summaryTaxes(Sale $sale): array
    {
        $vatObjects = $sale->sale_vat_objects ? json_decode($sale->sale_vat_objects, true) : [];
        $vatObjects = is_array($vatObjects) ? $vatObjects : [];
        $summaryTaxes = [];

        foreach ($vatObjects as $v) {
            $taxName = $v['tax_field_type'] ?? $v['tax_field_name'] ?? '';
            if (!$taxName) {
                continue;
            }
            $amt = (float) ($v['tax_field_amount'] ?? 0);
            $pct = (float) ($v['tax_field_percentage'] ?? 0);
            if (!isset($summaryTaxes[$taxName])) {
                $summaryTaxes[$taxName] = ['amount' => 0.0, 'rate' => $pct];
            }
            $summaryTaxes[$taxName]['amount'] += $amt;
            if ($pct > 0 && $summaryTaxes[$taxName]['rate'] == 0) {
                $summaryTaxes[$taxName]['rate'] = $pct;
            }
        }

        return $summaryTaxes;
    }

    /**
     * @param  array{details: array<int, mixed>, first: mixed}  $group
     */
    protected function imeiExpiryLine(array $group): string
    {
        $parts = [];
        foreach ($group['details'] as $d) {
            $s = trim((string) ($d->expiry_imei_serial ?? ''));
            if ($s !== '') {
                $parts[] = $s;
            }
        }
        if ($parts === []) {
            return '';
        }

        $combined = implode(', ', $parts);
        $type = $group['first']->item->type ?? null;

        return match ($type) {
            'IMEI_Product' => __('IMEI Number') . ': ' . $combined,
            'Serial_Product' => __('Serial Number') . ': ' . $combined,
            'Medicine_Product' => __('Expiry Date') . ': ' . $combined,
            default => __('IMEI/Serial') . ': ' . $combined,
        };
    }

    protected function warrantyGuaranteeLine($detail, array $invConfig): string
    {
        $item = $detail->item ?? null;
        if (!$item) {
            return '';
        }

        $w = $item->warranty;
        $wd = $item->warranty_date;
        $g = $item->guarantee;
        $gd = $item->guarantee_date;
        $parent = ((int) ($item->parent_id ?? 0) > 0) ? ($item->parent ?? null) : null;

        if ($parent) {
            if (($w === null || trim((string) $w) === '') && $parent->warranty !== null && trim((string) $parent->warranty) !== '') {
                $w = $parent->warranty;
                $wd = $parent->warranty_date;
            }
            if (($g === null || trim((string) $g) === '') && $parent->guarantee !== null && trim((string) $parent->guarantee) !== '') {
                $g = $parent->guarantee;
                $gd = $parent->guarantee_date;
            }
        }

        $unitLabels = ['day' => __('Day'), 'month' => __('Month'), 'year' => __('Year')];
        $parts = [];

        if (($invConfig['show_warranty_period'] ?? 'Yes') === 'Yes' && $w !== null && trim((string) $w) !== '') {
            $suffix = isset($unitLabels[$wd]) ? ' ' . $unitLabels[$wd] : ($wd ? ' ' . $wd : '');
            $parts[] = __('Warranty') . ': ' . trim((string) $w . $suffix);
        }
        if (($invConfig['show_guarantee_period'] ?? 'Yes') === 'Yes' && $g !== null && trim((string) $g) !== '') {
            $suffix = isset($unitLabels[$gd]) ? ' ' . $unitLabels[$gd] : ($gd ? ' ' . $gd : '');
            $parts[] = __('Guarantee') . ': ' . trim((string) $g . $suffix);
        }

        return implode(' | ', $parts);
    }

    protected function itemDiscountSuffix($detail): string
    {
        $discountType = strtolower((string) ($detail->discount_type ?? 'fixed'));
        if (in_array($discountType, ['flat', 'amount'], true)) {
            $discountType = 'fixed';
        }
        $pctVal = (float) ($detail->menu_discount_value ?? 0);
        if ($discountType === 'percentage' && $pctVal > 0) {
            $pctStr = (fmod($pctVal, 1.0) < 0.00001)
                ? (string) (int) round($pctVal)
                : rtrim(rtrim(number_format($pctVal, 3, '.', ''), '0'), '.');

            return ' ' . __('of') . ' ' . $pctStr . '%';
        }

        return '';
    }

    protected function orderDiscountSuffix(Sale $sale): string
    {
        $orderDiscType = strtolower((string) ($sale->sub_total_discount_type ?? 'fixed'));
        if (in_array($orderDiscType, ['flat', 'amount'], true)) {
            $orderDiscType = 'fixed';
        }
        $orderDiscVal = (float) ($sale->sub_total_discount_value ?? 0);
        if ($orderDiscType === 'percentage' && $orderDiscVal > 0) {
            $pctStr = (fmod($orderDiscVal, 1.0) < 0.00001)
                ? (string) (int) round($orderDiscVal)
                : rtrim(rtrim(number_format($orderDiscVal, 3, '.', ''), '0'), '.');

            return ' (' . $pctStr . '%)';
        }

        return '';
    }

    protected function resolveQrPayload(Sale $sale, array $invConfig, string $businessName): ?string
    {
        $qrCodeOption = $invConfig['qr_code_option'] ?? 'ZATCA QR Code';

        if ($qrCodeOption === 'ZATCA QR Code' && !empty($sale->zatca_phase1_qr_code)) {
            $decoded = base64_decode((string) $sale->zatca_phase1_qr_code, true);

            return $decoded !== false ? $decoded : null;
        }

        if ($qrCodeOption === 'Regular QR Code') {
            $regularQrDate = '';
            if (!empty($sale->date_time)) {
                $regularQrDate = function_exists('formatDateTime') ? formatDateTime($sale->date_time) : (string) $sale->date_time;
            } elseif (!empty($sale->sale_date)) {
                $regularQrDate = function_exists('formatDate') ? formatDate($sale->sale_date) : (string) $sale->sale_date;
            }

            $lines = array_values(array_filter([
                trim($businessName),
                $sale->outlet?->outlet_name ? (__('Outlet') . ': ' . $sale->outlet->outlet_name) : null,
                ($sale->sale_no ?? '') !== '' ? (__('Sale No') . ': ' . $sale->sale_no) : null,
                $regularQrDate !== '' ? (__('Date') . ': ' . $regularQrDate) : null,
                __('Total') . ': ' . $this->formatMoney($sale->total_payable ?? $sale->grand_total),
                $sale->customer?->name ? (__('Customer') . ': ' . $sale->customer->name) : null,
            ]));

            return implode("\n", $lines);
        }

        return null;
    }

    protected function invoiceConfig(): array
    {
        $invConfig = session('company.invoice_configuration')
            ? json_decode(session('company.invoice_configuration'), true)
            : [];

        return is_array($invConfig) ? $invConfig : [];
    }

    protected function shouldShowGst(array $invConfig): bool
    {
        return function_exists('isEnableGST')
            && isEnableGST()
            && session('company.collect_tax') === 'Yes';
    }

    protected function resolveCharactersPerLine(): int
    {
        $printerId = function_exists('getPrinterIdByRegisterID') ? getPrinterIdByRegisterID() : null;
        if ($printerId && function_exists('getPrinterInfo')) {
            $printer = getPrinterInfo($printerId);
            if ($printer && !empty($printer->characters_per_line)) {
                return (int) $printer->characters_per_line;
            }
        }

        // 56mm/58mm thermal is typically ~32 chars.
        try {
            if (class_exists(\Modules\PrintManager\Services\PrinterService::class)) {
                $setting = app(\Modules\PrintManager\Services\PrinterService::class)->resolveSettings(
                    (int) session('company.company_id'),
                    session('outlet.outlet_id') ? (int) session('outlet.outlet_id') : null
                );
                $paper = strtolower((string) ($setting->paper_size ?? ''));
                if (str_contains($paper, '58') || str_contains($paper, '56')) {
                    return 32;
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        return (int) config('printmanager.receipt.characters_per_line', 32);
    }

    protected function bilingualLabel(?string $english, ?string $arabic = ''): string
    {
        $en = $this->plainLabel($english);
        $ar = $this->plainLabel($arabic);

        if ($ar !== '' && $en !== '' && $ar !== $en) {
            return $en . ' ' . $ar;
        }

        return $ar !== '' ? $ar : $en;
    }

    protected function plainLabel(?string $label): string
    {
        $text = trim(strip_tags((string) $label));
        // Drop HTML bilingual wrappers leftovers.
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Ensure valid UTF-8 (Arabic labels must survive).
        if ($text !== '' && ! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1256');
        }

        return $text !== '' ? $text : '';
    }

    protected function plainText(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    protected function formatMoney(mixed $amount): string
    {
        if (function_exists('formatAmount')) {
            return formatAmount($amount);
        }

        return number_format((float) $amount, 2, '.', '');
    }

    protected function resolveSale(string $saleIdInput): ?Sale
    {
        if (ctype_digit($saleIdInput)) {
            return $this->saleRepository->getByIdWithDetails((int) $saleIdInput);
        }

        try {
            return $this->saleRepository->getByIdWithDetails((int) decrypt($saleIdInput));
        } catch (\Throwable) {
            return null;
        }
    }
}
