<?php

namespace App\Providers;

use App\Http\ResponseFactory;
use App\Services\InsTraService;
use Illuminate\Contracts\Routing\ResponseFactory as ResponseFactoryContract;
use Illuminate\Contracts\View\Factory as ViewFactoryContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // After update, composer autoload may lag behind new Modules/* until vendor.zip is replaced.
        $this->registerModulesAutoloadFallback();
        $this->app->scoped(\App\Services\MenuModuleService::class);

        $this->app->singleton(ResponseFactoryContract::class, function ($app) {
            return new ResponseFactory(
                $app->make(ViewFactoryContract::class),
                $app->make('redirect')
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->ensureModuleViewNamespaces();

        View::composer([
            'install.layout',
            'install.welcome',
            'install.environment',
            'install.purchase',
            'install.database',
            'install.complete',
            'install.uninstall',
            'install.uninstall_complete',
            'install.partials.steps',
        ], function ($view) {
            $lpIsBd = (defined('LP') && strtoupper((string) LP) === 'BD');
            if (!$lpIsBd) {
                foreach ([base_path('.env'), base_path('.env.example')] as $path) {
                    if (is_file($path)) {
                        $content = @file_get_contents($path);
                        if ($content !== false
                            && preg_match('/^\s*LP\s*=\s*["\']?([^"\'\r\n]*)["\']?\s*$/mi', $content, $m)
                            && strtoupper(trim((string) $m[1])) === 'BD') {
                            $lpIsBd = true;
                            break;
                        }
                    }
                }
            }
            $view->with('lpIsBd', $lpIsBd);
        });
        if (!$this->app->runningInConsole()) {
            $this->app->make(InsTraService::class)->run(request());
        }

        // Configure Razorpay SSL for development environments
        if (file_exists(app_path('Helpers/RazorpaySslHelper.php'))) {
            require_once app_path('Helpers/RazorpaySslHelper.php');
            if (function_exists('configureRazorpaySsl')) {
                configureRazorpaySsl();
            }
        }
        
        // Gate::before(function ($user, $ability) {
        //     if ($user->hasRole('Super-Admin')) {
        //         return true;
        //     }
        // });
    }

    /**
     * Load Modules\{Name}\... from Modules/{Name}/app when Composer PSR-4 is stale.
     */
    private function registerModulesAutoloadFallback(): void
    {
        spl_autoload_register(static function (string $class): void {
            if (! str_starts_with($class, 'Modules\\')) {
                return;
            }

            $parts = explode('\\', $class);
            if (count($parts) < 3) {
                return;
            }

            $module = $parts[1];
            $relative = implode(DIRECTORY_SEPARATOR, array_slice($parts, 2)).'.php';
            $file = base_path('Modules'.DIRECTORY_SEPARATOR.$module.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.$relative);

            if (is_file($file)) {
                require_once $file;
            }
        });
    }

    /**
     * Register module view namespaces if the provider did not (module disabled / boot skipped).
     */
    private function ensureModuleViewNamespaces(): void
    {
        $statusesPath = base_path('modules_statuses.json');
        $statuses = is_file($statusesPath)
            ? (json_decode((string) file_get_contents($statusesPath), true) ?: [])
            : [];

        foreach ($statuses as $name => $enabled) {
            if (! $enabled) {
                continue;
            }

            $hint = strtolower((string) $name);
            $viewsPath = base_path('Modules/'.$name.'/resources/views');
            if (! is_dir($viewsPath)) {
                continue;
            }

            $hints = View::getFinder()->getHints();
            if (! isset($hints[$hint])) {
                View::addNamespace($hint, $viewsPath);
            }
        }
    }
}
