<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use PDO;
use PDOException;

/**
 * 
 */
class InstallerService
{
    private function installStatePath(): string
    {
        return storage_path('app/install_db_state.json');
    }

    /**
     * Persists verified purchase data so the database step works even when PHP sessions are lost
     * (e.g. SESSION_DRIVER=database and "empty existing" drops the sessions table).
     */
    private function installPurchaseStatePath(): string
    {
        return storage_path('app/install_purchase_state.json');
    }

    private function installBusinessTypeStatePath(): string
    {
        return storage_path('app/install_business_type_state.json');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveInstallPurchaseState(array $data): bool
    {
        $path = $this->installPurchaseStatePath();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return false;
        }

        return file_put_contents($path, json_encode($data), LOCK_EX) !== false;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getInstallPurchaseState(): ?array
    {
        $path = $this->installPurchaseStatePath();
        if (!is_file($path)) {
            return null;
        }
        $content = file_get_contents($path);
        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Valid purchase payload for continuing the installer (session and/or disk).
     *
     * @param mixed $data
     */
    public function isValidInstallPurchasePayload($data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        return ($data['username'] ?? '') !== '' && ($data['purchase_code'] ?? '') !== '';
    }

    /**
     * Prefer session; fall back to disk and re-hydrate session. Use on GET/POST database step.
     *
     * @return array<string, mixed>|null
     */
    public function resolveInstallPurchase(): ?array
    {
        $session = Session::get('install_purchase');
        if ($this->isValidInstallPurchasePayload($session)) {
            /** @var array<string, mixed> $session */
            return $session;
        }

        $disk = $this->getInstallPurchaseState();
        if ($this->isValidInstallPurchasePayload($disk)) {
            Session::put('install_purchase', $disk);

            return $disk;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveInstallBusinessTypeState(array $data): bool
    {
        $path = $this->installBusinessTypeStatePath();
        $dir = dirname($path);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true)) {
            return false;
        }

        return file_put_contents($path, json_encode($data), LOCK_EX) !== false;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getInstallBusinessTypeState(): ?array
    {
        $path = $this->installBusinessTypeStatePath();
        if (! is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);
        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param mixed $data
     */
    public function isValidInstallBusinessTypePayload($data): bool
    {
        if (! is_array($data)) {
            return false;
        }

        return ($data['business_type'] ?? '') !== '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveInstallBusinessType(): ?array
    {
        $session = Session::get('install_business_type');
        if ($this->isValidInstallBusinessTypePayload($session)) {
            /** @var array<string, mixed> $session */
            return $session;
        }

        $disk = $this->getInstallBusinessTypeState();
        if ($this->isValidInstallBusinessTypePayload($disk)) {
            Session::put('install_business_type', $disk);

            return $disk;
        }

        return null;
    }

    private function blueimpPath(string $file = ''): string
    {
        return base_path('bootstrap/blueimp/' . ltrim($file, '/'));
    }
    private function instPa(): string
    {
        return base_path('bootstrap/__d__.enc');
    }

    private function instUPa(): string
    {
        return base_path('bootstrap/__u__.enc');
    }

    /**
     * 
     */
    private function checkSumC(int $code): int
    {
        return $code ^ 0x8A3F;
    }

    /**
     * Log install/validation errors server-side only; never expose URLs or exception details to client.
     */
    private function sanitizeInstallError(\Throwable $e, string $genericMessage): string
    {
        Log::channel('single')->error('Install/validation error: ' . $e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        return $genericMessage;
    }
    /**
     * 
     */
    public function saveInstallDbState(array $data): bool
    {
        $path = $this->installStatePath();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return false;
        }
        return file_put_contents($path, json_encode($data), LOCK_EX) !== false;
    }

    /**
     * 
     *
     * @return array<string, mixed>|null
     */
    public function getInstallDbState(): ?array
    {
        $path = $this->installStatePath();
        if (!is_file($path)) {
            return null;
        }
        $content = file_get_contents($path);
        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    /**
     * 
     */
    public function clearInstallDbState(): void
    {
        foreach ([
            $this->installStatePath(),
            $this->installPurchaseStatePath(),
            $this->installBusinessTypeStatePath(),
        ] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * 
     */
    public function isInstalled(): bool
    {
        $envPath = base_path('.env');
        if (!is_file($envPath)) {
            return false;
        }
        $content = file_get_contents($envPath);
        return (bool) preg_match('/^\s*APP_INSTALLED\s*=\s*true\s*$/mi', $content);
    }

    /**
     * 
     */
    public function getEnvironmentChecks(): array
    {
        $checks = [];
        $allPassed = true;

        $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=') && version_compare(PHP_VERSION, '8.3.0', '<');
        $checks[] = [
            'label' => 'PHP ' . PHP_VERSION,
            'passed' => $phpOk,
            'message' => $phpOk ? 'PHP 8.2' : 'PHP 8.2 is required (current: ' . PHP_VERSION . ')',
        ];
        if (!$phpOk) {
            $allPassed = false;
        }

        $extensions = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'json', 'ctype', 'fileinfo', 'curl', 'zip'];
        foreach ($extensions as $ext) {
            $loaded = extension_loaded($ext);
            $checks[] = [
                'label' => "Extension: {$ext}",
                'passed' => $loaded,
                'message' => $loaded ? 'Loaded' : 'Required',
            ];
            if (!$loaded) {
                $allPassed = false;
            }
        }

        $paths = [
            storage_path() => 'storage/',
            base_path('bootstrap/cache') => 'bootstrap/cache/',
            base_path() => 'project root (.env)',
        ];
        foreach ($paths as $path => $label) {
            if (is_dir($path)) {
                $writable = is_writable($path);
                $checks[] = [
                    'label' => "Writable: {$label}",
                    'passed' => $writable,
                    'message' => $writable ? 'Writable' : 'Not writable',
                ];
                if (!$writable) {
                    $allPassed = false;
                }
            } else {
                $parentWritable = is_dir(dirname($path)) && is_writable(dirname($path));
                $checks[] = [
                    'label' => "Writable: {$label}",
                    'passed' => $parentWritable,
                    'message' => $parentWritable ? 'Parent writable' : 'Parent not writable',
                ];
                if (!$parentWritable) {
                    $allPassed = false;
                }
            }
        }

        $artisanExists = file_exists(base_path('artisan'));
        $checks[] = [
            'label' => 'Laravel artisan',
            'passed' => $artisanExists,
            'message' => $artisanExists ? 'Found' : 'Not found',
        ];
        if (!$artisanExists) {
            $allPassed = false;
        }

        return ['checks' => $checks, 'passed' => $allPassed];
    }


    public function getInstallationTypeFromEnv(): string
    {
        if (defined('LP') && strtoupper((string) LP) === 'BD') {
            return 'BD';
        }
        foreach ([base_path('.env'), base_path('.env.example')] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $content = @file_get_contents($path);
            if ($content !== false && preg_match('/^\s*LP\s*=\s*["\']?([^"\'\r\n]*)["\']?\s*$/mi', $content, $m)) {
                $lp = trim((string) $m[1]);
                if (strtoupper($lp) === 'BD') {
                    return 'BD';
                }
                break;
            }
        }
        return 'CC';
    }

    /**
     * 
     *
     * @return array{product_id: string, source: string}
     */
    private function getProductIdAndSource(): array
    {
        if ($this->getInstallationTypeFromEnv() === 'BD') {
            return ['product_id' => '133347', 'source' => 'Bangladesh'];
        }
        return ['product_id' => '24326862', 'source' => 'CodeCanyon'];
    }

    /**
     * 
     */
    public function verPur(
        string $username,
        string $purchaseCode,
        string $installationUrl,
        string $ip,
        string $referer,
        string $path,
        bool $alreadyInstalledGetUpdate = false
    ): array {
        $owner = 'doorsoftco';
        $productSource = $this->getProductIdAndSource();
        $source = $productSource['source'];
        $productId = $productSource['product_id'];
        $hostname = gethostname() ?: '';
        $url = str_rot13("uggcf://qbbefbsg.pb/qfy/Inyvqngvba/Inyvqngr/");//Validate
        $payload = [
            'owner' => $owner,
            'username' => $username,
            'purchase_code' => $purchaseCode,
            'source' => $source,
            'installation_url' => $installationUrl,
            'ip' => $ip,
            'hostname' => $hostname,
            'referer' => $referer,
            'path' => $path,
            'already_installed_get_update' => $alreadyInstalledGetUpdate ? 'yes' : 'no',
        ];
        if ($source === 'CodeCanyon') {
            $payload['product_id'] = $productId;
            $payload['is_501'] = 'no';
        }
        try {
            $response = Http::asForm()
                ->withoutVerifying()
                ->timeout(60)
                ->post($url, $payload);

            $body = $response->body();
            $decoded = json_decode($body);

            if (!is_object($decoded)) {
                return [
                    'success' => false,
                    'message' => 'Invalid response. Please try again.',
                    'installation_status' => null,
                    'current_installed_url' => null,
                ];
            }

            if (isset($decoded->status) && $decoded->status === 'success') {
                return [
                    'success' => true,
                    'message' => $decoded->message ?? 'Purchase verified.',
                    'installation_status' => $decoded->installation_status ?? null,
                    'current_installed_url' => $decoded->current_installed_url ?? null,
                ];
            }

            return [
                'success' => false,
                'message' => $decoded->message ?? 'Verification failed.',
                'installation_status' => null,
                'current_installed_url' => $decoded->current_installed_url ?? null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Verification request failed. Please check your internet connection and try again.',
                'installation_status' => null,
                'current_installed_url' => null,
            ];
        }
    }

    /**
     *
     */
    public function testDatabaseConnection(
        string $host,
        string $port,
        string $username,
        string $password,
        string $database,
        bool $createIfNotExists = true
    ): array {
        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $stmt = $pdo->query(
                "SELECT 1 FROM information_schema.schemata WHERE schema_name = " . $pdo->quote($database)
            );
            $dbExists = $stmt && $stmt->fetch();

            if (!$dbExists) {
                if ($createIfNotExists) {
                    $safeName = str_replace('`', '``', $database);
                    $pdo->exec("CREATE DATABASE `{$safeName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    return ['success' => true, 'message' => 'Database created successfully.'];
                }
                return [
                    'success' => false,
                    'message' => 'Database does not exist. Enable "Create database if it does not exist" or create it manually.',
                ];
            }

            $pdo->exec('USE `' . str_replace('`', '``', $database) . '`');
            return ['success' => true, 'message' => 'Connection successful.'];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database connection failed. Please check your credentials and that the server is reachable.',
            ];
        }
    }

    /**
     * Drop all tables in the given database (destructive). Used when installer chooses "empty existing" fresh import.
     *
     * @return array{success: bool, message: string}
     */
    public function dropAllTablesInDatabase(
        string $host,
        string $port,
        string $username,
        string $password,
        string $database
    ): array {
        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec('USE `' . str_replace('`', '``', $database) . '`');

            $stmtViews = $pdo->query(
                'SELECT TABLE_NAME FROM information_schema.VIEWS '
                . 'WHERE TABLE_SCHEMA = ' . $pdo->quote($database)
            );
            $views = $stmtViews ? $stmtViews->fetchAll(PDO::FETCH_COLUMN) : [];
            if ($views === false) {
                $views = [];
            }

            $stmt = $pdo->query(
                'SELECT TABLE_NAME FROM information_schema.TABLES '
                . 'WHERE TABLE_SCHEMA = ' . $pdo->quote($database) . " AND TABLE_TYPE = 'BASE TABLE'"
            );
            $tables = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            if ($tables === false) {
                $tables = [];
            }

            $skipTables = [];
            if (config('session.driver') === 'database') {
                $sessionTable = (string) config('session.table', 'sessions');
                if ($sessionTable !== '') {
                    $skipTables[] = $sessionTable;
                }
            }

            $tables = array_values(array_filter($tables, static function ($t) use ($skipTables) {
                return is_string($t) && $t !== '' && !in_array($t, $skipTables, true);
            }));

            if ($views === [] && $tables === []) {
                return [
                    'success' => true,
                    'message' => 'No application tables to drop (empty database, or only protected tables such as sessions are present).',
                ];
            }

            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach ($views as $viewName) {
                if (!is_string($viewName) || $viewName === '') {
                    continue;
                }
                $safe = str_replace('`', '``', $viewName);
                $pdo->exec('DROP VIEW IF EXISTS `' . $safe . '`');
            }
            foreach ($tables as $table) {
                if (!is_string($table) || $table === '') {
                    continue;
                }
                $safe = str_replace('`', '``', $table);
                $pdo->exec('DROP TABLE IF EXISTS `' . $safe . '`');
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

            return ['success' => true, 'message' => 'All tables dropped successfully.'];
        } catch (PDOException $e) {
            Log::channel('single')->error('Install drop all tables failed', [
                'message' => $e->getMessage(),
                'database' => $database,
            ]);

            return [
                'success' => false,
                'message' => 'Could not empty the database. Check permissions and try again.',
            ];
        }
    }

    /**
     * 
     */
    public function writeEnv(array $data): bool
    {
        $envPath = base_path('.env');
        $examplePath = base_path('.env.example');

        if (!file_exists($examplePath)) {
            return false;
        }

        $content = file_get_contents($examplePath);
        $content = preg_replace('/^\s*DB_CONNECTION=.*/m', 'DB_CONNECTION=mysql', $content);
        $content = preg_replace('/^#\s*(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD).*$/m', '', $content);

        $appKey = $data['app_key'] ?? ('base64:' . $this->generateAppKey());
        $appUrl = rtrim(trim((string) ($data['app_url'] ?? 'http://localhost'), " \t\n\r\0\x0B\"'"), '/');
        $dbHost = $data['db_host'] ?? '127.0.0.1';
        $dbPort = $data['db_port'] ?? '3306';
        $dbName = (string) ($data['db_name'] ?? '');
        $dbUser = (string) ($data['db_username'] ?? '');
        if (strpos($data['db_password'], '#') !== false) {
            $dbPass = '"' . $data['db_password'] . '"';
        } else {
            $dbPass = (string) ($data['db_password'] ?? '');
        }
        $appName = $this->getInstallationTypeFromEnv() === 'BD' ? 'Biponi' : 'Retail POS';
        $content = $this->setEnvLine($content, 'APP_NAME', $appName);
        $content = $this->setEnvLine($content, 'APP_KEY', $appKey);
        $content = $this->setEnvLine($content, 'APP_URL', $appUrl, doubleQuoted: true);
        $content = $this->setEnvLine($content, 'APP_INSTALLED', 'false');
        $content = $this->setEnvLine($content, 'DB_CONNECTION', 'mysql');
        $content = $this->setEnvLine($content, 'DB_HOST', $dbHost);
        $content = $this->setEnvLine($content, 'DB_PORT', $dbPort);
        $content = $this->setEnvLine($content, 'DB_DATABASE', $dbName);
        $content = $this->setEnvLine($content, 'DB_USERNAME', $dbUser);
        $content = $this->setEnvLine($content, 'DB_PASSWORD', $dbPass);
        $content = $this->setEnvLine($content, 'SESSION_DRIVER', 'file');
        $content = $this->setEnvLine($content, 'CACHE_STORE', 'file');
        $content = $this->setEnvLine($content, 'QUEUE_CONNECTION', 'sync');
        $content = $this->setEnvLine($content, 'IS_DEMO', 'false');

        if ($this->getInstallationTypeFromEnv() === 'BD') {
            $content = $this->setEnvLine($content, 'LP', 'BD');
        }

        if (file_exists($envPath)) {
            @chmod($envPath, 0666);
        }

        $written = file_put_contents($envPath, $content, LOCK_EX) !== false;
        if ($written) {
            clearstatcache(true, $envPath);
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($envPath, true);
            }
        }

        return $written;
    }

    private function setEnvLine(string $content, string $key, string $value, bool $doubleQuoted = false): string
    {
        if ($doubleQuoted) {
            $escaped = str_replace(['\\', '"', '$'], ['\\\\', '\"', '\\$'], $value);
            $replacement = $key . '="' . $escaped . '"';
        } else {
            $value = str_replace(['\\', '$'], ['\\\\', '\\$'], $value);
            if (str_contains($value, ' ') || str_contains($value, "'")) {
                $value = "'" . str_replace("'", "''", $value) . "'";
            }
            $replacement = $key . '=' . $value;
        }
        if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/m', $content)) {
            return preg_replace('/^\s*' . preg_quote($key, '/') . '\s*=.*/m', $replacement, $content);
        }
        return trim($content) . "\n" . $replacement . "\n";
    }

    public function generateAppKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    /**
     * 
     */
    private function reqInstReg(
        string $username,
        string $purchaseCode,
        string $installationUrl,
        bool $alreadyInstalledGetUpdate
    ): array {
        $owner = 'doorsoftco';
        $productSource = $this->getProductIdAndSource();
        $source = $productSource['source'];
        $productId = $productSource['product_id'];
        $hostname = gethostname() ?: '';
        $installationDateAndTime = date('Y-m-d H:i:s');
        $url = str_rot13('uggcf://qbbefbsg.pb/qfy/Chepunfrinevsvl/Vafg/'); // Inst
        $payload = [
            'owner' => $owner,
            'username' => $username,
            'purchase_code' => $purchaseCode,
            'pc_hostname' => $hostname,
            'source' => $source,
            'product_id' => $productId,
            'installation_url' => $installationUrl,
            'installation_date_and_time' => $installationDateAndTime,
            'already_installed_get_update' => $alreadyInstalledGetUpdate ? 'yes' : 'no',
        ];

        $businessType = $this->resolveInstallBusinessType();
        if ($businessType !== null) {
            $payload['business_type'] = (string) ($businessType['business_type'] ?? '');
        }

        try {
            $response = Http::asForm()
                ->withoutVerifying()
                ->timeout(60)
                ->post($url, $payload);

            $body = $response->body();
            $decoded = json_decode($body);
            if (!is_object($decoded)) {
                return [
                    'success' => false,
                    'message' => 'Invalid response. Please try again.',
                    'decoded' => null,
                ];
            }

            if (!isset($decoded->status) || $decoded->status !== 'success') {
                return [
                    'success' => false,
                    'message' => $decoded->message ?? 'Installation server denied the request.',
                    'decoded' => $decoded,
                ];
            }

            $okMessage = isset($decoded->message) && is_string($decoded->message)
                ? $decoded->message
                : 'Installation registered.';

            return [
                'success' => true,
                'message' => $okMessage,
                'decoded' => $decoded,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Could not reach the installation server. Please try again.',
                'decoded' => null,
            ];
        }
    }

    /**
     * 
     */
    public function registerInstallationOnly(
        string $username,
        string $purchaseCode,
        string $installationUrl,
        ?string $appKey = null,
        bool $alreadyInstalledGetUpdate = false
    ): array {
        $reg = $this->reqInstReg(
            $username,
            $purchaseCode,
            $installationUrl,
            $alreadyInstalledGetUpdate
        );
        if (!$reg['success']) {
            return [
                'success' => false,
                'message' => $reg['message'],
            ];
        }
        $decoded = $reg['decoded'];
        if (!is_object($decoded)) {
            return [
                'success' => false,
                'message' => 'Invalid response. Please try again.',
            ];
        }

        $isBangladesh = $this->getProductIdAndSource()['source'] === 'Bangladesh';
        if ($isBangladesh && !empty($decoded->b_package_details) && $appKey !== null && $appKey !== '') {
            $bPackageDetails = is_string($decoded->b_package_details)
                ? $decoded->b_package_details
                : json_encode($decoded->b_package_details);
            $this->botPaFi($bPackageDetails, $appKey);
        }

        return [
            'success' => true,
            'message' => $reg['message'],
        ];
    }

    /**
     * 
     */
    public function connectDatabase(
        array $dbConfig,
        string $username,
        string $purchaseCode,
        string $installationUrl,
        ?string $appKey = null,
        bool $alreadyInstalledGetUpdate = false
    ): array {
        $productSource = $this->getProductIdAndSource();
        $source = $productSource['source'];
        $isBangladesh = $source === 'Bangladesh';

        $reg = $this->reqInstReg(
            $username,
            $purchaseCode,
            $installationUrl,
            $alreadyInstalledGetUpdate
        );
        if (!$reg['success']) {
            return [
                'success' => false,
                'message' => $reg['message'],
            ];
        }
        $decoded = $reg['decoded'];
        if (!is_object($decoded)) {
            return [
                'success' => false,
                'message' => 'Invalid response. Please try again.',
            ];
        }

        try {
            if (empty($decoded->database) || !is_string($decoded->database)) {
                return [
                    'success' => false,
                    'message' => 'No database content received from server.',
                ];
            }

            $sql = $decoded->database;
            $sql = str_replace('syasdf', $dbConfig['db_username'], $sql);
            $sql = str_replace('ysaea', $dbConfig['db_host'], $sql);

            $host = $dbConfig['db_host'];
            $port = $dbConfig['db_port'] ?? '3306';
            $user = $dbConfig['db_username'];
            $pass = $dbConfig['db_password'] ?? '';
            $database = $dbConfig['db_name'];

            $mysqli = @new \mysqli($host, $user, $pass, $database, (int) $port);
            if ($mysqli->connect_errno) {
                return [
                    'success' => false,
                    'message' => 'Database connection failed. Please check your credentials.',
                ];
            }

            $mysqli->set_charset('utf8mb4');

            if ($alreadyInstalledGetUpdate) {
                $executed = $this->executeSqlStatementsSkippingErrors($mysqli, $sql);
                $mysqli->close();
                if (!$executed['success']) {
                    return [
                        'success' => false,
                        'message' => $executed['message'],
                    ];
                }
            } else {
                $ok = $mysqli->multi_query($sql);
                if (!$ok) {
                    $mysqli->close();
                    return [
                        'success' => false,
                        'message' => 'Database import failed. Please try again.',
                    ];
                }
                do {
                    if ($result = $mysqli->store_result()) {
                        $result->free();
                    }
                } while ($mysqli->more_results() && $mysqli->next_result());

                $mysqli->close();
            }

            if ($isBangladesh && !empty($decoded->b_package_details) && $appKey !== null && $appKey !== '') {
                $bPackageDetails = is_string($decoded->b_package_details)
                    ? $decoded->b_package_details
                    : json_encode($decoded->b_package_details);
                $this->botPaFi($bPackageDetails, $appKey);
            }

            $downloadResult = $this->connectFiles();
            if (!$downloadResult['success']) {
                return [
                    'success' => false,
                    'message' => $downloadResult['message'],
                ];
            }
            $successMessage = $alreadyInstalledGetUpdate
                ? 'Database updated successfully.'
                : 'Database imported successfully.';

            return ['success' => true, 'message' => $successMessage];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Import failed. Please try again.',
            ];
        }
    }

    /**
     * 
     *
     * @return array{success: bool, message: string}
     */
    private function executeSqlStatementsSkippingErrors(\mysqli $mysqli, string $sql): array
    {
        // MySQL error codes to skip (object already exists or doesn't exist for DROP)
        $skipErrorCodes = [
            1050, // Table 'xxx' already exists
            1060, // Duplicate column name 'xxx'
            1061, // Duplicate key name 'xxx'
            1062, // Duplicate entry for key (INSERT conflict)
            1091, // Can't DROP; check that column/key exists
            1054, // Unknown column (e.g. in DROP when column already removed)
            1146, // Table doesn't exist (e.g. DROP TABLE when already dropped)
        ];

        $statements = $this->splitSqlStatements($sql);
        $skipped = 0;

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || preg_match('/^(--|#|\/\*)/', $stmt)) {
                continue;
            }

            $errno = 0;
            $errMsg = '';
            $ok = false;

            try {
                $ok = @$mysqli->query($stmt);
                if (!$ok) {
                    $errno = (int) $mysqli->errno;
                    $errMsg = $mysqli->error ?? '';
                }
            } catch (\Throwable $e) {
                $ok = false;
                $errno = (int) ($e->getCode() ?: 0);
                $errMsg = $e->getMessage();
            }

            if (!$ok) {
                $shouldSkip = in_array($errno, $skipErrorCodes, true)
                    || preg_match('/already exists|duplicate column|duplicate key|duplicate entry|check that column|check that key|doesn\'t exist|Unknown column/i', $errMsg);

                if ($shouldSkip) {
                    $skipped++;
                    continue;
                }

                $mysqli->close();
                return [
                    'success' => false,
                    'message' => 'Database import failed. Please try again.',
                ];
            }
        }

        $msg = 'Database updated successfully.';
        if ($skipped > 0) {
            $msg .= " ({$skipped} statements skipped - already applied)";
        }
        return ['success' => true, 'message' => $msg];
    }

    /**
     * 
     */
    private function splitSqlStatements(string $sql): array
    {
        // Remove MySQL comments (-- and #) that span full lines to avoid splitting inside them
        $sql = preg_replace('/^\s*--[^\r\n]*$/m', '', $sql);
        $sql = preg_replace('/^\s*#[^\r\n]*$/m', '', $sql);

        // Split: semicolon + optional whitespace + newline, or semicolon + space before SQL keywords
        $pattern = '/;\s*[\r\n]+|;\s+(?=\s*(?:ALTER|CREATE|DROP|INSERT|UPDATE|DELETE|RENAME|SET|USE|TRUNCATE|REPLACE)\b)/mi';
        $parts = preg_split($pattern, $sql);

        if ($parts === false) {
            return [];
        }

        return array_filter(array_map('trim', $parts), fn ($s) => $s !== '');
    }

    /**
     * 
     */
    private function connectFiles(): array
    {
        $baseUrl = str_rot13("uggcf://ergnvy-cbf.qbbefbsg-qrzb.pbz/ejsvyrf/");
        $isBd = $this->getInstallationTypeFromEnv() === 'BD';
        $zipFileName = $isBd ? 'application_bip_r.zip' : 'application_code_r.zip';
        $zipUrl = $baseUrl . $zipFileName;

        $tempZipPath = storage_path('app/install_app_package.zip');
        $tempExtractDir = storage_path('app/install_app_extract');

        try {
            $response = Http::withoutVerifying()
                ->timeout(120)
                ->get($zipUrl);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to download application package: HTTP ' . $response->status(),
                ];
            }

            $dir = dirname($tempZipPath);
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                return ['success' => false, 'message' => 'Could not create storage directory for download.'];
            }
            if (file_put_contents($tempZipPath, $response->body()) === false) {
                return ['success' => false, 'message' => 'Could not save downloaded zip file.'];
            }

