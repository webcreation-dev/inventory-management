<?php

namespace Modules\PrintManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrintReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'module' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'copies' => ['nullable', 'integer', 'min:1', 'max:10'],
            'open_cash_drawer' => ['nullable', 'boolean'],
            'allow_fallback' => ['nullable', 'boolean'],
            'receipt' => ['required', 'array'],
            'receipt.title' => ['nullable', 'string', 'max:255'],
            'receipt.footer' => ['nullable', 'string', 'max:500'],
            'receipt.header_lines' => ['nullable', 'array'],
            'receipt.header_lines.*' => ['string', 'max:255'],
            'receipt.items' => ['nullable', 'array'],
            'receipt.items.*.name' => ['required_with:receipt.items', 'string', 'max:255'],
            'receipt.items.*.qty' => ['required_with:receipt.items', 'numeric', 'min:0'],
            'receipt.items.*.price' => ['required_with:receipt.items', 'numeric', 'min:0'],
            'receipt.totals' => ['nullable', 'array'],
            'receipt.totals.*.label' => ['required_with:receipt.totals', 'string', 'max:100'],
            'receipt.totals.*.amount' => ['required_with:receipt.totals', 'numeric'],
            'receipt.barcode' => ['nullable', 'string', 'max:255'],
            'receipt.qr_code' => ['nullable', 'string', 'max:2000'],
            'receipt.characters_per_line' => ['nullable', 'integer', 'min:20', 'max:64'],
        ];
    }
}
