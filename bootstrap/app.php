<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Middleware\EnsureOutletSet;
use App\Http\Middleware\EnsureRegisterOpen;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Auth\Access\AuthorizationException;
use App\Services\InstallerService;
use App\Services\UpdateService;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(prepend: [
            \App\Http\Middleware\VerifyInstallationIntegrity::class,
        ]);
        // Apply locale and demo mode middleware to web routes
        $middleware->web(append: [
            SetLocale::class,
            \App\Http\Middleware\EnsureMenuModuleEnabled::class,
            \App\Http\Middleware\DemoModeRestriction::class,
        ]);
        
        $aliases = [
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'outlet_set' => EnsureOutletSet::class,
            'register_open' => EnsureRegisterOpen::class,
            'locale' => SetLocale::class,
            'redirect.if.installed' => \App\Http\Middleware\RedirectIfInstalled::class,
            'redirect.if.not.installed' => \App\Http\Middleware\RedirectIfNotInstalled::class,
            'verify.installation.integrity' => \App\Http\Middleware\VerifyInstallationIntegrity::class,
            'demo.mode' => \App\Http\Middleware\DemoModeRestriction::class,
        ];

        if (class_exists(\Modules\SaaS\Http\Middleware\EnsureSaasLandlordOperator::class)) {
            $aliases['saas.landlord'] = \Modules\SaaS\Http\Middleware\EnsureSaasLandlordOperator::class;
        }
        if (class_exists(\Modules\SaaS\Http\Middleware\EnsureSaasRegistrationEnabled::class)) {
            $aliases['saas.register_open'] = \Modules\SaaS\Http\Middleware\EnsureSaasRegistrationEnabled::class;
        }
        if (class_exists(\Modules\SaaS\Http\Middleware\CheckSubscriptionMiddleware::class)) {
            $aliases['saas.subscription'] = \Modules\SaaS\Http\Middleware\CheckSubscriptionMiddleware::class;
        }
        if (class_exists(\Modules\Ecommerce\Http\Middleware\EnsureEcommerceStorefrontEnabled::class)) {
            $aliases['ecommerce.storefront'] = \Modules\Ecommerce\Http\Middleware\EnsureEcommerceStorefrontEnabled::class;
        }

        $middleware->alias($aliases);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Decode status codes from non-obvious values (security: avoid plain numbers in source)
        $xc = function (int $v) {
            static $k = 0x8A3F;
            return $v ^ $k;
        };

        // After file update, vendor may be stale → Module provider class missing.
        // Bypass and show success + vendor download instead of a fatal error page.
        $exceptions->render(function (Throwable $e, Request $request) {
            $message = $e->getMessage();
            $isMissingModuleProvider = ($e instanceof \Error || $e instanceof \ErrorException)
                && str_contains($message, 'not found')
                && (str_contains($message, 'Modules\\') || str_contains($message, 'Modules\\\\'));

            if (! $isMissingModuleProvider) {
                return null;
            }

            try {
                $updateService = app(UpdateService::class);
                if (! $updateService->isVendorPending()) {
                    $updateService->markVendorPending(__('Installed successfully.'));
                }

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'status' => 'success',
                        'message' => __('Installed successfully. Please download and replace vendor.'),
                        'action' => url('/update/success'),
                        'caption' => __('Continue'),
                    ]);
                }

                return response()->view('update.success', [
                    'successMessage' => __('Installed successfully. Please download and replace vendor before continuing.'),
                    'vendorDownloadUrl' => UpdateService::VENDOR_DOWNLOAD_URL,
                    'lpIsBd' => app(InstallerService::class)->getInstallationTypeFromEnv() === 'BD',
                ]);
            } catch (Throwable) {
                $url = UpdateService::VENDOR_DOWNLOAD_URL;
                $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Update Successful</title>'
                    .'<style>body{font-family:system-ui,sans-serif;max-width:640px;margin:40px auto;padding:0 16px;color:#0f172a}'
                    .'.box{border:1px solid #fcd34d;background:#fffbeb;padding:16px;border-radius:12px;margin:16px 0}'
                    .'a.btn{display:inline-block;background:#4f46e5;color:#fff;padding:10px 16px;border-radius:10px;text-decoration:none}</style></head><body>'
                    .'<h1>Update installed successfully</h1>'
                    .'<div class="box"><p><strong>Important:</strong> Download vendor.zip and replace your <code>vendor</code> folder, then open the app again.</p>'
                    .'<p><a class="btn" href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'">Download Vendor</a></p></div>'
                    .'<p><a href="/update/success">Continue to success page</a></p></body></html>';

                return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($xc) {
            $code = $e instanceof HttpException ? $e->getStatusCode() : null;
            if ($code === $xc(0x8B9C)) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'message' => __('Your session has expired. Please refresh the page and try again.'),
                    ], $xc(0x8B9C));
                }
                $installer = app(InstallerService::class);
                if (!$installer->isInstalled()) {
                    $previous = url()->previous();
                    $path = $previous ? (string) (parse_url($previous, PHP_URL_PATH) ?: '') : '';
                    $onInstaller = $path !== '' && str_contains($path, 'install');
                    $msg = __(
                        'This page expired. Press Ctrl + Shift + R the page you were on, then submit again.'
                    );
                    if ($onInstaller) {
                        return redirect()->to($previous)->with('error', $msg);
                    }

                    return redirect()
                        ->route('install.welcome')
                        ->with('error', $msg . ' ' . __('You were returned to step 1 of the installer.'));
                }

                return redirect()->guest(route('login'))
                    ->with('error', __('Your session has expired. Please log in again.'));
            }
            if ($code === $xc(0x8BEE)) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Not Implemented'], $xc(0x8BEE))
                    : response()->view('errors.501', [], $xc(0x8BEE));
            }
            if ($code === $xc(0x8BEF)) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Bad Gateway'], $xc(0x8BEF))
                    : response()->view('errors.502', [], $xc(0x8BEF));
            }
            if ($code === $xc(0x8BED)) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Internal Server Error'], $xc(0x8BED))
                    : response()->view('errors.500', [], $xc(0x8BED));
            }
            $is403 = ($e instanceof HttpException && $e->getStatusCode() === $xc(0x89FC))
                || $e instanceof AuthorizationException;
            if ($is403) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => $e->getMessage()], $xc(0x89FC));
                }
                if (Auth::check()) {
                    return response()->view('errors.403_layout', ['exception' => $e], $xc(0x89FC));
                }
                return response()->view('errors.403', ['exception' => $e], $xc(0x89FC));
            }
        });
    })->create();