            if (!class_exists(\ZipArchive::class)) {
                @unlink($tempZipPath);
                return ['success' => false, 'message' => 'Zip extension is required to extract the application package.'];
            }

            $zip = new \ZipArchive();
            if ($zip->open($tempZipPath) !== true) {
                @unlink($tempZipPath);
                return ['success' => false, 'message' => 'Invalid or corrupted zip file.'];
            }

            if (is_dir($tempExtractDir)) {
                $this->removeDirectoryRecursive($tempExtractDir);
            }
            if (!mkdir($tempExtractDir, 0755, true)) {
                $zip->close();
                @unlink($tempZipPath);
                return ['success' => false, 'message' => 'Could not create extraction directory.'];
            }

            $zip->extractTo($tempExtractDir);
            $zip->close();
            @unlink($tempZipPath);

            $entries = array_values(array_diff(scandir($tempExtractDir), ['.', '..']));
            $rootPath = base_path();
            if (count($entries) === 1 && is_dir($tempExtractDir . DIRECTORY_SEPARATOR . $entries[0])) {
                $this->recurseCopyInto($tempExtractDir . DIRECTORY_SEPARATOR . $entries[0], $rootPath);
            } else {
                $this->recurseCopyInto($tempExtractDir, $rootPath);
            }

            $this->removeDirectoryRecursive($tempExtractDir);

