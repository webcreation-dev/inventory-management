<?php

namespace Modules\PrintManager\Services;

use Modules\PrintManager\Models\PrintLog;

class PrintLogService
{
    public function create(array $data): PrintLog
    {
        if (!config('printmanager.logging.enabled', true)) {
            return new PrintLog($data);
        }

        return PrintLog::query()->create($data);
    }

    public function markSuccess(PrintLog $log, ?string $message = null): PrintLog
    {
        $log->update([
            'status' => 'success',
            'error_message' => $message,
        ]);

        return $log->fresh();
    }

    public function markFailed(PrintLog $log, string $message): PrintLog
    {
        $log->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);

        return $log->fresh();
    }
}
