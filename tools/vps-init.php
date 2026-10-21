<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!config('recovery.enabled')) throw new RuntimeException('ALLOW_RECOVERED_SCHEMA=true is required');
if (Illuminate\Support\Facades\DB::table('users')->exists() || Illuminate\Support\Facades\DB::table('companies')->exists()) {
    throw new RuntimeException('Fresh database required; refusing to modify an existing installation.');
}
$credentialsPath = $argv[1] ?? '/root/inventory-admin.txt';
(function () use ($app, $credentialsPath) {
    (new Database\Seeders\RolePermissionSeeder)->run();
    (new Database\Seeders\StateSeeder)->run();
    require __DIR__.'/local-setup.php';
    $password = bin2hex(random_bytes(12));
    $user = App\Models\User::where('email', 'admin@doorsoft.co')->firstOrFail();
    $user->password = Illuminate\Support\Facades\Hash::make($password);
    $user->save();
    Illuminate\Support\Facades\DB::table('outlets')->where('company_id', 1)->update(['user_id' => $user->id]);
    Illuminate\Support\Facades\DB::table('companies')->where('id', 1)->update(['user_id' => $user->id]);
    file_put_contents($credentialsPath, "Email: admin@doorsoft.co\nPassword: {$password}\n");
    chmod($credentialsPath, 0600);
})();
$path = base_path('.env');
$content = file_get_contents($path);
$content = preg_replace('/^APP_INSTALLED=.*$/m', 'APP_INSTALLED=true', $content);
file_put_contents($path, $content);
echo "Installation ready. Credentials: {$credentialsPath}\n";
