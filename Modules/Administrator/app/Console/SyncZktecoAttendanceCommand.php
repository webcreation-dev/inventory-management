<?php

namespace Modules\Administrator\Console;

use Illuminate\Console\Command;
use Modules\Administrator\Services\ZktDeviceAttendanceSyncService;

class SyncZktecoAttendanceCommand extends Command
{
    protected $signature = 'zkteco:sync-attendance
                            {--host= : Override ZKTECO_HOST}
                            {--port= : Override ZKTECO_PORT}
                            {--limit= : Max log rows to read (newest first on device)}';

    protected $description = 'Pull attendance punches from a ZKTeco device and save them into the attendances table';

    public function handle(ZktDeviceAttendanceSyncService $sync): int
    {
        $host = $this->option('host') ?: null;
        $port = $this->option('port') !== null && $this->option('port') !== ''
            ? (int) $this->option('port')
            : null;
        $limit = $this->option('limit') !== null && $this->option('limit') !== ''
            ? (int) $this->option('limit')
            : null;

        $this->info('Syncing from ZKTeco device...');

        $result = $sync->syncFromDevice($host, $port, $limit);

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Imported punches', $result['imported']],
                ['Skipped (already imported)', $result['skipped_duplicates']],
                ['Skipped (no matching employee zk_user_id)', $result['skipped_unknown_user']],
            ]
        );

        return count($result['errors']) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
