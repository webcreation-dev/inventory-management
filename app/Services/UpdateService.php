<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Handles update flow: version check (CodeCanyon/LP=BD), update verification,
 * download, and install. Uses bootstrap/blueimp/REST_API_UV.json for current version.
 */
class UpdateService
{

    public const VENDOR_DOWNLOAD_URL = 'https://doorsoft.co/updater/off_pos/download-vendor/vendor.zip';

    public const VENDOR_PENDING_FLAG = 'framework/update_vendor_pending';

    private function blueimpPath(string $file = ''): string
    {
        return base_path('bootstrap/blueimp/' . ltrim($file, '/'));
    }

    public function vendorPendingPath(): string
    {
        return storage_path(self::VENDOR_PENDING_FLAG);
    }

    /**
     * @return array{at?: string, message?: string, disabled_modules?: array<int, string>}|null
     */
    public function getVendorPending(): ?array
    {
        $path = $this->vendorPendingPath();
        if (! is_file($path)) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    public function isVendorPending(): bool
    {
        return is_file($this->vendorPendingPath());
    }

    /**
     * Mark update as waiting for vendor.zip replace.
     * Keeps modules enabled (AppServiceProvider autoload fallback loads new module classes).
     *
     * @return array<int, string>
     */
    public function markVendorPending(string $message = ''): array
    {
        // Repair modules that an older updater may have disabled
        $this->ensurePresentModulesEnabled(['Ecommerce', 'PrintManager']);

        $payload = [
            'at' => date('c'),
            'message' => $message !== '' ? $message : __('Installed successfully.'),
            'disabled_modules' => [],
        ];

        @file_put_contents($this->vendorPendingPath(), json_encode($payload), LOCK_EX);

        return [];
    }

    /**
     * Clear vendor-pending flag and ensure known modules stay enabled.
     */
    public function clearVendorPendingAndRestoreModules(): void
    {
        $pending = $this->getVendorPending();
        $toRestore = $pending['disabled_modules'] ?? [];

        if (is_array($toRestore) && $toRestore !== []) {
            $this->setModulesEnabled($toRestore, true);
        }

        $this->ensurePresentModulesEnabled(['Ecommerce', 'PrintManager']);

        $path = $this->vendorPendingPath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Enable modules that exist on disk (repairs prior disable-for-vendor logic).
     *
     * @param  array<int, string>  $moduleNames
     */
    public function ensurePresentModulesEnabled(array $moduleNames): void
    {
        $toEnable = [];
        foreach ($moduleNames as $name) {
            if (is_dir(base_path('Modules/'.$name)) && is_file(base_path('Modules/'.$name.'/module.json'))) {
                $toEnable[] = $name;
            }
        }

        if ($toEnable !== []) {
            $this->setModulesEnabled($toEnable, true);
        }
    }

    /**
     * @param  array<int, string>  $moduleNames
     */
    public function setModulesEnabled(array $moduleNames, bool $enabled): void
    {
        $statusesPath = base_path('modules_statuses.json');
        if (! is_file($statusesPath) || $moduleNames === []) {
            return;
        }

        $statuses = json_decode((string) file_get_contents($statusesPath), true);
        if (! is_array($statuses)) {
            return;
        }

        foreach ($moduleNames as $name) {
            if (array_key_exists($name, $statuses)) {
                $statuses[$name] = $enabled;
            }
        }

        file_put_contents(
            $statusesPath,
            json_encode($statuses, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
            LOCK_EX
        );
    }

    /**
     * Get current system version from REST_API_UV.json.
     */
    public function getSystemVersion(): string
    {
        $path = $this->blueimpPath('REST_API_UV.json');
        if (!is_file($path)) {
            return '0';
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            return '0';
        }
        $data = json_decode($content, true);
        return (string) ($data['version'] ?? '0');
    }

    /**
     * Get update info: fetch remote check_for_update URL from REST_API_UV and return version, url, whats_new.
     * whats_new from API may be an array of strings; it is normalized to a single newline-separated string.
     *
     * @return array{version: string, url: string, whats_new: string|null}|null
     */
    public function getUpdateInfo(): ?array
    {
        $path = $this->blueimpPath('REST_API_UV.json');
        if (!is_file($path)) {
            return null;
        }
        $content = @file_get_contents($path);

        if ($content === false) {
            return null;
        }
        $data = json_decode($content, true);
        $checkUrl = $data['url'] ?? null;
        if (!$checkUrl || !is_string($checkUrl)) {
            return null;
        }
        $decodedUrl = str_rot13($checkUrl);
        if (!str_starts_with($decodedUrl, 'http://') && !str_starts_with($decodedUrl, 'https://')) {
            return null;
        }
        try {
            $response = Http::withoutVerifying()->timeout(15)->get($decodedUrl);
            $body = $response->body();
            $obj = json_decode($body);
            if (!is_object($obj)) {
                return null;
            }
            $whatsNew = null;
            if (isset($obj->whats_new)) {
                $whatsNew = is_array($obj->whats_new)
                    ? implode("\n", array_map('strval', $obj->whats_new))
                    : (string) $obj->whats_new;
            }
            return [
                'version' => (string) ($obj->version ?? '0'),
                'url' => (string) ($obj->url ?? ''),
                'whats_new' => $whatsNew,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Verify update with Doorsoft API (checking_update). Sets session on success.
     *
     * @return array{success: bool, message: string}
     */
    public function verifyUpdateCheck(
        string $username,
        string $purchaseCode,
        string $installationUrl,
        string $ip,
        string $referer,
        string $path
    ): array {
        $owner = 'doorsoftco';
        $productSource = $this->getProductIdAndSource();
        $source = $productSource['source'];
        $productId = $productSource['product_id'];
        $url = str_rot13('uggcf://qbbefbsg.pb/qfy/Inyvqngvba/purpxvat_hcqngr/');


        try {
            $response = Http::asForm()
                ->withoutVerifying()
                ->timeout(60)
                ->post($url, [
                    'username' => $username,
                    'purchase_code' => $purchaseCode,
                    'source' => $source,
                    'product_id' => $productId,
                    'owner' => $owner,
                    'installation_url' => $installationUrl,
                    'ip' => $ip,
                    'referer' => $referer,
                    'path' => $path,
                ]);

            $body = $response->body();
            $decoded = json_decode($body);

            if (!is_object($decoded)) {
                return [
                    'success' => false,
                    'message' => strip_tags($body) ?: __('Invalid response from update server.'),
                ];
            }

            if (isset($decoded->status) && $decoded->status === 'success') {
                return [
                    'success' => true,
                    'message' => $decoded->message ?? __('Verification success'),
                ];
            }

            return [
                'success' => false,
                'message' => $decoded->message ?? __('Verification failed.'),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => __('Request failed: ') . $e->getMessage(),
            ];
        }
    }

    private function getInstallationTypeFromEnv(): string
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

    private function getProductIdAndSource(): array
    {
        if ($this->getInstallationTypeFromEnv() === 'BD') {
            return ['product_id' => '133347', 'source' => 'Bangladesh'];
        }
        return ['product_id' => '24326862', 'source' => 'CodeCanyon'];
    }

    /**
     * Download file from URL to local path.
     */
    public function downloadFile(string $url, string $localPath): bool
    {
        try {
            $content = Http::withoutVerifying()->timeout(120)->get($url)->body();
            if ($content === '') {
                return false;
            }
            return file_put_contents($localPath, $content, LOCK_EX) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Run update: extract zip already in $zipPath to _temp, then caller calls installUpdate().
     */
    public function extractUpdateZip(string $zipPath): bool
    {
        $tempDir = base_path('_temp');
        if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true)) {
            return false;
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return false;
        }
        $zip->extractTo($tempDir);
        $zip->close();
        return true;
    }

    /**
     * Install update from _temp: run installer.json (delete, sql), recurse copy, update version file.
     * If the zip had a single root folder, use that folder as content root so files land in project root.
     *
     * @return array{success: bool, message: string}
     */
    public function installUpdate(string $newVersion): array
    {
        $tempDir = base_path('_temp');
        $src = $this->resolveUpdateContentRoot($tempDir);
        $dst = base_path();
        $installerPath = $src . '/installer.json';

        if (!is_file($installerPath)) {
            return ['success' => false, 'message' => __('Package installer missing.')];
        }

        $installer = json_decode(file_get_contents($installerPath), true);
        if (!is_array($installer)) {
            return ['success' => false, 'message' => __('Invalid installer.json.')];
        }

        if (!empty($installer['delete'])) {
            foreach ($installer['delete'] as $filePath) {
                if ($filePath && is_file(base_path($filePath))) {
                    @unlink(base_path($filePath));
                }
            }
        }

        if (!empty($installer['sql'])) {
            foreach ($installer['sql'] as $query) {
                if ($query) {
                    try {
                        DB::unprepared($query);
                    } catch (\Throwable $e) {
                        // log but continue
                    }
                }
            }
        }

        $this->recurseCopy($src, $dst);

        // Remove _temp and build.zip (delete tempDir, not src, in case src was a subdir)
        File::deleteDirectory($tempDir);
        $buildZip = base_path('build.zip');
        if (is_file($buildZip)) {
            @unlink($buildZip);
        }

        // Update REST_API_UV.json with new version
        $uvPath = $this->blueimpPath('REST_API_UV.json');
        if (is_file($uvPath)) {
            $uv = json_decode(file_get_contents($uvPath), true) ?: [];
            $uv['version'] = $newVersion;
            file_put_contents($uvPath, json_encode($uv), LOCK_EX);
        }

        $message = __('Installed successfully.');
        // Bypass Module provider class-not-found until vendor.zip is replaced
        $this->markVendorPending($message);

        return ['success' => true, 'message' => $message];
    }

    /**
     * Resolve the content root inside _temp. If the zip had a single root directory
     * (e.g. _temp/off_pos_laravel/), use that so files are copied to project root, not into a nested folder.
     */
    protected function resolveUpdateContentRoot(string $tempDir): string
    {
        if (!is_dir($tempDir)) {
            return $tempDir;
        }
        $entries = [];
        $dir = opendir($tempDir);
        while (false !== ($entry = readdir($dir))) {
            if ($entry !== '.' && $entry !== '..') {
                $entries[] = $entry;
            }
        }
        closedir($dir);

        if (count($entries) === 1 && is_dir($tempDir . '/' . $entries[0])) {
            return $tempDir . '/' . $entries[0];
        }

        return $tempDir;
    }

    protected function recurseCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        @mkdir($dst, 0755, true);
        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..' || $file === 'installer.json') {
                continue;
            }
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;
            if (is_dir($srcPath)) {
                $this->recurseCopy($srcPath, $dstPath);
            } else {
                @copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }

    /**
     * LP=BD: Verify upgrade license with server (username, purchase_code, upgrade_code).
     * Does not download or execute any remote code.
     *
     * @return array{success: bool, message: string}
     */
    public function verifyUpgradeLicense(
        string $username,
        string $purchaseCode,
        string $upgradeCode,
        string $installationUrl,
        string $ip,
        string $referer,
        string $path
    ): array {
        $owner = 'doorsoftco';
        $productSource = $this->getProductIdAndSource();
        $source = $productSource['source'];
        $productId = $productSource['product_id'];
        $url = str_rot13('uggcf://qbbefbsg.pb/qfy/Inyvqngvba/hctenqr_yvprafr/');

        try {
            $response = Http::asForm()
                ->withoutVerifying()
                ->timeout(60)
                ->post($url, [
                    'username' => $username,
                    'purchase_code' => $purchaseCode,
                    'upgrade_code' => $upgradeCode,
                    'source' => $source,
                    'product_id' => $productId,
                    'owner' => $owner,
                    'installation_url' => $installationUrl,
                    'base_url_install' => $installationUrl,
                    'ip' => $ip,
                    'referer' => $referer,
                    'path' => $path,
                ]);

            $body = $response->body();
            $decoded = json_decode($body);

            if (!is_object($decoded)) {
                return [
                    'success' => false,
                    'message' => strip_tags($body) ?: __('Invalid response from server.'),
                ];
            }

            if (isset($decoded->status) && $decoded->status === 'success') {
                return [
                    'success' => true,
                    'message' => $decoded->message ?? __('Update success'),
                ];
            }

            return [
                'success' => false,
                'message' => $decoded->message ?? __('Something went wrong'),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => __('Request failed: ') . $e->getMessage(),
            ];
        }
    }
}
