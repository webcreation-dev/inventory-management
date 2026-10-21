<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCompanyTimezone
{
    /**
     * Use the logged-in company's zone_name (IANA identifier) for app time.
     * Falls back to config app.timezone when session has no company or invalid zone.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $default = config('app.timezone', 'UTC');
        $zoneName = $request->session()->get('company.zone_name');

        $timezone = is_string($zoneName) && $zoneName !== ''
            ? $this->validatedTimezone($zoneName, $default)
            : $default;

        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);

        return $next($request);
    }

    protected function validatedTimezone(string $candidate, string $fallback): string
    {
        try {
            new \DateTimeZone($candidate);

            return $candidate;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