            return ['success' => true, 'message' => 'Application Updated successfully.'];
        } catch (\Throwable $e) {
            if (is_file($tempZipPath ?? '')) {
                @unlink($tempZipPath);
            }
            if (is_dir($tempExtractDir ?? '')) {
                $this->removeDirectoryRecursive($tempExtractDir);
            }
            return [
                'success' => false,
                'message' => 'Application Update failed. Please try again.',
            ];
        }
    }

    /**
     *
     */
    private function recurseCopyInto(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            @mkdir($dst, 0755, true);
        }
        $items = @scandir($src);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $srcPath = $src . DIRECTORY_SEPARATOR . $item;
            $dstPath = $dst . DIRECTORY_SEPARATOR . $item;
            if (is_dir($srcPath)) {
                $this->recurseCopyInto($srcPath, $dstPath);
            } else {
                $dir = dirname($dstPath);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                @copy($srcPath, $dstPath);
            }
        }
    }

    /**
     * 
     */
    private function removeDirectoryRecursive(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = @scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full)) {
                $this->removeDirectoryRecursive($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($path);
    }

    /**
     * 
     */
    public function uninstallLicense(
        array $params,
        string $ip,
        string $referer,
        string $path
    ): array {
        $productSource = $this->getProductIdAndSource();
        $source = $productSource['source'];
        $productId = $productSource['product_id'];
        $owner = $params['owner'] ?? 'doorsoftco';
        $currentUrl = $params['current_installation_url'] ?? '';
        $newUrl = $params['new_installation_url'] ?? '';
        $actionType = $params['action_type'] ?? 'uninstall';
        $baseUrlInstall = $params['base_url_install'] ?? '';
        
        $url = str_rot13('uggcf://qbbefbsg.pb/qfy/Inyvqngvba/havafgnyyYvprafr/');

        try {
            $response = Http::asForm()
                ->withoutVerifying()
                ->timeout(60)
                ->post($url, [
                    'username' => $params['username'],
                    'purchase_code' => $params['purchase_code'],
                    'source' => $source,
                    'product_id' => $productId,
                    'current_installation_url' => $currentUrl,
                    'new_installation_url' => $newUrl,
                    'action_type' => $actionType,
                    'owner' => $owner,
                    'base_url_install' => $baseUrlInstall,
                    'ip' => $ip,
                    'referer' => $referer,
                    'path' => $path,
                ]);

            $body = $response->body();
            $decoded = json_decode($body);

            if (!is_object($decoded)) {
                return [
                    'success' => false,
                    'message' => 'Invalid response. Please try again.',
                ];
            }

            if (isset($decoded->status) && $decoded->status === 'success') {
                return [
                    'success' => true,
                    'message' => $decoded->message ?? 'Uninstall success done, please reload the page.',
                ];
            }

            return [
                'success' => false,
                'message' => $decoded->message ?? 'Uninstall request failed.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Uninstall request failed. Please try again.',
            ];
        }
    }

    /**
     * 
     */
    public function secFileW(
        string $username,
        string $purchaseCode,
        string $installationUrl,
        string $version = '11.2',
        ?string $appKey = null
    ): bool {
        $dir = $this->blueimpPath();
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return false;
        }

        $existingFiles = [
            $this->blueimpPath('REST_API.json'),
            $this->blueimpPath('REST_API_I.json'),
            $this->blueimpPath('REST_API_UV.json'),
            $this->instPa(),
            $this->instUPa(),
        ];
        foreach ($existingFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $today = date('Y-m-d');
        $restApi = ['date' => $today];
        if (file_put_contents($this->blueimpPath('REST_API.json'), json_encode($restApi), LOCK_EX) === false) {
            return false;
        }

        $installationUrlStored = rtrim($installationUrl, '/') . '/';
        if (!str_starts_with($installationUrlStored, 'http://') && !str_starts_with($installationUrlStored, 'https://')) {
            $installationUrlStored = (request()->getScheme() ?: 'http') . '://' . ltrim($installationUrlStored, '/');
        }

        $restApiI = [
            'username' => $username,
            'purchase_code' => $purchaseCode,
            'installation_url' => str_rot13($installationUrlStored),
        ];
        if (file_put_contents($this->blueimpPath('REST_API_I.json'), json_encode($restApiI), LOCK_EX) === false) {
            return false;
        }

        $up_u = $this->getInstallationTypeFromEnv() === 'BD'
            ? 'uggc://qbbefbsg.pb/hcqngre/ovcbav/purpx_sbe_hcqngr.cuc'
            : 'uggc://qbbefbsg.pb/hcqngre/bss_cbf/purpx_sbe_hcqngr.cuc';
        $restApiUv = [
            'version' => $version,
            'url' => $up_u,
        ];
        if (file_put_contents($this->blueimpPath('REST_API_UV.json'), json_encode($restApiUv), LOCK_EX) === false) {
            return false;
        }

        $payload = [
            'url' => $installationUrlStored,
            'username' => $username,
            'purchase_code' => $purchaseCode,
        ];
        try {
            if ($appKey !== null && $appKey !== '') {
                $encrypted = $this->encryptWithKey(json_encode($payload), $appKey);
            } else {
                $encrypted = Crypt::encryptString(json_encode($payload));
            }
            if (file_put_contents($this->instPa(), $encrypted, LOCK_EX) === false) {
                return false;
            }
        } catch (\Throwable $e) {
            return false;
        }

        $payloadU = [
            'username' => $username,
            'purchase_code' => $purchaseCode,
            'installation_url' => $installationUrlStored,
        ];
        try {
            if ($appKey !== null && $appKey !== '') {
                $encryptedU = $this->encryptWithKey(json_encode($payloadU), $appKey);
            } else {
                $encryptedU = Crypt::encryptString(json_encode($payloadU));
            }
            if (file_put_contents($this->instUPa(), $encryptedU, LOCK_EX) === false) {
                return false;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    /**
     * 
     */
    private function encryptWithKey(string $value, string $appKey): string
    {
        $key = $appKey;
        if (Str::startsWith($key, 'base64:')) {
            $key = base64_decode(Str::after($key, 'base64:'), true);
            if ($key === false) {
                throw new \RuntimeException('Invalid base64 app key.');
            }
        }
        $cipher = config('app.cipher', 'AES-256-CBC');
        $encrypter = new Encrypter($key, $cipher);
        return $encrypter->encryptString($value);
    }

    /**
     * 
     */
    public function botPaFi(string $bPackageDetailsJson, string $appKey): bool
    {
        $data = json_decode($bPackageDetailsJson, true);
        if (!is_array($data)) {
            return false;
        }

        $ilt = isset($data['ilt']) ? (int) $data['ilt'] : 500;
        $olt = isset($data['olt']) ? (int) $data['olt'] : 1;


        $p = 'Silver';
        if ($olt === 0 || $ilt === 0) {
            $p = 'Diamond';
        } elseif ($olt >= 1 && $ilt <= 500) {
            $p = 'Silver';
        } elseif ($olt >= 1 && $ilt <= 2000) {
            $p = 'Gold';
        }

        $payload = [
            'p'  => $p,
            'ol' => ($olt === 0 ? 'Unlimited' : (string) $olt),
            'pl' => ($ilt === 0 ? 'Unlimited' : (string) $ilt),
        ];

        $dir = $this->blueimpPath();
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return false;
        }

        try {
            $encrypted = $this->encryptWithKey(json_encode($payload), $appKey);
            return file_put_contents($this->blueimpPath('B.json'), $encrypted, LOCK_EX) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 
     *
     * @return array{ok: bool, code: int|null}
     */
    public function cheSecInt(): array
    {
        $lp = env('LP', '');
        if (strtoupper((string) $lp) === 'BD') {
            $bPath = $this->blueimpPath('B.json');
            if (!is_file($bPath) || !is_readable($bPath)) {
                return ['ok' => false, 'code' => $this->checkSumC(0x8BED)];
            }
            $raw = @file_get_contents($bPath);
            if ($raw === false || trim($raw) === '') {
                return ['ok' => false, 'code' => $this->checkSumC(0x8BED)];
            }
            try {
                $decrypted = Crypt::decryptString(trim($raw));
                $data = json_decode($decrypted, true);
                if (!is_array($data) || !isset($data['p'], $data['ol'], $data['pl'])) {
                    return ['ok' => false, 'code' => $this->checkSumC(0x8BED)];
                }
            } catch (\Throwable $e) {
                return ['ok' => false, 'code' => $this->checkSumC(0x8BED)];
            }
        }

        $uPath = $this->instUPa();
        if (!is_file($uPath) || !is_readable($uPath)) {
            return ['ok' => false, 'code' => $this->checkSumC(0x8BEF)];
        }
        $rawU = @file_get_contents($uPath);
        if ($rawU === false || trim($rawU) === '') {
            return ['ok' => false, 'code' => $this->checkSumC(0x8BEF)];
        }
        try {
            $decryptedU = Crypt::decryptString(trim($rawU));
            $payloadU = json_decode($decryptedU, true);
            $storedUrl = is_array($payloadU) ? ($payloadU['installation_url'] ?? '') : '';
            if ($storedUrl === '') {
                return ['ok' => false, 'code' => $this->checkSumC(0x8BEF)];
            }
            $storedNormalized = $this->normalizeInstallationUrl($storedUrl);
            $currentNormalized = $this->normalizeCurrentRequestUrl();
            if ($storedNormalized !== $currentNormalized) {
                return ['ok' => false, 'code' => $this->checkSumC(0x8BEF)];
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'code' => $this->checkSumC(0x8BEF)];
        }

        $restApiPath = $this->blueimpPath('REST_API.json');
        if (!is_file($restApiPath)) {
            return ['ok' => false, 'code' => $this->checkSumC(0x8BEE)];
        }
        $content = file_get_contents($restApiPath);
        $jsonData = json_decode($content, true);
        $installationDate = $jsonData['date'] ?? null;
        $metaDate = date('Y-m-d', filectime($restApiPath));

        if ($installationDate !== $metaDate) {
            return ['ok' => false, 'code' => $this->checkSumC(0x8BEE)];
        }

        $restApiIPath = $this->blueimpPath('REST_API_I.json');
        if (!is_file($restApiIPath)) {
            return ['ok' => false, 'code' => $this->checkSumC(0x8BEF)];
        }
        $contentI = file_get_contents($restApiIPath);
        $jsonDataI = json_decode($contentI, true);
        if (!is_array($jsonDataI) || empty($jsonDataI['installation_url'])) {
            return ['ok' => false, 'code' => $this->checkSumC(0x8BEF)];
        }
        $storedUrl = str_rot13($jsonDataI['installation_url']);
        $storedNormalized = $this->normalizeInstallationUrl($storedUrl);
        $currentNormalized = $this->normalizeCurrentRequestUrl();

        if ($storedNormalized !== $currentNormalized) {
            return ['ok' => false, 'code' => $this->checkSumC(0x8BEF)];
        }

        $encPath = $this->instPa();
        if (!is_file($encPath)) {
            return ['ok' => false, 'code' => $this->checkSumC(0x8BEF)];
        }

        try {
            $raw = trim(file_get_contents($encPath));
            $decrypted = Crypt::decryptString($raw);
            $payload = json_decode($decrypted, true);
            $encryptedUrl = $payload['url'] ?? '';
            if ($encryptedUrl === '') {
                return ['ok' => false, 'code' => $this->checkSumC(0x8BEF)];
            }
            $encNormalized = $this->normalizeInstallationUrl($encryptedUrl);
            if ($encNormalized !== $currentNormalized) {
                return ['ok' => false, 'code' => $this->checkSumC(0x8BEF)];
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'code' => $this->checkSumC(0x8BEF)];
        }


        return ['ok' => true, 'code' => null];
    }

    /**
     * 
     */
    private function normalizeInstallationUrl(string $url): string
    {
        $url = str_replace(['https://', 'http://', 'www.'], '', $url);
        $url = rtrim($url, '/');
        $url = strtolower($url);
        $parts = explode('/', $url);
        $host = $parts[0] ?? $url;
        $path = isset($parts[1]) ? implode('/', array_slice($parts, 1)) : '';
        return $path !== '' ? $host . '/' . $path : $host;
    }

    /**
     * 
     */
    private function normalizeCurrentRequestUrl(): string
    {
        $url = rtrim(request()->getSchemeAndHttpHost() . request()->getBasePath(), '/');
        if ($url === '') {
            $url = rtrim((string) config('app.url', ''), '/');
        }
        return $this->normalizeInstallationUrl($url);
    }

    /**
     * 
     */
    public function remSecFi(): void
    {
        $files = [
            $this->blueimpPath('REST_API.json'),
            $this->blueimpPath('REST_API_I.json'),
            $this->blueimpPath('REST_API_UV.json'),
            $this->blueimpPath('B.json'),
            $this->instPa(),
            $this->instUPa(),
        ];
        foreach ($files as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $dir = $this->blueimpPath();
        if (is_dir($dir) && count(glob($dir . '/*')) === 0) {
            @rmdir($dir);
        }
    }

    /**
     * 
     */
    public function removeEnvFile(): bool
    {
        $envPath = base_path('.env');
        if (!is_file($envPath)) {
            return true;
        }
        return @unlink($envPath);
    }

    /**
     * After license uninstall: reset .env from .env.example with a new APP_KEY so the app boots
     * and the installer can run again (session flash is unreliable across key rotation).
     */
    public function prepareEnvAfterUninstall(): bool
    {
        $examplePath = base_path('.env.example');
        $envPath = base_path('.env');
        $existingLp = '';
        if (is_file($envPath)) {
            $currentEnv = @file_get_contents($envPath);
            if ($currentEnv !== false && preg_match('/^\s*LP\s*=\s*["\']?([^"\'\r\n]*)["\']?\s*$/mi', $currentEnv, $m)) {
                $existingLp = trim((string) $m[1]);
            }
        }
        if (!is_file($examplePath)) {
            return $this->removeEnvFile();
        }
        $content = file_get_contents($examplePath);
        if ($content === false) {
            return false;
        }
        $appKey = 'base64:' . $this->generateAppKey();
        if (preg_match('/^\s*APP_KEY\s*=/m', $content)) {
            $content = preg_replace('/^\s*APP_KEY\s*=.*/m', 'APP_KEY=' . $appKey, $content);
        } else {
            $content = 'APP_KEY=' . $appKey . "\n" . $content;
        }
        if (preg_match('/^\s*APP_INSTALLED\s*=/m', $content)) {
            $content = preg_replace('/^\s*APP_INSTALLED\s*=.*/m', 'APP_INSTALLED=false', $content);
        } else {
            $content = rtrim($content) . "\nAPP_INSTALLED=false\n";
        }
        if ($existingLp !== '') {
            if (preg_match('/^\s*LP\s*=/m', $content)) {
                $content = preg_replace('/^\s*LP\s*=.*/m', 'LP=' . $existingLp, $content);
            } else {
                $content = rtrim($content) . "\nLP=" . $existingLp . "\n";
            }
        }
        $written = file_put_contents($envPath, $content, LOCK_EX) !== false;
        $this->clearInstallDbState();

        return $written;
    }

    /**
     * 
     */
    public function setInstalled(): bool
    {
        $envPath = base_path('.env');
        if (!file_exists($envPath)) {
            return false;
        }
        $content = file_get_contents($envPath);
        $content = preg_replace('/^APP_INSTALLED=.*/m', 'APP_INSTALLED=true', $content);
        if (strpos($content, 'APP_INSTALLED=') === false) {
            $content .= "\nAPP_INSTALLED=true\n";
        }
        return file_put_contents($envPath, $content) !== false;
    }
}
