<?php

use App\Http\Controllers\DBBController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\UpdateController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

/*
|--------------------------------------------------------------------------
| LP favicon/logo image (when LP=BD) - served as PNG so it always displays
|--------------------------------------------------------------------------
*/
Route::get('lp-favicon-logo.png', function () {
    if (!defined('LP') || LP !== 'BD' || !defined('LP_BD_FAVICON_LOGO_DATA_URI')) {
        abort(404);
    }
    $dataUri = LP_BD_FAVICON_LOGO_DATA_URI;
    if (strpos($dataUri, 'base64,') === false) {
        abort(404);
    }
    $base64 = substr($dataUri, strpos($dataUri, 'base64,') + 7);
    $binary = base64_decode($base64, true);
    if ($binary === false) {
        abort(404);
    }
    return response($binary, 200, [
        'Content-Type' => 'image/png',
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->name('lp.favicon_logo');

/*
|--------------------------------------------------------------------------
| Installer routes (when app is not installed)
|--------------------------------------------------------------------------
*/
Route::middleware('redirect.if.installed')
    ->prefix('install')
    ->name('install.')
    ->group(function () {
        Route::get('/', [InstallController::class, 'welcome'])->name('welcome');
        Route::get('/environment', [InstallController::class, 'environment'])->name('environment');
        Route::get('/purchase', [InstallController::class, 'purchase'])->name('purchase');
        Route::post('/purchase', [InstallController::class, 'verPur'])->name('purchase.verify');
        Route::get('/business-type', [InstallController::class, 'businessType'])->name('business-type');
        Route::get('/business-type/features', [InstallController::class, 'businessTypeFeatures'])->name('business-type.features');
        Route::post('/business-type', [InstallController::class, 'storeBusinessType'])->name('business-type.store');
        Route::get('/database', [InstallController::class, 'database'])->name('database');
        Route::post('/database', [InstallController::class, 'storeDatabase'])->name('database.store');
        Route::get('/complete', [InstallController::class, 'complete'])->name('complete');
        Route::get('/uninstall-complete', [InstallController::class, 'uninstallComplete'])->name('uninstall_complete');
    });

/*
|--------------------------------------------------------------------------
| Application routes (only when app is installed)
|--------------------------------------------------------------------------
*/
Route::middleware(['redirect.if.not.installed'])->group(function () {
    // PWA (public, no auth required)
    Route::get('pwa/manifest.json', [PwaController::class, 'manifest'])->name('pwa.manifest');
    Route::get('pwa/serviceworker.js', [PwaController::class, 'serviceWorker'])->name('pwa.serviceworker');

    Route::get('i/s/{token}', [\Modules\Sale\Http\Controllers\PublicInvoiceController::class, 'showShort'])
        ->name('invoice.public.short');
    Route::get('i/{token}', [\Modules\Sale\Http\Controllers\PublicInvoiceController::class, 'show'])
        ->name('invoice.public.show');

    require __DIR__.'/auth.php';
    require __DIR__.'/backend.php';

    // Root URL:
    // - EC=true + Ecommerce module enabled → ecommerce storefront home
    // - IS=true → SaaS landing (when module present)
    // - otherwise → login
    $ecommerceModuleEnabled = false;
    $modulesStatusesPath = base_path('modules_statuses.json');
    if (is_file($modulesStatusesPath)) {
        $modulesStatuses = json_decode((string) file_get_contents($modulesStatusesPath), true);
        $ecommerceModuleEnabled = (bool) ($modulesStatuses['Ecommerce'] ?? false);
    }

    if (
        config('app.ec')
        && $ecommerceModuleEnabled
        && class_exists(\Modules\Ecommerce\Http\Controllers\EcommerceController::class)
    ) {
        $ecommerceHome = Route::get('/', [\Modules\Ecommerce\Http\Controllers\EcommerceController::class, 'home'])
            ->name('ecommerce.home');
        if (class_exists(\Modules\Ecommerce\Http\Middleware\EnsureEcommerceStorefrontEnabled::class)) {
            $ecommerceHome->middleware(['ecommerce.storefront']);
        }
    } elseif (filter_var(env('IS', false), FILTER_VALIDATE_BOOLEAN)) {
        Route::get('/', function () {
            if (View::exists('saas::saas-landing')) {
                return view('saas::saas-landing');
            }

            return redirect()->route('login');
        });
    } else {
        Route::get('/', function () {
            return redirect()->route('login');
        })->middleware('guest');
    }

    Route::get('lang/{locale}', [LanguageController::class, 'switch'])
        ->name('language.switch');
});

// Uninstall license (when app is installed)
Route::get('uninstall-license', [InstallController::class, 'uninstall'])->name('uninstall.license');
Route::post('uninstall-license', [InstallController::class, 'uninstallLicense'])->name('uninstall.submit');

/*
|--------------------------------------------------------------------------
| Update routes (when app is installed, auth required)
|--------------------------------------------------------------------------
*/
Route::middleware(['redirect.if.not.installed', 'auth'])
    ->prefix('update')
    ->name('update.')
    ->group(function () {
        Route::get('verification', [UpdateController::class, 'updateVerification'])->name('verification');
        Route::post('verification', [UpdateController::class, 'verifyUpdate'])->name('verify');
        Route::get('/', [UpdateController::class, 'index'])->name('index');
        Route::post('do-update', [UpdateController::class, 'doUpdate'])->name('do');
        Route::post('install', [UpdateController::class, 'installUpdate'])->name('install');
        Route::get('upgrade-license', [UpdateController::class, 'upgradeLicense'])->name('upgrade-license');
        Route::post('upgrade-license', [UpdateController::class, 'submitUpgradeLicense'])->name('upgrade.submit');
    });

// Success / vendor-done stay reachable if auth/session is shaky right after update.
Route::middleware(['redirect.if.not.installed'])
    ->prefix('update')
    ->name('update.')
    ->group(function () {
        Route::get('success', [UpdateController::class, 'success'])->name('success');
        Route::post('vendor-done', [UpdateController::class, 'vendorDone'])->name('vendor-done');
    });

