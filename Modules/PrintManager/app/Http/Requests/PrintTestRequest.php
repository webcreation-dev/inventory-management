<?php

namespace Modules\PrintManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PrintManager\Enums\PrintMethod;

class PrintTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'print_method' => ['nullable', 'string', Rule::in(PrintMethod::values())],
            'default_printer' => ['nullable', 'string', 'max:255'],
        ];
    }
}
