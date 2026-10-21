<?php

namespace Modules\PrintManager\DTO;

class PrintJobResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $method,
        public readonly array $payload,
        public readonly ?int $logId = null,
        public readonly ?string $message = null,
        public readonly ?string $errorCode = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'method' => $this->method,
            'payload' => $this->payload,
            'log_id' => $this->logId,
            'message' => $this->message,
            'error_code' => $this->errorCode,
        ];
    }

    public static function failed(string $message, ?string $errorCode = null, ?int $logId = null): self
    {
        return new self(
            success: false,
            method: '',
            payload: [],
            logId: $logId,
            message: $message,
            errorCode: $errorCode,
        );
    }
}
