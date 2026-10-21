<?php
// Checks use a rolled-back transaction: no changes to the company's settings.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!$app->environment('local')) throw new RuntimeException('Run against the local development database.');
use App\Services\ModuleUsageService;
use App\Services\MenuModuleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
DB::beginTransaction();
try {
    $admin = App\Models\User::where('email', 'admin@doorsoft.co')->firstOrFail();
    Auth::setUser($admin);
    session()->put('company.company_id', $admin->company_id);
    $usage = app(ModuleUsageService::class);
    $before = $usage->all();
    foreach (config('menu_modules') as $key => $definition) {
        $usage->save([$key => false], companyId: $admin->company_id);
        $modules = new MenuModuleService;
        if ($modules->enabled($key)) throw new RuntimeException("Disable failed: {$key}");
        foreach ($definition['prefixes'] as $prefix) {
            if ($modules->forPath($prefix) !== $key) throw new RuntimeException("Route mapping mismatch: {$prefix}");
        }
        $usage->save([$key => true], companyId: $admin->company_id);
        if (!(new MenuModuleService)->enabled($key)) throw new RuntimeException("Enable failed: {$key}");
        $usage->save($before, companyId: $admin->company_id);
    }
    $employee = App\Models\User::create(['name'=>'Temporary module test', 'email'=>'module-test@example.invalid', 'password'=>bcrypt(bin2hex(random_bytes(12))), 'company_id'=>$admin->company_id]);
    Auth::setUser($employee);
    try {
        app(Modules\Configuration\Http\Controllers\ModuleUsageController::class)->index();
        throw new RuntimeException('Employee unexpectedly accessed module administration.');
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
        if ($e->getStatusCode() !== 403) throw $e;
    }
    echo "All menu toggles, route mappings and administrator restriction: OK\n";
} finally {
    DB::rollBack();
    $usage->forgetSession();
    Auth::forgetUser();
}
