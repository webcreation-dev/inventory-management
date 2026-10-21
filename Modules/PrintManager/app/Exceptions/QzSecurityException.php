<?php

namespace Modules\PrintManager\Exceptions;

use Exception;

class QzSecurityException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'qz_security_error',
        int $httpStatus = 422,
    ) {
        parent::__construct($message, $httpStatus);
    }

    public function httpStatus(): int
    {
        return max(400, min(599, $this->getCode() ?: 422));
    }
}
