<?php

namespace Modules\Administrator\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Jmrashed\Zkteco\Lib\ZKTeco;
use Modules\Administrator\Models\Attendance;
use Modules\Administrator\Repositories\AttendanceRepository;

class ZktDeviceAttendanceSyncService
{
    public function __construct(
        protected AttendanceRepository $attendanceRepository
    ) {}

    /**
     * Pull logs from the device and upsert into attendances (one row per employee per calendar day).
     *
     * @return array{imported: int, skipped_duplicates: int, skipped_unknown_user: int, errors: list<string>}
     */
    public function syncFromDevice(?string $host = null, ?int $port = null, ?int $attendanceLimit = null): array
    {
        $host = $host ?? config('zkteco.host');
        $port = $port ?? config('zkteco.port');
        $attendanceLimit = $attendanceLimit ?? config('zkteco.attendance_limit');

        $result = [
            'imported' => 0,
            'skipped_duplicates' => 0,
            'skipped_unknown_user' => 0,
            'errors' => [],
        ];

        if (! $host) {
            $result['errors'][] = 'ZKTECO_HOST is not set in .env';

            return $result;
        }

        $zk = new ZKTeco($host, $port);

        if (! $zk->connect()) {
            $result['errors'][] = "Could not connect to ZKTeco device at {$host}:{$port}";

            return $result;
        }

        try {
            $zk->disableDevice();
            $logs = $attendanceLimit !== null && $attendanceLimit > 0
                ? $zk->getAttendance($attendanceLimit)
                : $zk->getAttendance();
        } catch (\Throwable $e) {
            $result['errors'][] = 'Device read failed: '.$e->getMessage();
            Log::warning('ZKTeco getAttendance failed', ['exception' => $e]);

            try {
                $zk->enableDevice();
                $zk->disconnect();
            } catch (\Throwable) {
            }

            return $result;
        }

        try {
            $zk->enableDevice();
            $zk->disconnect();
        } catch (\Throwable) {
        }

        if (! is_array($logs)) {
            $result['errors'][] = 'Unexpected response from device (not an array).';

            return $result;
        }

        usort($logs, function ($a, $b) {
            $ta = is_array($a) ? ($a['timestamp'] ?? '') : '';
            $tb = is_array($b) ? ($b['timestamp'] ?? '') : '';

            return strcmp((string) $ta, (string) $tb);
        });

        foreach ($logs as $row) {
            if (! is_array($row)) {
                continue;
            }

            $deviceUserId = isset($row['id']) ? trim((string) $row['id']) : '';
            $timestampRaw = $row['timestamp'] ?? null;

            if ($deviceUserId === '' || $timestampRaw === null || $timestampRaw === '') {
                continue;
            }

            try {
                $punchedAt = Carbon::parse((string) $timestampRaw);
            } catch (\Throwable) {
                continue;
            }

            $punchHash = hash('sha256', $deviceUserId."\0".$punchedAt->format('Y-m-d H:i:s'));

            if (DB::table('zk_attendance_imports')->where('punch_hash', $punchHash)->exists()) {
                $result['skipped_duplicates']++;

                continue;
            }

            $user = User::query()
                ->where('del_status', 'Live')
                ->where('zk_user_id', $deviceUserId)
                ->first();

            if (! $user || ! $user->company_id) {
                $result['skipped_unknown_user']++;

                continue;
            }

            DB::transaction(function () use ($user, $punchedAt, $punchHash, &$result) {
                DB::table('zk_attendance_imports')->insert([
                    'punch_hash' => $punchHash,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $dateStr = $punchedAt->format('Y-m-d');
                $timeStr = $punchedAt->format('H:i:s');

                /** @var Attendance|null $attendance */
                $attendance = Attendance::query()
                    ->where('del_status', 'Live')
                    ->where('company_id', $user->company_id)
                    ->where('employee_id', $user->id)
                    ->whereDate('date', $dateStr)
                    ->first();

                if (! $attendance) {
                    Attendance::query()->create([
                        'reference_no' => $this->attendanceRepository->generateReferenceNoForCompany((int) $user->company_id),
                        'employee_id' => $user->id,
                        'date' => $dateStr,
                        'in_time' => $timeStr,
                        'out_time' => null,
                        'note' => 'ZKTeco',
                        'user_id' => null,
                        'company_id' => $user->company_id,
                        'del_status' => 'Live',
                    ]);
                } else {
                    $inVal = $attendance->in_time;
                    $inCarbon = null;
                    if ($inVal) {
                        $timePart = is_string($inVal) ? $inVal : $inVal->format('H:i:s');
                        $inCarbon = Carbon::parse($dateStr.' '.$timePart);
                    }

                    if ($inCarbon === null) {
                        $attendance->in_time = $timeStr;
                    } elseif ($punchedAt->greaterThan($inCarbon)) {
                        $attendance->out_time = $timeStr;
                    }

                    $attendance->save();
                }

                $result['imported']++;
            });
        }

        return $result;
    }
}
