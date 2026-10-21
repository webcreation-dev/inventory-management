<?php

namespace Modules\PrintManager\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Modules\PrintManager\Contracts\PrinterSettingRepositoryInterface;
use Modules\PrintManager\Repositories\PrinterSettingRepository;
use Modules\PrintManager\Services\BrowserPrintService;
use Modules\PrintManager\Services\ESCPosService;
use Modules\PrintManager\Services\LocalAgentPrintService;
use Modules\PrintManager\Services\PrintLogService;
use Modules\PrintManager\Services\PrinterService;
use Modules\PrintManager\Services\QZTrayService;
use Modules\PrintManager\Services\QzSecurityService;
use Modules\PrintManager\Services\ReceiptService;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PrintManagerServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'PrintManager';

    protected string $nameLower = 'printmanager';

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        $this->app->bind(PrinterSettingRepositoryInterface::class, PrinterSettingRepository::class);
        $this->app->singleton(ESCPosService::class);
        $this->app->singleton(QzSecurityService::class);
        $this->app->singleton(PrintLogService::class);
        $this->app->singleton(ReceiptService::class);
        $this->app->singleton(BrowserPrintService::class);
        $this->app->singleton(QZTrayService::class);
        $this->app->singleton(LocalAgentPrintService::class);
        $this->app->singleton(PrinterService::class);
    }

    protected function registerConfig(): void
    {
        $configPath = module_path($this->name, config('modules.paths.generator.config.path'));

        if (!is_dir($configPath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $config = str_replace($configPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $configKey = str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $config);
            $segments = explode('.', $this->nameLower . '.' . $configKey);

            $normalized = [];
            foreach ($segments as $segment) {
                if (end($normalized) !== $segment) {
                    $normalized[] = $segment;
                }
            }

            $key = ($config === 'config.php') ? $this->nameLower : implode('.', $normalized);

            $this->publishes([$file->getPathname() => config_path($config)], 'config');
            $this->mergeConfigFromFile($file->getPathname(), $key);
        }
    }

    protected function mergeConfigFromFile(string $path, string $key): void
    {
        $existing = config($key, []);
        $moduleConfig = require $path;

        config([$key => array_replace_recursive($existing, $moduleConfig)]);
    }

    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/' . $this->nameLower);
        $sourcePath = module_path($this->name, 'resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->nameLower . '-module-views']);
        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->nameLower);
        Blade::componentNamespace(config('modules.namespace') . '\\' . $this->name . '\\View\\Components', $this->nameLower);
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];

        foreach (config('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->nameLower)) {
                $paths[] = $path . '/modules/' . $this->nameLower;
            }
        }

        return $paths;
    }
}
