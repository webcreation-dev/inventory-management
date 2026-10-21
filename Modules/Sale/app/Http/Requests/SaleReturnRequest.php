<?php

namespace Modules\Sale\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleReturnRequest extends FormRequest
{
    /**
     * Normalize money-like input (strip thousand separators) before rules run.
     */
    protected function prepareForValidation(): void
    {
        $rawPaid = $this->input('paid');
        if ($rawPaid === null || $rawPaid === '') {
            $paid = 0.0;
        } elseif (is_string($rawPaid)) {
            $rawPaid = preg_replace('/[^\d.,-]/', '', $rawPaid);
            $rawPaid = str_replace(',', '', $rawPaid);
            $paid = is_numeric($rawPaid) ? (float) $rawPaid : 0.0;
        } else {
            $paid = is_numeric($rawPaid) ? (float) $rawPaid : 0.0;
        }

        $this->merge(['paid' => $paid]);

        // Avoid NOT NULL / invalid empty select; service stores 0 when nothing is paid
        if ($paid <= 0) {
            $this->merge(['payment_method_id' => 0]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // Get sale return ID from route parameter (encrypted) and decrypt it
        $saleReturnId = null;
        $saleReturnParam = $this->route('sale_return');
        
        if ($saleReturnParam) {
            try {
                $saleReturnId = (int) decrypt($saleReturnParam);
            } catch (\Exception $e) {
                // If decryption fails, try to get it directly (might be already decrypted)
                $saleReturnId = is_numeric($saleReturnParam) ? (int) $saleReturnParam : null;
            }
        }

        $companyId = session('company.company_id');
        
        $rules = [
            'reference_no' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('sale_returns', 'reference_no')
                    ->ignore($saleReturnId)
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live'),
            ],
            'date' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'sale_id' => 'required|exists:sales,id',
            'items' => 'required|array|min:1',
            'items.*' => 'required|exists:items,id',
            'sale_quantities' => 'required|array|min:1',
            'sale_quantities.*' => 'required|numeric|min:0.01',
            'return_quantities' => 'required|array|min:1',
            'return_quantities.*' => 'required|numeric|min:0.01',
            'unit_prices_sale' => 'required|array|min:1',
            'unit_prices_sale.*' => 'required|numeric|min:0',
            'unit_prices_return' => 'required|array|min:1',
            'unit_prices_return.*' => 'required|numeric|min:0',
            'imei_serial' => 'nullable|array',
            'imei_serial.*' => 'nullable|string',
            'paid' => 'nullable|numeric|min:0',
            // Required only when refunding some amount now; otherwise store 0 in DB (NOT NULL column)
            'payment_method_id' => Rule::when(
                (float) ($this->input('paid') ?? 0) > 0,
                ['required', 'integer', 'exists:payment_methods,id'],
                ['nullable', 'integer'],
            ),
            'note' => 'nullable|string|max:1000',
        ];

        // For IMEI_Product, Serial_Product, and Medicine_Product, IMEI/Serial is required
        if ($this->items) {
            foreach ($this->items as $index => $itemId) {
                $item = \Modules\Stock\Models\Item::find($itemId);
                if ($item && in_array($item->type, ['IMEI_Product', 'Serial_Product', 'Medicine_Product'])) {
                    $rules["imei_serial.$index"] = 'required|string';
                }
            }
        }

        return $rules;
    }

    /**
     * Paid cannot exceed the line totals (server-side totals are authoritative vs. client "grandTotal").
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            if (! is_array($items) || empty($items)) {
                return;
            }

            $returnQuantities = $this->input('return_quantities', []);
            $unitPricesReturn = $this->input('unit_prices_return', []);
            $totalReturnAmount = 0.0;

            foreach ($items as $index => $itemId) {
                if (empty($itemId)) {
                    continue;
                }
                $qty = isset($returnQuantities[$index]) ? (float) $returnQuantities[$index] : 0;
                $price = isset($unitPricesReturn[$index]) ? (float) $unitPricesReturn[$index] : 0;
                $totalReturnAmount += $qty * $price;
            }

            $paid = (float) ($this->input('paid') ?? 0);

            if ($paid > $totalReturnAmount + 0.001) {
                $validator->errors()->add(
                    'paid',
                    'Paid amount cannot be greater than the Grand Total.'
                );
            }
        });
    }

    /**
     * Get the custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer is required',
            'customer_id.exists' => 'Selected customer is invalid',
            'sale_id.required' => 'Sale invoice is required',
            'sale_id.exists' => 'Selected sale invoice is invalid',
            'items.required' => 'At least one item is required',
            'items.min' => 'At least one item is required',
            'items.*.exists' => 'Selected item is invalid',
            'return_quantities.*.required' => 'Return quantity is required',
            'return_quantities.*.min' => 'Return quantity must be at least 0.01',
            'unit_prices_return.*.required' => 'Return price is required',
            'unit_prices_return.*.min' => 'Return price must be at least 0',
            'imei_serial.*.required' => 'IMEI/Serial number is required for this product type',
            'payment_method_id.required' => 'Payment method is required when paid amount is greater than zero',
            'payment_method_id.exists' => 'Selected payment method is invalid',
            'paid.min' => 'Paid amount must be at least 0',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'reference_no' => 'Reference No',
            'date' => 'Date',
            'customer_id' => 'Customer',
            'sale_id' => 'Sale Invoice',
            'items' => 'Items',
            'return_quantities' => 'Return Quantity',
            'unit_prices_return' => 'Return Price',
            'imei_serial' => 'IMEI/Serial',
            'payment_method_id' => 'Payment Method',
            'paid' => 'Paid Amount',
            'note' => 'Note',
        ];
    }
}
