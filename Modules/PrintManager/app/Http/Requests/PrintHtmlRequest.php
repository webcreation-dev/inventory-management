<?php

namespace Modules\PrintManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrintHtmlRequest extends FormRequest
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
            'html' => ['required', 'string'],
        ];
    }
}
