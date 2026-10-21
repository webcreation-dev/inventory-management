<?php

namespace Modules\PrintManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrintLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'log_id' => ['required', 'integer', 'exists:print_logs,id'],
            'status' => ['required', Rule::in(['success', 'failed'])],
            'error_message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
