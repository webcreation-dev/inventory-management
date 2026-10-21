<?php

namespace Modules\PrintManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrintRawRequest extends FormRequest
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
            'raw' => ['required', 'string'],
            'encoding' => ['nullable', Rule::in(['plain', 'base64'])],
        ];
    }
}
