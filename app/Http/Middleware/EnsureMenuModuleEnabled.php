<?php

namespace App\Http\Middleware;

use App\Services\MenuModuleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMenuModuleEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $modules = app(MenuModuleService::class);
            $key = $modules->forPath($request->path());
            if ($key && !$modules->enabled($key)) {
                if ($key === 'menu_dashboard' && $request->isMethod('GET')) {
                    return redirect('/user-home');
                }
                abort(403, __('Ce module est désactivé pour votre entreprise.'));
            }
        }
        return $next($request);
    }
}
