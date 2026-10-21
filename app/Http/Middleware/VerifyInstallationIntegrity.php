<?php

namespace App\Http\Middleware;

use App\Services\InstallerService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInstallationIntegrity
{
    public function __construct(
        protected InstallerService $installer
    ) {}

    /**
     * 
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->installer->isInstalled()) {
            return $next($request);
        }

        if ($request->routeIs('uninstall.license') || $request->routeIs('uninstall.submit')) {
            return $next($request);
        }

        // $result = $this->installer->cheSecInt();

        // if (!$result['ok'] && $result['code'] !== null) {
        //     abort($result['code']);
        // }

        return $next($request);
    }
}
