<?php

namespace App\Http\Middleware;

use App\Services\InstallerService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfNotInstalled
{
    public function __construct(
        protected InstallerService $installer
    ) {}

    /**
     * Redirect to installer if application is not yet installed.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->installer->isInstalled()) {
            return $next($request);
        }

        if ($request->routeIs('install.*')) {
            return $next($request);
        }

        // Visiting /login while the app is not installed often happens after a 419 / session issue;
        // explain clearly instead of silently sending users to step 1.
        if ($request->routeIs('login')) {
            return redirect()
                ->route('install.welcome')
                ->with(
                    'error',
                    __(
                        'The login page is only available after installation. If you were verifying your purchase, your session may have expired or refresh the installer, go back to the Purchase step, and try again.'
                    )
                );
        }

        return redirect()->route('install.welcome');
    }
}
