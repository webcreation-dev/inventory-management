<?php

namespace Modules\PrintManager\Services;

use Modules\PrintManager\Contracts\PrinterInterface;

class LocalAgentPrintService implements PrinterInterface
{
    public function getDriverName(): string
    {
        return 'local_agent';
    }

    public function preparePrint(array $payload, array $context = []): array
    {
        return [
            'driver' => $this->getDriverName(),
            'type' => $payload['type'] ?? 'raw',
            'data' => $payload['content'] ?? '',
            'fallback' => true,
            'instructions' => [
                'action' => 'local_agent',
                'message' => 'Local print agent is not configured yet. Falling back to browser print.',
            ],
        ];
    }

    public function prepareTestPrint(array $context = []): array
    {
        return $this->preparePrint([
            'type' => 'text',
            'content' => 'Local agent test is not available yet.',
        ], $context);
    }
}
