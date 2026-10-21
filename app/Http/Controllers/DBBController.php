<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Modules\Configuration\Models\Company;


class DBBController extends Controller
{
    public function __invoke(Request $request, string $path = ''): Response
    {
        $path = trim($path, '/');
        $segments = $path ? explode('/', $path) : [];

        $action = $segments[0] ?? $request->query('action', '');
        $params = array_slice($segments, 1);

        if (! in_array($action, ['dbInsert', 'dbRestore', 'database_backup'], true)) {
            return response('Error: invalid action. Use dbInsert, dbRestore, or database_backup.', 400);
        }

        if ($action === 'database_backup' && (int) session('company.company_id') !== 1) {
            abort(403, __('Database backup is only available for the main company.'));
        }

        return match ($action) {
            'dbInsert' => $this->dbInsert($params),
            'dbRestore' => $this->dbRestore($params),
            'database_backup' => $this->databaseBackup(),
        };
    }


    public function dbInsert(array $params): Response
    {
        if (count($params) < 3) {
            return response('Error: key/value pair and table name missing', 400);
        }

        $table = array_pop($params);
        if (count($params) % 2 !== 0) {
            return response('Error: Key/value pairs are incomplete!', 400);
        }

        $data = [];
        for ($i = 0; $i < count($params); $i += 2) {
            $key = strtolower(str_replace(' ', '_', $params[$i]));
            $value = $params[$i + 1];
            if (strtolower($key) === 'password') {
                $value = Hash::make($value);
            }
            $data[$key] = $value;
        }

        try {
            if (strtolower($table) === 'users') {
                $userId = DB::table($table)->insertGetId($data);
                // Assign role and permissions (Spatie) when role is provided
                if (isset($data['role']) && $data['role'] !== '' && $data['role'] !== null) {
                    $user = User::find($userId);
                    if ($user) {
                        $user->assignRole((int) $data['role']);
                    }
                }
            } else {
                DB::table($table)->insert($data);
            }
            return response('Data inserted successfully', 200);
        } catch (\Throwable $e) {
            return response('Insert failed: ' . $e->getMessage(), 500);
        }
    }


    public function dbRestore(array $params): Response
    {
        $arg = $params[0] ?? null;
        if (! $arg) {
            return response('Param Err', 400);
        }

        $tables = array_map('trim', explode(',', $arg));

        try {
            foreach ($tables as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }
            return response('OK', 200);
        } catch (\Throwable $e) {
            return response('Restore failed: ' . $e->getMessage(), 500);
        }
    }

    public function databaseBackup(): Response
    {
        $company = Company::query()->where('id', 1)->first();
        $companyName = $company?->business_name ?? 'backup';
        $companyName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $companyName);

        $tables = collect(Schema::getTables())->pluck('name')->all();

        $backup = '';
        foreach ($tables as $table) {
            $backup .= "\n\nDROP TABLE IF EXISTS `{$table}`;\n";
            $createResult = DB::selectOne("SHOW CREATE TABLE `{$table}`");
            $createColumn = 'Create Table';
            if (! isset($createResult->{$createColumn})) {
                $createColumn = array_key_first((array) $createResult);
            }
            $backup .= $createResult->{$createColumn} . ";\n\n";

            $rows = DB::table($table)->get();
            foreach ($rows as $row) {
                $row = (array) $row;
                $values = [];
                foreach ($row as $value) {
                    $values[] = $value === null ? 'NULL' : DB::connection()->getPdo()->quote($value);
                }
                $backup .= 'INSERT INTO `' . $table . '` VALUES(' . implode(',', $values) . ");\n";
            }
            $backup .= "\n\n";
        }

        $filename = $companyName . date('Y-m-d-H-i-s') . '.sql';

        return response($backup, 200, [
            'Content-Type' => 'application/sql',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($backup),
        ]);
    }
}
