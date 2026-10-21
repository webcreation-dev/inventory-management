<?php

namespace Modules\PrintManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QzSignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            // Official QZ parameter name is "request"
            'request' => ['nullable', 'string', 'max:65535'],
            'toSign' => ['nullable', 'string', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->filled('request') && $this->filled('toSign')) {
            $this->merge(['request' => $this->input('toSign')]);
        }
    }

    public function signingPayload(): string
    {
        return trim((string) ($this->input('request') ?? $this->input('toSign') ?? ''));
    }
}
