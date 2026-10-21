<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get available locales (Biponi LP=BD is limited to English + Bangla)
        $availableLocales = available_ui_locales();
        
        // Get locale from session, or use default from config
        $locale = Session::get('locale', config('app.locale'));
        
        // Validate locale is in available locales
        if (! in_array($locale, $availableLocales, true)) {
            $locale = in_array(config('app.locale'), $availableLocales, true)
                ? config('app.locale')
                : ($availableLocales[0] ?? 'en');
            Session::put('locale', $locale);
        }
        
        // Set the application locale
        App::setLocale($locale);
        
        // Share locale with all views
        view()->share('currentLocale', $locale);
        
        return $next($request);
    }
}
