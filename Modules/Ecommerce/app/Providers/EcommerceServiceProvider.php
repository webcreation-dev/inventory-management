<?php

namespace Modules\Ecommerce\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Ecommerce\Services\CartService;
use Modules\Ecommerce\Services\CustomerAuthService;
use Modules\Ecommerce\Services\EcommerceContentService;
use Modules\Stock\Models\ItemCategory;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class EcommerceServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Ecommerce';

    protected string $nameLower = 'ecommerce';

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
        $this->registerViewComposers();
    }

    protected function registerViewComposers(): void
    {
        View::composer('ecommerce::layouts.app', function ($view) {
            try {
                $content = app(EcommerceContentService::class);
                $branding = $content->branding();
                $companyId = $content->companyId();
            } catch (\Throwable) {
                $branding = [
                    'siteName' => 'NexaShop',
                    'siteLogo' => null,
                    'footerLogo' => null,
                    'siteFavicon' => null,
                    'brandColor' => \Modules\Ecommerce\Support\BrandColor::DEFAULT,
                    'brandPalette' => \Modules\Ecommerce\Support\BrandColor::palette(null),
                    'storefrontLanguages' => ['en'],
                    'contactPhone' => null,
                    'contactEmail' => null,
                    'contactAddress' => null,
                    'footerDescription' => null,
                    'appLink' => null,
                    'showAdminLoginUrl' => false,
                    'socialFacebook' => null,
                    'socialInstagram' => null,
                    'socialTiktok' => null,
                    'socialLinkedin' => null,
                ];
                $companyId = 0;
            }

            $allowedLocales = $branding['storefrontLanguages'] ?? ['en'];
            $activeLocale = \Illuminate\Support\Facades\Session::get('locale', config('app.locale'));
            if (! in_array($activeLocale, $allowedLocales, true)) {
                $activeLocale = $allowedLocales[0] ?? 'en';
                \Illuminate\Support\Facades\Session::put('locale', $activeLocale);
                \Illuminate\Support\Facades\App::setLocale($activeLocale);
            }

            $cart = app(CartService::class)->payload();
            $customer = app(CustomerAuthService::class)->customer();
            $wishlistCount = 0;
            $wishlistIds = [];
            try {
                $wishlist = app(\Modules\Ecommerce\Services\WishlistService::class);
                $wishlistCount = $wishlist->count();
                $wishlistIds = $wishlist->ids();
            } catch (\Throwable) {
                $wishlistCount = 0;
                $wishlistIds = [];
            }

            $navCategories = collect();
            try {
                $navCategories = ItemCategory::query()
                    ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
                    ->where('del_status', 'Live')
                    ->where('show_in_header', true)
                    ->with(['subCategories' => function ($q) use ($companyId) {
                        $q->live()
                            ->when($companyId > 0, fn ($sq) => $sq->where('company_id', $companyId))
                            ->whereHas('items', function ($iq) use ($companyId) {
                                $iq->where('del_status', 'Live')
                                    ->when($companyId > 0, fn ($itemQ) => $itemQ->where('company_id', $companyId))
                                    ->where(function ($statusQ) {
                                        $statusQ->whereNull('enable_disable_status')
                                            ->orWhere('enable_disable_status', '')
                                            ->orWhere('enable_disable_status', 'Enable');
                                    })
                                    ->where(function ($parentQ) {
                                        $parentQ->whereNull('parent_id')->orWhere('parent_id', 0);
                                    });
                            })
                            ->orderBy('name');
                    }])
                    ->orderByRaw('COALESCE(header_sort_id, 999999) ASC')
                    ->orderBy('sort_id')
                    ->orderBy('name')
                    ->get(['id', 'name']);
            } catch (\Throwable) {
                $navCategories = collect();
            }

            $moneySettings = companyAmountSettings();
            $view->with($branding + [
                'cartItems' => $cart['items'],
                'cartCount' => $cart['count'],
                'cartSubtotal' => $cart['subtotal'],
                'cartTax' => $cart['tax'],
                'cartTaxInclusive' => $cart['tax_inclusive'] ?? 0,
                'cartShipping' => $cart['shipping'],
                'cartTotal' => $cart['total'],
                'navCategories' => $navCategories,
                'ecommerceCustomer' => $customer,
                'isEcommerceCustomer' => (bool) $customer,
                'isBackendAdmin' => \Illuminate\Support\Facades\Auth::guard('web')->check(),
                'wishlistCount' => $wishlistCount,
                'wishlistIds' => $wishlistIds,
                'moneySettings' => $moneySettings,
            ]);
        });
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/' . $this->nameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);
            $this->loadJsonTranslationsFrom(module_path($this->name, 'lang'));
        }
    }

    protected function registerConfig(): void
    {
        $configPath = module_path($this->name, config('modules.paths.generator.config.path'));

        if (is_dir($configPath)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
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

    public function provides(): array
    {
        return [];
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
