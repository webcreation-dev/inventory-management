<?php

namespace Modules\PrintManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PrintManager\Enums\PrintMethod;

class StorePrintSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'default_printer' => ['nullable', 'string', 'max:255'],
            'paper_size' => ['required', 'string', Rule::in(array_keys(config('printmanager.paper_sizes', [])))],
            'print_method' => ['required', 'string', Rule::in(PrintMethod::values())],
            'copies' => ['required', 'integer', 'min:1', 'max:10'],
            'cash_drawer' => ['required', Rule::in(['ON', 'OFF'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'character_encoding' => ['required', 'string', 'max:50'],
            'margins' => ['nullable', 'array'],
            'margins.top' => ['nullable', 'numeric', 'min:0'],
            'margins.right' => ['nullable', 'numeric', 'min:0'],
            'margins.bottom' => ['nullable', 'numeric', 'min:0'],
            'margins.left' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
