<?php

namespace Modules\Ecommerce\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Ecommerce\Support\Storefront;
use Symfony\Component\HttpFoundation\Response;

class EnsureEcommerceStorefrontEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Storefront::checkerEnabled()) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => __('Ecommerce storefront is not enabled.'),
                ], 403);
            }

            if (Storefront::envEnabled()) {
                return redirect()->route('login');
            }

            abort(404);
        }

        return $next($request);
    }
}
